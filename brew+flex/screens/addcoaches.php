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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'An error occurred.'];

    // Get form data
    $first_name = $_POST['name'] ?? '';
    $last_name = $_POST['lastname'] ?? '';
    $contact_number = $_POST['contact'] ?? '';
    $expertise = $_POST['type'] ?? '';
    $gender = $_POST['gender'] ?? '';

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($contact_number) || empty($expertise) || empty($gender)) {
        $response['message'] = 'All fields are required.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contact_number)) {
        $response['message'] = 'Invalid contact number format.';
    } elseif (!in_array($gender, ['male', 'female', 'other'])) {
        $response['message'] = 'Invalid gender selected.';
    } else {
        // Check if the coach already exists
        $check_query = "SELECT coach_id FROM coaches WHERE first_name = ? AND last_name = ? AND gender = ?";
        $check_stmt = $conn->prepare($check_query);

        if ($check_stmt) {
            $check_stmt->bind_param('sss', $first_name, $last_name, $gender);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $response['message'] = 'Coach with the same name and gender already exists.';
            } else {
                // Insert into database
                $query = "INSERT INTO coaches (first_name, last_name, contact_number, expertise, gender, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);

                if ($stmt) {
                    $stmt->bind_param('sssss', $first_name, $last_name, $contact_number, $expertise, $gender);

                    if ($stmt->execute()) {
                        $response['status'] = 'success';
                        $response['message'] = 'Coach added successfully.';
                    } else {
                        $response['message'] = 'Failed to add coach.';
                    }

                    $stmt->close();
                } else {
                    $response['message'] = 'Database error: ' . $conn->error;
                }
            }

            $check_stmt->close();
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
        }
    }

    echo json_encode($response);
    exit;
}

// Fetch user session details
$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];
$user_info = [];

// Fetch user information
$query = "SELECT username, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("ss", $username, $usertype);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaches - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/addcoaches.css">
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
    <main class="main-content">
        <div class="header">
            <img src="/brew+flex/assets/brewlogo2.png" class="logo">
        </div>
        <div class="form-container">
            <h3>Add a Coach<i class="fas fa-user-friends"></i></h3>
            <form id="addCoachForm">
                <div class="form-group">
                    <div>
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" placeholder="Enter first name" required>
                    </div>
                    <div>
                        <label for="lastname">Lastname</label>
                        <input type="text" id="lastname" name="lastname" placeholder="Enter last name" required>
                    </div>
                </div>
                <div class="form-group">
    <div>
        <label for="contact">Contact Number</label>
        <input type="tel" id="contact" name="contact" placeholder="Enter contact number" pattern="[0-9]{11}" maxlength="11" required>
    </div>
    <div>
        <label for="gender">Gender</label>
        <select id="gender" name="gender" required>
            <option value="" disabled selected>Select Gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>
    </div>
</div>

                    <div class="form-group">
                    <div>
                        <label for="type">Expertise</label>
                        <select id="type" name="type" required>
                            <option value="" disabled selected>Select type</option>
                            <option value="Bodybuilding">Bodybuilding</option>
                            <option value="Power Lifting">Power Lifting</option>
                            <option value="Strength Training">Strength Training</option>
                            <option value="Boxing">Boxing</option>
                            <option value="Muay Thai">Muay Thai</option>
                        </select>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="goToPreviousScreen()">Back</button>
                    <button type="button" class="next-btn" onclick="showConfirmationModal()">Add</button>
                </div>
            </form>
        </div>
    </main>
</div>

<!-- Confirmation Modal -->
<div class="modal-confirmation" id="confirmationModal" style="display: none;">
    <div class="modal-content-confirmation">
    <i class="fas fa-question-circle modal-icon"></i> <!-- Confirmation Icon -->
        <h3>Confirm Submission</h3>
        <p>Are you sure you want to add this coach?</p>
        <div class="modal-buttons">
            <button class="confirms-buttons" onclick="submitCoachForm()">Yes, Confirm</button>
            <button class="cancels-buttons" onclick="closeConfirmationModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal-success" id="successModal" style="display: none;">
    <div class="modal-content-success">
        <i class="fas fa-check-circle modal-icon" style="color: #28a745;"></i>
        <h3>Success!</h3>
        <p>The coach was added successfully.</p>
        <div class="modal-buttons">
            <button class="confirms-buttons" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal-error" id="errorModal" style="display: none;">
    <div class="modal-content-error">
        <i class="fas fa-times-circle modal-icon" style="color: #dc3545;"></i>
        <h3>Error!</h3>
        <p>Something went wrong. Please try again.</p>
        <div class="modal-buttons">
            <button class="cancels-buttons" onclick="closeErrorModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* Confirmation Modal Styles */
.modal-confirmation {
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

.modal-content-confirmation {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease;
}

.modal-content-confirmation .modal-icon {
    font-size: 40px;
    color: #71d4fc; /* Match the confirm button color */
    margin-bottom: 15px;
}

.modal-content-confirmation h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

.modal-content-confirmation p {
    font-size: 1rem;
    color: #555;
    margin-bottom: 20px;
}

.modal-content-confirmation .modal-buttons {
    margin-top: 20px;
}

.modal-content-confirmation button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.modal-content-confirmation .confirms-buttons {
    background-color: #71d4fc;
    color: #ffffff;
}

.modal-content-confirmation .confirms-buttons:hover {
    background-color: #5ec4e3;
    transform: scale(1.05);
}

.modal-content-confirmation .cancels-buttons {
    background-color: #ccc;
    color: #333;
}

.modal-content-confirmation .cancels-buttons:hover {
    background-color: #bbb;
    transform: scale(1.05);
}

/* Success Modal Styles */
.modal-success {
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
}

.modal-content-success {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease;
}

.modal-content-success .modal-icon {
    font-size: 40px;
    color: #28a745; /* Green for success */
    margin-bottom: 15px;
}

.modal-content-success h3,
.modal-content-success p {
    color: #333;
}

.modal-content-success .modal-buttons {
    margin-top: 20px;
}

.modal-content-success button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.modal-content-success .confirms-buttons {
    background-color: #71d4fc;
    color: #ffffff;
}

.modal-content-success .confirms-buttons:hover {
    background-color: #5ec4e3;
    transform: scale(1.05);
}

/* Error Modal Styles */
.modal-error {
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
}

.modal-content-error {
    background: linear-gradient(to bottom right, #ffe6e6, #fff9f9); /* Gradient for error */
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease;
}

.modal-content-error .modal-icon {
    font-size: 40px;
    color: #dc3545; /* Red for error */
    margin-bottom: 15px;
}

.modal-content-error h3,
.modal-content-error p {
    color: #333;
}

.modal-content-error .modal-buttons {
    margin-top: 20px;
}

.modal-content-error button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.modal-content-error .cancels-buttons {
    background-color: #71d4fc;
    color: #ffffff;
}

.modal-content-error .cancels-buttons:hover {
    background-color: #5ec4e3;
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


</style>

<script>
document.getElementById('addCoachForm').addEventListener('submit', async function (event) {
    event.preventDefault();
});

function showConfirmationModal() {
    document.getElementById('confirmationModal').style.display = 'flex';
}

function closeConfirmationModal() {
    document.getElementById('confirmationModal').style.display = 'none';
}

function showSuccessModal() {
    document.getElementById('successModal').style.display = 'flex';
}

function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
    // Redirect to coaches.php after closing success modal
    window.location.href = 'coaches.php';
}

function showErrorModal() {
    document.getElementById('errorModal').style.display = 'flex';
}

function closeErrorModal() {
    document.getElementById('errorModal').style.display = 'none';
}

async function submitCoachForm() {
    const formData = new FormData(document.getElementById('addCoachForm'));
    const addButton = document.querySelector('.next-btn');
    addButton.disabled = true;
    addButton.innerHTML = `Adding... <div class="loader"></div>`;

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();

        if (result.status === 'success') {
            closeConfirmationModal();
            showSuccessModal();
        } else {
            closeConfirmationModal();
            showErrorModal();
        }
    } catch (error) {
        closeConfirmationModal();
        showErrorModal();
    } finally {
        addButton.disabled = false;
        addButton.innerHTML = 'Add';
    }
}

function goToPreviousScreen() {
    window.history.back();
}

const style = document.createElement('style');
style.innerHTML = `
    .loader {
        display: inline-block;
        margin-left: 10px;
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-top: 2px solid #009acd;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

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
