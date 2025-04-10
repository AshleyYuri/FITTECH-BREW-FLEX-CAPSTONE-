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

$coach_id = $_GET['id'] ?? null;

if (!$coach_id || !is_numeric($coach_id)) {
    // Redirect to the coaches list page if no valid ID is provided
    header("Location: coaches.php");
    exit;
}

if ($coach_id) {
    $query = "SELECT * FROM coaches WHERE coach_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $coach_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $coach_details = $result->fetch_assoc();
    } else {
        die("Coach not found.");
    }
}

// Handle form submission for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'An error occurred.'];

    // Get form data
    $first_name = $_POST['name'] ?? '';
    $last_name = $_POST['lastname'] ?? '';
    $contact_number = $_POST['contact'] ?? '';
    $expertise = $_POST['type'] ?? '';
    $gender = $_POST['gender'] ?? ''; // Add gender

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($contact_number) || empty($expertise) || empty($gender)) {
        $response['message'] = 'All fields are required.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contact_number)) {
        $response['message'] = 'Invalid contact number format.';
    } else {
        // Update the coach details in the database
        $query = "UPDATE coaches SET first_name = ?, last_name = ?, contact_number = ?, expertise = ?, gender = ?, updated_at = NOW() WHERE coach_id = ?";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param('sssssi', $first_name, $last_name, $contact_number, $expertise, $gender, $coach_id);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Coach updated successfully.';
            } else {
                $response['message'] = 'Failed to update coach.';
            }

            $stmt->close();
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
    <title>Edit Coach - Brew + Flex Gym</title>
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

        <!-- Update Confirmation Modal -->
<div class="update-confirmation-modal" id="updateConfirmationModal" style="display: none;">
    <div class="update-confirmation-modal-content">
        <h3>Confirm Update</h3>
        <p>Are you sure you want to update this coach's information?</p>
        <div class="modal-buttons">
            <button class="confirm-btn" id="confirmUpdateButton">Yes, Update</button>
            <button class="cancel-btn" id="cancelUpdateButton">Cancel</button>
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
            <h3>Edit Coach <i class="fas fa-user-friends"></i></h3>
            <form id="editCoachForm">
                <div class="form-group">
                    <div>
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($coach_details['first_name'] ?? ''); ?>" placeholder="Enter first name" required>
                    </div>
                    <div>
                        <label for="lastname">Lastname</label>
                        <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($coach_details['last_name'] ?? ''); ?>" placeholder="Enter last name" required>
                    </div>
                </div>
                <div class="form-group">
                    <div>
                        <label for="contact">Contact Number</label>
                        <input type="tel" id="contact" name="contact" value="<?php echo htmlspecialchars($coach_details['contact_number'] ?? ''); ?>" placeholder="Enter contact number" pattern="[0-9]{11}" maxlength="11" required>
                    </div>
                    <div>
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="" disabled>Select gender</option>
                            <option value="male" <?php echo ($coach_details['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($coach_details['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>
                    <div class="form-group">
                    <div>
                        <label for="type">Expertise</label>
                        <select id="type" name="type" required>
                            <option value="" disabled>Select type</option>
                            <option value="Bodybuilding" <?php echo ($coach_details['expertise'] ?? '') === 'Bodybuilding' ? 'selected' : ''; ?>>Bodybuilding</option>
                            <option value="Power Lifting" <?php echo ($coach_details['expertise'] ?? '') === 'Power Lifting' ? 'selected' : ''; ?>>Power Lifting</option>
                            <option value="Strength Training" <?php echo ($coach_details['expertise'] ?? '') === 'Strength Training' ? 'selected' : ''; ?>>Strength Training</option>
                            <option value="Boxing" <?php echo ($coach_details['expertise'] ?? '') === 'Boxing' ? 'selected' : ''; ?>>Boxing</option>
                            <option value="Muay Thai" <?php echo ($coach_details['expertise'] ?? '') === 'Muay Thai' ? 'selected' : ''; ?>>Muay Thai</option>
                        </select>
                    </div>
                </div>
                <div class="form-buttons">
                <button type="button" class="cancel-btn" onclick="window.location.href='coaches.php'">Back</button>
                    <button type="submit" class="next-btn">Update</button>
                </div>
            </form>
        </div>
    </main>
</div>
<style>
    .update-confirmation-dialog {
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
.dialog-content {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease; /* Slide-in animation for the modal */
    position: relative;
}

.dialog-icon {
    font-size: 40px;
    color: #71d4fc; /* Vibrant blue for attention */
    margin-bottom: 15px;
}

.dialog-content h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

/* Modal Buttons */
.dialog-buttons {
    margin-top: 20px;
}

.dialog-content button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

/* Confirm Button */
.confirm-btn {
    background-color: #71d4fc; /* Same blue as the icon */
    color: #ffffff;
}

.confirm-btn:hover {
    background-color: #5bb0d9; /* Slightly darker on hover */
    transform: scale(1.05); /* Slight zoom effect */
}

/* Cancel Button */
.cancel-btn {
    background-color: #ccc;
    color: #333;
}

.cancel-btn:hover {
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

</style>
<script src="/brew+flex/js/editcoaches.js"></script>
</body>
</html>
