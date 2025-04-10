<?php
session_start();
require_once 'db_connection.php';

// Redirect to login if the username is not set in the session
if (!isset($_SESSION["username"])) {
    header("location:/brew+flex/auth/login.php");
    exit;
}

$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"] ?? null; // Retrieve user type from session if it exists
$admin_info = [];

// Handle success message for deletion
$delete_msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Fetch admin information from the database
$query = "SELECT username, contact_no, email, profile_picture FROM users WHERE username = ? AND usertype = 'admin'";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $admin_info = $result->fetch_assoc();
} else {
    echo "Admin information not found.";
    exit;
}

// Fetch all staff members from the database, including the gender field
$staff_query = "SELECT user_id, username, contact_no, email, profile_picture, gender FROM users WHERE usertype = 'staff'";
$staff_result = $conn->query($staff_query);


// Handle staff deletion via AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff_id'])) {
    $staff_id = $_POST['delete_staff_id'];
    
    // Ensure the staff_id is numeric to prevent SQL injection
    if (is_numeric($staff_id)) {
        // Prepare the delete query to remove the staff member
        $delete_query = "DELETE FROM users WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $staff_id);
        
        if ($delete_stmt->execute()) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully.']);
        } else {
            // Return failure response if query fails
            echo json_encode(['success' => false, 'message' => 'Error deleting staff member.']);
        }
    } else {
        // Return failure response if staff_id is not valid
        echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Brew & Flex Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/managestaff.css">
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
    z-index: 9999;
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

    <div class="main-content">
        <h2 class="title">Manage Staff <i class="fas fa-users"></i></h2>

        <div class="staff-cards-container">
            <?php if ($delete_msg): ?>
                <p class="success-msg"><?php echo htmlspecialchars($delete_msg); ?></p>
            <?php endif; ?>
            <?php if ($staff_result->num_rows > 0): ?>
                <div class="staff-cards">
                    <?php 
                    $staff_count = 1;
                    while ($staff = $staff_result->fetch_assoc()): ?>
                        <div class="staff-card">
                        <div class="card-actions">
                                <a href="editstaff.php?staff_id=<?php echo urlencode($staff['user_id']); ?>" title="Edit Staff"><i class="fas fa-edit"></i></a>
                                <a href="#" class="delete-staff-link" data-staff-id="<?php echo urlencode($staff['user_id']); ?>" title="Remove Staff"><i class="fas fa-trash-alt"></i></a>
                            </div>
                            <p class="staff-label">Staff <?php echo $staff_count++; ?></p>
                            <div class="staff-avatar">
                                <img src="<?php echo htmlspecialchars($staff['profile_picture'] ?? 'default-profile.png'); ?>" alt="Staff Profile Picture">
                            </div>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($staff['username']); ?></p>
                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($staff['gender']); ?></p>
                            <p><strong>Contact No.:</strong> <?php echo htmlspecialchars($staff['contact_no']); ?></p>
                            <p><strong>Email Address:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="no-staff-msg">No staff available, add some staff members.</p>
            <?php endif; ?>
        </div>

        <div style="align-self: flex-end; margin-top: 20px;"><button class="add-staff" onclick="location.href='addstaff.php'">Add Staff</button></div>
    </div>
</div>

<!-- Staff Deletion Confirmation Modal -->
<div id="deleteStaffModal" class="modal-confirmation-staff" style="display: none;">
    <div class="modal-content-confirmation-staff">
        <i class="fas fa-trash-alt modal-icon-confirmation-staff"></i>
        <h3>Are you sure you want to remove this staff member?</h3>
        <div class="modal-buttons-confirmation-staff">
            <button id="confirmDeleteBtn" class="confirm-btn-staff">Yes, Delete</button>
            <!-- Updated Cancel Button with Correct Event Binding -->
            <button class="cancel-btn-staff" id="cancelDeleteBtn">Cancel</button>
        </div>
    </div>
</div>
<!-- Success Modal -->
<div id="successModal" class="modal-confirmation" style="display: none;">
    <div class="modal-content-confirmation">
        <i class="fas fa-check-circle modal-icon"></i>
        <h3>Staff member deleted successfully!</h3>
        <button class="confirm-btn-success">OK</button>
    </div>
</div>
<style>

    
.staff-cards {
  display: grid
;
  grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
  gap: 120px;
  justify-items: center;
  width: 100%;
  padding: 30px;
}



.staff-cards-container {
  display: flex;
  justify-content: center;
  width: 100%;
  background: linear-gradient(to bottom right, #bde3e8, #f2f2f2, #bde3e8);
  padding: 20px;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}
.staff-card {
    background: linear-gradient(to bottom right, #f0faff, #ffffff); /* Softer gradient */
  border-radius: 20px;
  overflow: hidden;
  text-align: center;
  border: 1px solid #ddd;
  box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  padding: 10px; /* Reduced padding */
  min-height: 200px; /* Reduced min-height */
}

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
  animation: slideIn 0.4s ease; /* Slide-in animation for the modal */
  position: relative;
}

.modal-icon {
    font-size: 40px;
  color: #71d4fc; /* Vibrant blue for attention */
  margin-bottom: 15px;
}

.confirm-btn-success {
    margin: 10px 5px;
  padding: 10px 20px;
  background-color: #71d4fc; /* Same blue as the icon */
  color: #ffffff;
  border: none;
  cursor: pointer;
  border-radius: 5px;
  font-weight: bold;
  transition: background-color 0.3s ease, transform 0.2s ease;
}

.confirm-btn-success:hover {
    background-color: #61c0d4; /* Slightly darker on hover */
    transform: scale(1.05); /* Slight zoom effect */
}

</style>
<script>
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
   document.addEventListener("DOMContentLoaded", function () {
    const deleteStaffModal = document.getElementById("deleteStaffModal");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
    const successModal = document.getElementById("successModal"); // Success modal
    const confirmSuccessBtn = document.querySelector('.confirm-btn-success'); // Success confirmation button

    // Function to show the delete confirmation modal
    function showDeleteStaffModal() {
        deleteStaffModal.style.display = "flex"; // Show the modal
    }

    // Function to hide the delete confirmation modal
    function hideDeleteStaffModal() {
        deleteStaffModal.style.display = "none"; // Hide the modal
    }

    // Function to show the success modal
    function showSuccessModal() {
        successModal.style.display = "flex"; // Show the success modal
    }

    // Function to hide the success modal
    function hideSuccessModal() {
        successModal.style.display = "none"; // Hide the success modal
    }

    // Handle staff delete request
    const deleteLinks = document.querySelectorAll('.delete-staff-link');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            const staffId = this.getAttribute("data-staff-id"); // Get the staff ID
            confirmDeleteBtn.setAttribute("data-staff-id", staffId); // Set staff ID on the confirm button
            showDeleteStaffModal(); // Show the delete modal
        });
    });

    // Handle the confirmation of the delete action
    confirmDeleteBtn.addEventListener("click", function() {
        const staffId = this.getAttribute("data-staff-id"); // Get staff ID

        // Send the delete request via AJAX
        fetch('managestaff.php', {
            method: 'POST',
            body: new URLSearchParams({
                delete_staff_id: staffId
            })
        })
        .then(response => response.json()) // Parse the JSON response
        .then(data => {
            if (data.success) {
                hideDeleteStaffModal(); // Hide delete modal
                showSuccessModal(); // Show success modal
            } else {
                alert(data.message); // Show error message in case of failure
            }
        })
        .catch(error => {
            console.error('Error deleting staff member:', error);
            alert('Error deleting staff member');
        });
    });

    // Handle the cancel action (Hide the modal)
    cancelDeleteBtn.addEventListener('click', function() {
        hideDeleteStaffModal(); // Hide the modal when cancel button is clicked
    });

    // Handle the success modal close action
    confirmSuccessBtn.addEventListener('click', function() {
        hideSuccessModal(); // Hide the success modal when OK is clicked
        location.reload(); // Optionally reload the page
    });
});


</script>

</body>
</html>
