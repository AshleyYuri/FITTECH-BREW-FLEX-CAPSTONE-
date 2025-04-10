<?php
session_start();
require_once 'db_connection.php'; // Database connection setup

// Redirect to login if the username is not set in the session or if the user is not an admin
if (!isset($_SESSION["username"]) || $_SESSION['usertype'] != 'admin') {
    header("location:/brew+flex/auth/login.php");
    exit;
}

$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"] ?? null; // Retrieve user type from session if it exists
$admin_info = [];
$staff_info = [];
$no_staff_id_error = false; // Initialize the error flag
$error_message = ""; // Initialize the error message variable

// Check if 'staff_id' is provided in the query string
$staff_id = $_GET['staff_id'] ?? null;

if (!$staff_id) {
    $no_staff_id_error = true; // Set error flag
    $error_message = "Error: No staff ID provided."; // Define error message
} else {
    // Fetch admin information for the sidebar
    $admin_query = "SELECT profile_picture, email, username FROM users WHERE username = ? AND usertype = 'admin'";
    $admin_stmt = $conn->prepare($admin_query);
    $admin_stmt->bind_param("s", $username);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_result->num_rows === 1) {
        $admin_info = $admin_result->fetch_assoc();
    }
    $admin_stmt->close();

    // Fetch staff info (including gender)
    $query = "SELECT username, contact_no, email, gender, profile_picture FROM users WHERE user_id = ? AND usertype = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $staff_info = $result->fetch_assoc();
    } else {
        $no_staff_id_error = true;
        $error_message = "Error: Staff information not found.";
    }
    $stmt->close();
}

// Handle staff information update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $new_username = trim($_POST['username']);
        $contact_no = trim($_POST['contact_no']);
        $email = trim($_POST['email']);
        $gender = trim($_POST['gender']); // Fetch gender value from form
        $target_file = $staff_info['profile_picture']; // Default to the existing profile picture

        // Check if the username already exists
        $check_query = "SELECT COUNT(*) FROM users WHERE username = ? AND usertype = 'staff' AND user_id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $new_username, $staff_id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            $_SESSION['update_error'] = "Username already exists. Please choose a different username.";
            header("Location: editstaff.php?staff_id=$staff_id");
            exit;
        } else {
            // Handle file upload if a new file is provided
            if (!empty($_FILES['profile_picture']['name'])) {
                $target_dir = "uploads/";
                $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Validate uploaded image
                $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
                if ($check === false) {
                    $_SESSION['update_error'] = "File is not an image.";
                    header("Location: editstaff.php?staff_id=$staff_id");
                    exit;
                } elseif ($_FILES["profile_picture"]["size"] > 2000000) {
                    $_SESSION['update_error'] = "File is too large.";
                    header("Location: editstaff.php?staff_id=$staff_id");
                    exit;
                } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $_SESSION['update_error'] = "Invalid file format.";
                    header("Location: editstaff.php?staff_id=$staff_id");
                    exit;
                } elseif (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    $_SESSION['update_error'] = "Error uploading file.";
                    header("Location: editstaff.php?staff_id=$staff_id");
                    exit;
                }
            }

            // Update staff info in the database
            $update_query = "UPDATE users SET username = ?, contact_no = ?, email = ?, gender = ?, profile_picture = ? WHERE user_id = ? AND usertype = 'staff'";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssssi", $new_username, $contact_no, $email, $gender, $target_file, $staff_id);

            if ($update_stmt->execute()) {
                $_SESSION['update_success'] = "Staff information updated successfully!";
                header("Location: editstaff.php?staff_id=$staff_id"); // Redirect after success
                exit; // Prevent further code execution
            } else {
                $_SESSION['update_error'] = "Error updating staff information.";
                header("Location: editstaff.php?staff_id=$staff_id"); // Redirect after error
                exit;
            }
        }
    }
    // Handle password update
// Handle password update
if (isset($_POST['update_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    // Query to fetch the stored password of the staff user
    $query = "SELECT password FROM users WHERE user_id = ? AND usertype = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    // Check if user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify the current password
        if (password_verify($current_password, $user['password'])) {
            
            // Check if new password and confirmation match
            if ($new_password === $confirm_password) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                // Update the password in the database
                $update_query = "UPDATE users SET password = ? WHERE user_id = ? AND usertype = 'staff'";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("si", $hashed_password, $staff_id);

                // Execute update query
                if ($update_stmt->execute()) {
                    $_SESSION['update_success'] = "Password updated successfully!";
                    header("Location: editstaff.php?staff_id=$staff_id"); // Redirect after success
                    exit; // Prevent further code execution
                } else {
                    $_SESSION['update_error'] = "Error updating password.";
                    header("Location: editstaff.php?staff_id=$staff_id"); // Redirect after error
                    exit;
                }
            } else {
                $_SESSION['update_error'] = "New password and confirmation do not match.";
                header("Location: editstaff.php?staff_id=$staff_id"); // Redirect to avoid form resubmission
                exit;
            }
        } else {
            $_SESSION['update_error'] = "Current password is incorrect.";
            header("Location: editstaff.php?staff_id=$staff_id"); // Redirect to avoid form resubmission
            exit;
        }
    } else {
        $_SESSION['update_error'] = "User not found.";
        header("Location: editstaff.php?staff_id=$staff_id"); // Redirect to avoid form resubmission
        exit;
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff - Brew & Flex Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/editstaff.css">
</head>
<body>
<?php if ($no_staff_id_error): ?>
        <!-- Modal for Missing Staff ID -->
        <div id="errorModal" class="modal" style="display: flex;">
            <div class="modal-content">
                <i class="fas fa-exclamation-circle modal-icon" style="color: red; font-size: 24px;"></i>
                <h3>Error</h3>
                <p><?php echo $error_message; ?></p>
                <div class="modal-buttons">
                    <button class="confirm-btn" onclick="redirectToManageStaff()">OK</button>
                </div>
            </div>
        </div>
    <?php else: ?>
    <div class="container">
        <aside class="sidebar">
            <div class="admin-info">
                <img src="<?php echo htmlspecialchars($admin_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Admin Avatar">
                <h3><?php echo htmlspecialchars($admin_info['username']); ?></h3>
                <p><?php echo htmlspecialchars($admin_info['email']); ?></p>
            </div>
            <nav class="menu">
                <ul>
                    <li ><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php if ($usertype === 'admin'): ?>
                        <li><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                        <li class="active"><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <?php endif; ?>
                    <li><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
                    <li><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                    <li ><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                    <li><a href="payment.php"><i class="fas fa-credit-card"></i> Payment</a></li>
                    <li><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                    <li><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
                    <li><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
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
    <h2>Edit Staff Information <i class="fas fa-users"></i></h2>
    <div class="profile-section">
        <div class="profile-card">
            <div class="profile-picture">
            <img id="profilePicturePreview" src="<?php echo htmlspecialchars($staff_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Staff Avatar">
            </div>
            <p><b>Username: </b><?php echo htmlspecialchars($staff_info['username']); ?></p>
            <p><b>Gender: </b><?php echo htmlspecialchars($staff_info['gender'] ?? 'Not specified'); ?></p>
            <p><b>Contact No: </b><?php echo htmlspecialchars($staff_info['contact_no']); ?></p>
            <p><b>Email: </b><?php echo htmlspecialchars($staff_info['email']); ?></p>
        </div>

        <form class="edit-form" method="post" enctype="multipart/form-data" onsubmit="return confirmAction('update_info');">
            <h3>Update Info</h3>
            <input type="text" name="username" value="<?php echo htmlspecialchars($staff_info['username']); ?>" required placeholder="Username">
            <select name="gender" id="gender" required>
                <option value="male" <?php if ($staff_info['gender'] === 'male') echo 'selected'; ?>>Male</option>
                <option value="female" <?php if ($staff_info['gender'] === 'female') echo 'selected'; ?>>Female</option>
                <option value="other" <?php if ($staff_info['gender'] === 'other') echo 'selected'; ?>>Other</option>
            </select>
            <input type="text" name="contact_no" value="<?php echo htmlspecialchars($staff_info['contact_no']); ?>" required placeholder="Contact Number">
            <input type="email" name="email" value="<?php echo htmlspecialchars($staff_info['email']); ?>" required placeholder="Email Address">
            

            <!-- File Input for Profile Picture -->
            <label for="profile_picture" class="custom-file-upload">
                        Choose Profile Picture
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" onchange="previewProfilePicture();">
                    </label>
            
            <button type="submit" name="update_info" class="save-btn">Save</button>
        </form>

        <form method="post" onsubmit="return confirmAction('update_password');">
            <h3>Change Password</h3>
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password" required pattern="^[a-zA-Z0-9]{6,}$" title="Password must contain at least 6 characters or numbers.">
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="update_password" class="change-password-btn">Change Password</button>
        </form>
    </div>
</main>
    </div>
        <?php endif; ?>
<!-- Modal for Success/Error Messages -->
<div class="modal-message" id="messageModal">
    <div class="message-content">
        <i id="modalIcon" class="fas fa-info-circle modal-icon"></i> <!-- Icon for success/error -->
        <h3 id="modalTitle">Message</h3> <!-- Title (Success/Error) -->
        <p id="modalMessage"></p> <!-- Message content -->
        <div class="modal-buttons">
            <button class="confirm-btn" onclick="closeMessageModal()">Close</button>
        </div>
    </div>
</div>
<!-- Error Modal -->
<div id="errorModal" class="modal" style="display: none;">
        <div class="modal-content">
            <i class="fas fa-exclamation-circle modal-icon" style="color: red;"></i>
            <h3 id="modalTitle">Error</h3>
            <p id="modalMessage">Error: No staff ID provided.</p>
            <div class="modal-buttons">
                <button class="confirm-btn" onclick="redirectToManageStaff()">OK</button>
            </div>
        </div>
    </div>


<script>
    // Preview the profile picture when selected
    function previewProfilePicture() {
        const file = document.getElementById('profile_picture').files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            document.getElementById('profilePicturePreview').src = e.target.result;
        };

        if (file) {
            reader.readAsDataURL(file);
        }
    }
    // Redirect to the manage staff page
    function redirectToManageStaff() {
            window.location.href = "managestaff.php"; // Navigate to managestaff.php
        }


function confirmAction(actionType) {
    let confirmationMessage = "";
    
    // Decide what confirmation message to show based on the action type
    if (actionType === "update_info") {
        confirmationMessage = "Are you sure you want to update the staff information?";
    } else if (actionType === "update_password") {
        confirmationMessage = "Are you sure you want to change the password?";
    }
    
    // Show the confirmation prompt
    return confirm(confirmationMessage); // If the user clicks 'OK', returns true, else false
}

// Example of how you would use it in your form submission
document.querySelector('form.edit-form').onsubmit = function(event) {
    if (!confirmAction("update_info")) {
        event.preventDefault(); // Prevent form submission if user clicks 'Cancel'
    }
};

document.querySelector('form').onsubmit = function(event) {
    if (!confirmAction("update_password")) {
        event.preventDefault(); // Prevent form submission if user clicks 'Cancel'
    }
};

window.onload = function() {
    <?php if (isset($_SESSION['update_success']) || isset($_SESSION['update_error'])): ?>
        var message = "<?php echo $_SESSION['update_success'] ?? $_SESSION['update_error']; ?>";
        var messageType = "<?php echo isset($_SESSION['update_success']) ? 'success' : 'error'; ?>";

        // Set message content dynamically
        document.getElementById("modalMessage").innerHTML = message;

        // Set title and icon based on message type
        if (messageType === 'success') {
            document.getElementById("modalTitle").innerHTML = "Success!";
            document.getElementById("modalIcon").classList.add("fa-check-circle");
            document.getElementById("modalIcon").classList.remove("fa-info-circle");
            document.querySelector('.message-content').classList.add("success");
        } else {
            document.getElementById("modalTitle").innerHTML = "Error!";
            document.getElementById("modalIcon").classList.add("fa-times-circle");
            document.getElementById("modalIcon").classList.remove("fa-info-circle");
            document.querySelector('.message-content').classList.add("error");
        }

        // Clear the session data after displaying the message
        <?php unset($_SESSION['update_success'], $_SESSION['update_error']); ?>

        // Show the modal
        var modal = document.getElementById("messageModal");
        modal.style.display = "flex";

        // Close the modal
        document.getElementsByClassName("close-btns")[0].onclick = function() {
            closeMessageModal();
        };
    <?php endif; ?>
};

// Function to close the modal
function closeMessageModal() {
    var modal = document.getElementById("messageModal");
    modal.style.display = "none";
    <?php unset($_SESSION['update_success'], $_SESSION['update_error']); ?> // Clear session messages
}
</script>
    <script src="/brew+flex/js/editstaff.js"></script>
</body>
</html>