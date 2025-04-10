<?php
session_start();
require_once 'db_connection.php';

// Redirect to login if the username is not set in the session
if (!isset($_SESSION["username"])) {
    header("location:/brew+flex/auth/login.php");
    exit;
}

date_default_timezone_set('Asia/Manila'); // Set timezone to Philippines

$currentDateTime = date('Y-m-d\TH:i'); // Format for datetime-local input

$successMessage = "";
$errorMessage = "";
$username = $_SESSION["username"];
$user_info = [];
$walkinData = null; // Initialize to null, in case it's a new walk-in

// Retrieve session variables
$usertype = $_SESSION["usertype"];
// Fetch user info
$query = "SELECT username, email, profile_picture, usertype FROM users WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user_info = $result->fetch_assoc();
} else {
    echo "User information not found.";
}

// Fetch existing walk-in data if editing
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $walkinId = $_GET['id'];

    // Fetch existing walk-in data for pre-filling the form
    $stmt = $conn->prepare("SELECT * FROM walkins WHERE id = ?");
    $stmt->bind_param("i", $walkinId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $walkinData = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle POST request to register/update walk-ins
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $lastname = $_POST['lastname'];
    $join_date = $_POST['join_date'];
    $contact = $_POST['contact'];
    $type = $_POST['type'];
    $gender = $_POST['gender'];
    $amount = ($type === 'basic') ? 70 : (($type === 'premium') ? 270 : 0);

    // Check if the walk-in already exists
    $checkQuery = "SELECT * FROM walkins WHERE contact_number = ? AND name = ? AND lastname = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("sss", $contact, $name, $lastname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Walk-in exists, update walk-ins table and insert the old data into walkins_logs
        $existingWalkin = $result->fetch_assoc();
        $walkinId = $existingWalkin['id'];

      // Insert the old data into walkins_logs
$action = 'Updated'; // Define the action type
$logStmt = $conn->prepare("INSERT INTO walkins_logs (id, name, lastname, contact_number, gender, join_date, walkin_type, amount, action, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$logStmt->bind_param(
    "issssssss",
    $walkinId,
    $existingWalkin['name'],
    $existingWalkin['lastname'],
    $existingWalkin['contact_number'],
    $existingWalkin['gender'],
    $existingWalkin['join_date'],
    $existingWalkin['walkin_type'],
    $existingWalkin['amount'],
    $action
);
if (!$logStmt->execute()) {
    $errorMessage = "Error saving walk-in log: " . $logStmt->error;
}
$logStmt->close();


        // Update the existing walk-in
        if ($type !== $existingWalkin['walkin_type']) {
            // Type has changed, update the amount
            $updateStmt = $conn->prepare("UPDATE walkins SET name = ?, lastname = ?, join_date = ?, contact_number = ?, walkin_type = ?, amount = ?, gender = ? WHERE id = ?");
            $updateStmt->bind_param("sssssssi", $name, $lastname, $join_date, $contact, $type, $amount, $gender, $walkinId);
        } else {
            // Type has not changed, keep the existing amount
            $updateStmt = $conn->prepare("UPDATE walkins SET name = ?, lastname = ?, join_date = ?, contact_number = ?, gender = ? WHERE id = ?");
            $updateStmt->bind_param("sssssi", $name, $lastname, $join_date, $contact, $gender, $walkinId);
        }

        if ($updateStmt->execute()) {
            $successMessage = "Existing walk-in updated successfully!";
        } else {
            $errorMessage = "Error updating walk-in: " . $updateStmt->error;
        }
        $updateStmt->close();
    } else {
        // New walk-in, insert into walkins table
        $stmt = $conn->prepare("INSERT INTO walkins (name, lastname, join_date, contact_number, walkin_type, amount, gender) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $lastname, $join_date, $contact, $type, $amount, $gender);
        if ($stmt->execute()) {
            $successMessage = "New walk-in added successfully!";
        } else {
            $errorMessage = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brew & Flex Fitness Gym Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/addwalkins.css">
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
                    <li ><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
                    <li class="active"><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
                    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Report</a></li>
                    <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Reports Analytics</a></li>
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
            <img src="/brew+flex/assets/brewlogo2.png" class="logo" alt="Brew & Flex Logo">
        </div>
        <div class="form-container">
        <h3><?php echo $walkinData ? 'Add Existing Walk-in' : 'Add Walk-in'; ?> <i class="fas fa-walking"></i></h3>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="confirmWalkinRegistration(event, <?php echo isset($walkinData) ? 'true' : 'false'; ?>)">
        <div class="form-group">
                    <div>
                        <label for="name">First name</label>
                        <input type="text" name="name" id="name" placeholder="Enter first name" required pattern="^[a-zA-Z\s]+$" title="Only letters and spaces are allowed." value="<?php echo htmlspecialchars($walkinData['name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="lastname">Last name</label>
                        <input type="text" name="lastname" id="lastname" placeholder="Enter last name" required pattern="^[a-zA-Z\s]+$" title="Only letters and spaces are allowed." value="<?php echo htmlspecialchars($walkinData['lastname'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                <div>
                <label for="gender">Gender</label>
                        <select name="gender" id="gender" required>
                            <option value="" enable>Select Gender</option>
                            <option value="male" <?php echo ($walkinData['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($walkinData['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($walkinData['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="contact">Contact Number</label>
                        <input type="tel" name="contact" id="contact" placeholder="Enter contact number" pattern="[0-9]{11}" maxlength="11" required value="<?php echo htmlspecialchars($walkinData['contact_number'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div>
                        <label for="type">Walk-in Type</label>
                        <select name="type" id="type" required>
                            <option value="" disabled selected>Select type</option>
                            <option value="basic">Basic (Without Coach)</option>
                            <option value="premium">Premium (With Coach)</option>
                        </select>
                    </div>
                    <div>
    <label for="join-date">Date and Time of Join</label>
    <input type="datetime-local" name="join_date" id="join-date" required>
</div>

                </div>
                <div class="form-group">
                    <div>
                        <label for="amount">Amount</label>
                        <span id="amount" class="amount-display">0</span>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="window.location.href='walkins.php';">Cancel</button>
                    <button type="submit" class="next-btn">Add</button>
                    </div>
            </form>
        </div>
    </main>
</div>
<!-- Confirmation Modal -->
<div class="modal-walkin-confirm" id="walkinConfirmModal">
    <div class="modal-walkin-confirm-content">
    <i class="fas fa-question-circle modal-icon"></i> <!-- Icon for confirmation -->
        <h3>Confirm Walk-in Registration</h3>
        <p id="walkinConfirmMessage"></p>
        <div class="modal-walkin-confirm-buttons">
            <button class="modal-walkin-confirm-yes">Yes</button>
            <button class="modal-walkin-confirm-no" onclick="closeWalkinConfirmModal()">No</button>
        </div>
    </div>
</div>
<!-- Success Modal -->
<div class="modal-walkin-success" id="walkinSuccessModal">
    <div class="modal-walkin-success-content">
        <h3>Success</h3>
        <p id="walkinSuccessMessage"></p>
        <button class="modal-walkin-success-ok" onclick="closeWalkinSuccessModal()">OK</button>
    </div>
</div>

<!-- Error Modal -->
<div class="modal-walkin-error" id="walkinErrorModal">
    <div class="modal-walkin-error-content">
        <h3>Error</h3>
        <p id="walkinErrorMessage"></p>
        <button class="modal-walkin-error-ok" onclick="closeWalkinErrorModal()">OK</button>
    </div>
</div>
<style>
 .modal-walkin-confirm {
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

/* Modal Content */
.modal-walkin-confirm-content {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease; /* Slide-in animation for the modal */
    position: relative;
}

.modal-icon {
    font-size: 40px;
    color: #71d4fc; /* Vibrant color for attention */
    margin-bottom: 15px;
}

.modal-walkin-confirm-content h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

/* Modal Buttons */
.modal-button {
    margin-top: 20px;
}

.modal-walkin-confirm-content button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

/* Confirm Button */
.modal-walkin-confirm-yes {
    background-color: #71d4fc; /* Same color as the icon */
    color: #ffffff;
}

.modal-walkin-confirm-yes:hover {
    background-color: #71bce4; /* Slightly darker on hover */
    transform: scale(1.05); /* Slight zoom effect */
}

/* Cancel Button */
.modal-walkin-confirm-no {
    background-color: #ccc;
    color: #333;
}

.modal-walkin-confirm-no:hover {
    background-color: #bbb; /* Slightly darker on hover */
    transform: scale(1.05); /* Slight zoom effect */
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
/* General Modal Styles */
.modal-walkin-success, .modal-walkin-error {
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
    .modal-walkin-success-content, .modal-walkin-error-content {
        background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        width: 320px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.4s ease;
    }

    /* Modal Buttons */
    .modal-walkin-success-ok, .modal-walkin-error-ok {
        background-color: #71d4fc;
        color: #ffffff;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .modal-walkin-success-ok:hover, .modal-walkin-error-ok:hover {
        background-color: #71bce4;
        transform: scale(1.05);
    }

</style>

<script src="/brew+flex/js/addwalkins.js"></script>
<script>

document.addEventListener("DOMContentLoaded", function () {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset()); // Adjust for local time

        const formattedDateTime = now.toISOString().slice(0, 16); // Format for datetime-local
        document.getElementById("join-date").value = formattedDateTime;
    });


  // Function to open the confirmation modal
function openWalkinConfirmModal(isExistingWalkin, onConfirmCallback) {
    const modal = document.getElementById('walkinConfirmModal');
    const message = isExistingWalkin
        ? "This walk-in already exists. Are you sure you want to add again?"
        : "Are you sure you want to register this new walk-in?";
    const messageElement = document.getElementById('walkinConfirmMessage');
    messageElement.textContent = message;

    modal.style.display = 'flex';

    // Attach confirmation action
    const confirmButton = document.querySelector('.modal-walkin-confirm-yes');
    confirmButton.onclick = function () {
        closeWalkinConfirmModal();
        onConfirmCallback(); // Execute the callback (e.g., form submission)
    };
}

// Function to close the confirmation modal
function closeWalkinConfirmModal() {
    const modal = document.getElementById('walkinConfirmModal');
    modal.style.display = 'none';
}

// Function to handle walk-in registration with a confirmation modal
function confirmWalkinRegistration(event, isExistingWalkin) {
    // Prevent the default form submission
    event.preventDefault();

    // Open the confirmation modal
    openWalkinConfirmModal(isExistingWalkin, function () {
        // Submit the form programmatically after confirmation
        document.querySelector('form').submit();
    });
}



    const typeSelect = document.getElementById('type');
    const amountDisplay = document.getElementById('amount');

    typeSelect.addEventListener('change', function() {
        if (typeSelect.value === 'basic') {
            amountDisplay.textContent = 70;
        } else if (typeSelect.value === 'premium') {
            amountDisplay.textContent = 270;
        } else {
            amountDisplay.textContent = '0';
        }
    });

// Function to open the success modal
function openWalkinSuccessModal(message) {
    const modal = document.getElementById('walkinSuccessModal');
    const messageElement = document.getElementById('walkinSuccessMessage');
    messageElement.textContent = message;
    modal.style.display = 'flex';
}

// Function to close the success modal
function closeWalkinSuccessModal() {
    const modal = document.getElementById('walkinSuccessModal');
    modal.style.display = 'none';
    window.location.href = 'membersuccessfullyadded.php'; // Redirect after closing
}

// Function to open the error modal
function openWalkinErrorModal(message) {
    const modal = document.getElementById('walkinErrorModal');
    const messageElement = document.getElementById('walkinErrorMessage');
    messageElement.textContent = message;
    modal.style.display = 'flex';
}

// Function to close the error modal
function closeWalkinErrorModal() {
    const modal = document.getElementById('walkinErrorModal');
    modal.style.display = 'none';
}

// Handle success or error messages
<?php if (!empty($successMessage)): ?>
    openWalkinSuccessModal("<?php echo addslashes($successMessage); ?>");
<?php elseif (!empty($errorMessage)): ?>
    openWalkinErrorModal("<?php echo addslashes($errorMessage); ?>");
<?php endif; ?>

</script>
</body>
</html>