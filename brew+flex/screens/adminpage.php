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

// Fetch admin information for the sidebar
$admin_query = "SELECT profile_picture, email, username, contact_no FROM users WHERE username = ? AND usertype = 'admin'";
$admin_stmt = $conn->prepare($admin_query);
$admin_stmt->bind_param("s", $username);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
if ($admin_result->num_rows === 1) {
    $admin_info = $admin_result->fetch_assoc();
}
$admin_stmt->close();

// Handle admin information update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $new_username = trim($_POST['username']);
        $contact_no = trim($_POST['contact_no']);
        $email = trim($_POST['email']);
        $target_file = $admin_info['profile_picture'];

        // Check if the username already exists
        $check_query = "SELECT COUNT(*) FROM users WHERE username = ? AND usertype = 'admin' AND username != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $new_username, $username);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            $_SESSION['update_error'] = "Username already exists. Please choose a different username.";
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
                } elseif ($_FILES["profile_picture"]["size"] > 2000000) {
                    $_SESSION['update_error'] = "File is too large.";
                } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $_SESSION['update_error'] = "Invalid file format.";
                } elseif (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    $_SESSION['update_error'] = "Error uploading file.";
                }
            }

            // Update admin info in the database
            $update_query = "UPDATE users SET username = ?, contact_no = ?, email = ?, profile_picture = ? WHERE username = ? AND usertype = 'admin'";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssss", $new_username, $contact_no, $email, $target_file, $username);

            if ($update_stmt->execute()) {
                $_SESSION["username"] = $new_username; // Update session username if it was changed
                $_SESSION['update_success'] = "Admin information updated successfully!";
            } else {
                $_SESSION['update_error'] = "Error updating admin information.";
            }
        }

        // Redirect to avoid form resubmission
        header("location: adminpage.php");
        exit;
    }

    // Handle password update
    if (isset($_POST['update_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        $query = "SELECT password FROM users WHERE username = ? AND usertype = 'admin'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                    $update_query = "UPDATE users SET password = ? WHERE username = ? AND usertype = 'admin'";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ss", $hashed_password, $username);

                    if ($update_stmt->execute()) {
                        $_SESSION['update_success'] = "Password updated successfully!";
                    } else {
                        $_SESSION['update_error'] = "Error updating password.";
                    }
                    $update_stmt->close();
                } else {
                    $_SESSION['update_error'] = "New password and confirmation do not match.";
                }
            } else {
                $_SESSION['update_error'] = "Current password is incorrect.";
            }
        } else {
            $_SESSION['update_error'] = "Admin not found.";
        }
        $stmt->close();

        // Redirect to avoid form resubmission
        header("location: adminpage.php");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin - Brew & Flex Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/adminpage.css">
    <style>
        /* Style for the profile picture preview */
.profile-preview-img {
    margin-top: 10px;
    max-width: 150px;
    max-height: 150px;
    border-radius: 50%;
    object-fit: cover;
}

    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="admin-info">
                <img src="<?php echo htmlspecialchars($admin_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Admin Avatar">
                <h3><?php echo htmlspecialchars($admin_info['username']); ?></h3>
                <p><?php echo htmlspecialchars($admin_info['email']); ?></p>
            </div>
            <nav class="menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php if ($usertype === 'admin'): ?>
                        <li class="active"><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                        <li><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
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
            <h2>Admin Information <i class="fas fa-cogs"></i></h2>
            <div class="profile-section">
                <div class="profile-card">
                    <div class="profile-picture">
                    <img id="profile_picture_preview" src="<?php echo htmlspecialchars($admin_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Profile Picture Preview" class="profile-preview-img">
                    </div>
                    <p><b>Username:</b><?php echo htmlspecialchars($admin_info['username']); ?></p> 
                    <p><b>Contact No:</b><?php echo htmlspecialchars($admin_info['contact_no']); ?></p> 
                    <p><b>Email:</b><?php echo htmlspecialchars($admin_info['email']); ?></p> 
                </div>
                <form class="edit-form" method="post" enctype="multipart/form-data">
                    <h3>Update Info</h3>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($admin_info['username']); ?>" required>
                    <input type="text" name="contact_no" value="<?php echo htmlspecialchars($admin_info['contact_no']); ?>" required>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin_info['email']); ?>" required>
        <!-- File Input for Profile Picture -->
        <label for="profile_picture" class="custom-file-upload">
    Choose Profile Picture
    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" onchange="previewProfilePicture(event)">
</label>
                 
            <button type="submit" name="update_info" class="update-btn" onclick="return confirmInfoUpdate();">Update Info</button>
                    </form>
                <form method="post">
                    <h3>Change Password</h3>
                    <input type="password" name="current_password" placeholder="Current Password" required>
                    <input type="password" name="new_password" placeholder="New Password" required pattern="^[a-zA-Z0-9]{6,}$" title="Password must contain at least 6 characters or numbers.">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                    <button type="submit" name="update_password" class="change-password-btn" onclick="return confirmPasswordUpdate();">Update Password</button>
                    </form>
            </div>
        </main>
    </div>
<!-- Modal for Success/Error Messages -->
<div class="modal-message" id="messageModal" style="display: none;">
    <div class="message-content">
        <!-- Icon for success/error -->
        <i id="modalIcon" class="fas modal-icon"></i> 
        <h3 id="modalTitle">Message</h3> <!-- Title (Success/Error) -->
        <p id="modalMessage"></p> <!-- Message content -->
        <div class="modal-buttons">
            <button class="confirm-btn" onclick="closeMessageModal()">Close</button>
        </div>
    </div>
</div>
    <script src="/brew+flex/js/adminpage.js"></script>
    <script>
            // Display modal messages (success/error) on page load if applicable
    window.onload = function () {
        <?php if (isset($_SESSION['update_success']) || isset($_SESSION['update_error'])): ?>
            // Set the message and type dynamically
            var message = "<?php echo $_SESSION['update_success'] ?? $_SESSION['update_error']; ?>";
            var messageType = "<?php echo isset($_SESSION['update_success']) ? 'success' : 'error'; ?>";
    
            // Update modal content dynamically
            document.getElementById("modalMessage").innerHTML = message;
            var modalContent = document.querySelector(".message-content");
            var modalIcon = document.getElementById("modalIcon");
            var modalTitle = document.getElementById("modalTitle");
    
            // Reset styles and classes
            modalContent.classList.remove("success", "error");
            modalIcon.classList.remove("fa-check-circle", "fa-times-circle");
    
            // Apply styles and titles based on the message type
            if (messageType === "success") {
                modalContent.classList.add("success");
                modalIcon.classList.add("fa-check-circle");
                modalTitle.innerHTML = "Success!";
            } else {
                modalContent.classList.add("error");
                modalIcon.classList.add("fa-times-circle");
                modalTitle.innerHTML = "Error!";
            }
    
            // Show the modal
            var modal = document.getElementById("messageModal");
            modal.style.display = "flex";
    
            // Clear session messages after displaying
            <?php unset($_SESSION['update_success'], $_SESSION['update_error']); ?>
        <?php endif; ?>
    };
    
    // Close the modal
    function closeMessageModal() {
        var modal = document.getElementById("messageModal");
        modal.style.display = "none";
    }
    </script>
</body>
</html>