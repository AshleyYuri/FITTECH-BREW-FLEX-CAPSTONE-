<?php
session_start();
require_once 'db_connection.php';

// Redirect to login if the user isn't logged in
if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

// Database connection check
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Retrieve session variables
$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];
$user_info = [];

// Fetch user information from the database
$query = "SELECT username, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("ss", $username, $usertype);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
    } else {
        die("User information not found.");
    }
    $stmt->close();
} else {
    die("Error preparing the SQL statement: " . $conn->error);
}

// Handle POST submission for "Show Entities"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_entities'])) {
    $recordsToShow = (int)$_POST['show_entities'];
    header("Location: " . $_SERVER['PHP_SELF'] . "?show_entities=$recordsToShow");
    exit;
}

// Get query parameters for filtering and pagination
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchWildcard = '%' . $searchTerm . '%';
$recordsToShow = isset($_GET['show_entities']) ? (int)$_GET['show_entities'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Handle "Show All" case
if ($recordsToShow === 0) {
    $limit = null;
    $offset = null;
} else {
    $limit = $recordsToShow;
    $offset = ($page - 1) * $limit;
}

// Sorting parameters
$sortColumn = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'coach_id';
$sortBy = isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['asc', 'desc']) ? $_GET['sort_by'] : 'asc';

// Fetch coaches dynamically based on "Show All" or paginated results
$coaches = [];
if ($recordsToShow === 0) {
    $query = "
        SELECT coach_id, CONCAT(first_name, ' ', last_name) AS full_name, contact_number, expertise, gender
        FROM coaches
        WHERE CONCAT(first_name, ' ', last_name) LIKE ?
        ORDER BY $sortColumn $sortBy";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $searchWildcard);
} else {
    $query = "
        SELECT coach_id, CONCAT(first_name, ' ', last_name) AS full_name, contact_number, expertise, gender
        FROM coaches
        WHERE CONCAT(first_name, ' ', last_name) LIKE ?
        ORDER BY $sortColumn $sortBy
        LIMIT ?, ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $searchWildcard, $offset, $limit);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $coaches[] = $row;
    }
}

// Get total number of coaches for pagination
$query_total = "SELECT COUNT(*) AS total FROM coaches WHERE CONCAT(first_name, ' ', last_name) LIKE ?";
$stmt_total = $conn->prepare($query_total);
$stmt_total->bind_param("s", $searchWildcard);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_coaches = $result_total->fetch_assoc()['total'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaches - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/coaches.css">
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="admin-info">
                <img src="<?php echo htmlspecialchars($user_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Admin Avatar">
                <h3><?php echo htmlspecialchars($user_info['username']); ?></h3>
                <p><?php echo htmlspecialchars($user_info['email']); ?></p>
            </div>
            <nav class="menu">
                <ul>
                    <li ><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php if ($usertype === 'admin'): ?>
                        <li><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                        <li><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <?php endif; ?>
                    <li><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
                    <li><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                    <li ><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                    <li ><a href="payment.php"><i class="fas fa-credit-card"></i> Payment</a></li>
                    <li ><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                    <li ><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
                    <li class="active"><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
                    <li><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
                    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Transaction Logs</a></li>
                    <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                </ul>

                <div class="logout">
    <a href="#" onclick="showLogoutModal(); return true;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
<div class="modal-logouts" id="logoutModal">
    <div class="logouts-content">
        <i class="fas fa-sign-out-alt modal-icon"></i>
        <h3>Are you sure you want to log out?</h3>
        <div class="modal-buttons">
            <button class="confirms-buttons" onclick="handleLogout()">Yes, Log Out</button>
            <button class="cancels-buttons" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>
</aside>
</nav>

        <div class="content-wrapper">
    <div class="main-content">
        <div class="header">
            <h2>Coaches <i class="fas fa-dumbbell"></i></h2>
        </div>
        <div class="header-controls">
    <div class="action-buttons">
        <button class="add-btn" onclick="addCoach()">
            <span class="button-text">Add Coach</span>
            <i class="fas fa-user-friends"></i>
        </button>
    </div>
    <div class="entity-and-search">
    <div class="entity-controls">
    <h3>Show Entities</h3>
    <form method="POST" action="">
        <select name="show_entities" onchange="this.form.submit()">
            <option value="10" <?php echo ($recordsToShow == 10) ? 'selected' : ''; ?>>10</option>
            <option value="20" <?php echo ($recordsToShow == 20) ? 'selected' : ''; ?>>20</option>
            <option value="50" <?php echo ($recordsToShow == 50) ? 'selected' : ''; ?>>50</option>
            <option value="0" <?php echo ($recordsToShow == 0) ? 'selected' : ''; ?>>Show All</option>
        </select>
    </form>
</div>

        <form class="search-bar" method="GET" action="">
            <input type="text" name="search" placeholder="Search coaches..." value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>
                <div class="table-wrapper">
                    <div class="table-container">
                        <div class="scrollable-table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th><a href="?sort_column=coach_id&sort_by=<?php echo $sortBy === 'asc' ? 'desc' : 'asc'; ?>">Coach ID</a></th>
                                        <th>Full Name</th>
                                        <th>Gender</th> <!-- New column -->
                                        <th>Contact</th>
                                        <th>Expertise</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($coaches as $coach): ?>
                                    <?php
                                        $fullName = htmlspecialchars($coach['full_name']);

                                        // Highlight logic
                                        if (!empty($searchTerm)) {
                                            $searchPattern = '/' . preg_quote($searchTerm, '/') . '/i';
                                            $highlightTemplate = '<span class="highlight">$0</span>';
                                            $fullName = preg_replace($searchPattern, $highlightTemplate, $fullName);
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($coach['coach_id']); ?></td>
                                        <td class="name-cell"><?php echo $fullName; ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($coach['gender'])); ?></td> <!-- Display gender -->
                                        <td><?php echo htmlspecialchars($coach['contact_number']); ?></td>
                                        <td><?php echo htmlspecialchars($coach['expertise']); ?></td>
                                        <td>
                                            <button class="action-btn edit-btn" onclick="editCoach(<?php echo $coach['coach_id']; ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($coaches)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="no-results">No results found for "<?php echo htmlspecialchars($searchTerm); ?>"</div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>

                            </table>
                        </div>
                    </div>
                </div>
                            <!-- Display Total Coaches -->
<div class="total-coaches">
    <strong> Total Coaches: <?php echo number_format($total_coaches); ?></strong>
</div>
            </div>
        </div>
    </div>
<style>
.logout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none; /* Initially hidden */
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out; /* Animation for smooth appearance */
    }
    
    .modal-logouts {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none; /* Initially hidden */
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out;
}

/* Modal Content */
.logouts-content {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease;
    position: relative;
}

.modal-icon {
    font-size: 40px;
    color: #71d4fc;
    margin-bottom: 15px;
}

.logouts-content h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

/* Modal Buttons */
.modal-buttons {
    margin-top: 20px;
}

.logouts-content button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

/* Confirm Button */
.confirms-buttons {
    background-color: #71d4fc;
    color: #ffffff;
}

.confirms-buttons:hover {
    background-color: #71d4fc;
    transform: scale(1.05);
}

/* Cancel Button */
.cancels-buttons {
    background-color: #ccc;
    color: #333;
}

.cancels-buttons:hover {
    background-color: #bbb;
    transform: scale(1.05);
}

/* Modal Animations */
@keyframes fadeIn {
    0% {
        opacity: 0;
    }
    100% {
        opacity: 1;
    }
}

@keyframes slideIn {
    0% {
        transform: translateY(-50px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

</style>

    <script>
// Add Coach Button
const addBtn = document.querySelector('.add-btn');

// Add an event listener for when the "Add Coach" button is clicked
addBtn.addEventListener('click', function() {
    addBtn.innerHTML = '<span class="spinner"></span> Adding...';
    addBtn.disabled = true;

    // Redirect to add coach page after a slight delay
    setTimeout(() => {
        window.location.href = 'addcoaches.php';
    }, 500);
});

// Edit Coach Button
function editCoach(coachId) {
    // Show the confirmation modal
    const modal = document.getElementById('editCoachModal');
    modal.style.display = 'flex';

    // When the user clicks 'Yes, Edit', redirect to the edit page
    document.getElementById('confirmEditBtn').onclick = function() {
        window.location.href = `editcoaches.php?id=${coachId}`;
    };

    // When the user clicks 'No, Cancel', close the modal
    document.getElementById('cancelEditBtn').onclick = function() {
        modal.style.display = 'none';
    };
}


function editCoach(coachId) {
    window.location.href = `editcoaches.php?id=${coachId}`;
}

function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function handleLogout() {
    alert("You have been logged out.");
    window.location.href = "/brew+flex/logout.php";
}


    </script>
</body>
</html>
