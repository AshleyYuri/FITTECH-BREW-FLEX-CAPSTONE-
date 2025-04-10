<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

// Retrieve session variables
$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];
$user_info = [];

// Fetch user information from the database
$query = "SELECT username, contact_no, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $usertype);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user_info = $result->fetch_assoc();
} else {
    echo "User information not found.";
    exit;
}

// Ensure member_id is provided in the GET parameter
if (isset($_GET['id'])) {
    $member_id = $_GET['id'];

    // Fetch existing member information
    $query = "SELECT * FROM members WHERE member_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $member_info = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Member ID not specified.");
}

// Check if member exists
if (!$member_info) {
    die("Member not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Retrieve form data
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $contact_no = $_POST['contact_no'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $country = $_POST['country'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';
    $city = $_POST['city'] ?? '';
    $municipality = $_POST['municipality'] ?? '';
    $profile_picture_path = $member_info['profile_picture']; // Default to existing picture

    // Validate input data
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Invalid email address.";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $contact_no)) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = "Invalid contact number format.";
    } else {
        // Handle profile picture upload if a new one is provided
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            // Get file details
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_name = $_FILES['profile_picture']['name'];
            $file_size = $_FILES['profile_picture']['size'];

            // Validate file type (only allow jpg, jpeg, png)
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                $_SESSION['status'] = 'error';
                $_SESSION['message'] = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
            } elseif ($file_size > 5 * 1024 * 1024) { // Limit to 5MB
                $_SESSION['status'] = 'error';
                $_SESSION['message'] = "File size too large. Max allowed is 5MB.";
            } else {
                // Generate a new filename and move the file to the upload directory
                $upload_dir = 'uploads/profile_pictures/';
                $new_file_name = uniqid('', true) . '.' . $file_extension;
                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    // Set the new profile picture path
                    $profile_picture_path = $upload_dir . $new_file_name;
                } else {
                    $_SESSION['status'] = 'error';
                    $_SESSION['message'] = "Error uploading the profile picture.";
                }
            }
        }

        // If no error, proceed with updating the database
        if (!isset($_SESSION['status'])) {
            // Prepare SQL query to update member info
            $update_query = "
                UPDATE members 
                SET 
                    first_name = ?, 
                    last_name = ?, 
                    gender = ?, 
                    email = ?, 
                    contact_no = ?, 
                    country = ?, 
                    zipcode = ?, 
                    city = ?,
                    municipality = ?,
                    profile_picture = ?
                WHERE member_id = ?";

            $stmt = $conn->prepare($update_query);
            $stmt->bind_param(
                "ssssssssssi",
                $first_name,
                $last_name,
                $gender,
                $email,
                $contact_no,
                $country,
                $zipcode,
                $city,
                $municipality,
                $profile_picture_path,
                $member_id
            );

            if ($stmt->execute()) {
                $_SESSION['status'] = 'success';
                $_SESSION['message'] = 'Member information updated successfully!';
            } else {
                $_SESSION['status'] = 'error';
                $_SESSION['message'] = 'Error updating member information.';
            }

            // Redirect to avoid resubmission on refresh
            header("Location: editmember.php?id=$member_id");
            exit(); // Ensure no further code is executed after the redirect
        }
    }
}

// Always fetch the latest member data from the database
$member_query = "
    SELECT 
        m.member_id, 
        m.first_name, 
        m.last_name,
        m.gender, 
        m.email, 
        m.contact_no, 
        m.birthday, 
        m.zipcode, 
        m.municipality,
        m.city, 
        m.country, 
        m.profile_picture,
        COALESCE(p.membership_renewal_payment_date, m.date_enrolled) AS date_enrolled,
        COALESCE(p.membership_expiration_date, m.expiration_date) AS expiration_date,
        p.monthly_plan_payment_date, 
        p.monthly_plan_expiration_date, 
        p.locker_payment_date,
        p.locker_expiration_date,
        p.coaching_payment_date,
        CONCAT(c.first_name, ' ', c.last_name, ' (', c.expertise, ')') AS coach_full_details,
        MAX(a.check_in_date) AS last_attendance_date,
        CASE 
            WHEN p.monthly_plan_payment_date IS NULL THEN 'Pending'
            WHEN p.monthly_plan_expiration_date < NOW() THEN 'Inactive'
            WHEN MAX(a.check_in_date) IS NULL OR MAX(a.check_in_date) < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 'Inactive'
            ELSE 'Active'
        END AS status
    FROM 
        members m
    LEFT JOIN 
        payments p 
    ON 
        m.member_id = p.member_id
    LEFT JOIN 
        coaches c 
    ON 
        p.coach_id = c.coach_id
    LEFT JOIN 
        attendance a 
    ON 
        m.member_id = a.member_id
    WHERE 
        m.member_id = ?
    GROUP BY 
        m.member_id
";
$stmt = $conn->prepare($member_query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member_info) {
    die("Error: Member not found.");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member Info - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/editmember.css">
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
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <?php if ($usertype === 'admin'): ?>
                    <li><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                    <li><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                <?php endif; ?>
                <li><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
                <li class="active"><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                <li><a href="payment.php"><i class="fas fa-credit-card"></i> Payment</a></li>
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                <li><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
                <li><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
                <li><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
                <li><a href="reports.php"><i class="fas fa-file-alt"></i> Transaction Logs</a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-bar"></i>  Analytics</a></li>
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
<!-- Confirmation Modal for Saving Changes -->
<div class="confirm-modal" id="saveModal">
    <div class="confirm-modal-content">
        <i class="fas fa-exclamation-circle modal-icon"></i>
        <h3>Are you sure you want to save changes?</h3>
        <div class="modal-buttons">
            <button class="confirm-btn" id="confirm-save">Yes, Save Changes</button>
            <button class="cancel-btn" onclick="closeSaveModal()">Cancel</button>
        </div>
    </div>
</div>
<!-- Success Modal -->
<div class="modal" id="successModal">
    <div class="modal-content success">
        <i class="fas fa-check-circle modal-icon"></i>  <!-- Green check icon -->
        <h3 id="successMessage">Success!</h3>
        <div class="modal-buttons">
            <button class="confirm-btn" onclick="closeSuccessModal()">Close</button>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal" id="errorModal">
    <div class="modal-content error">
        <i class="fas fa-times-circle modal-icon"></i>  <!-- Red error icon -->
        <h3 id="errorMessage">Error!</h3>
        <div class="modal-buttons">
            <button class="confirm-btn cancel-btn" onclick="closeErrorModal()">Close</button>
        </div>
    </div>
</div>




    </aside>

    <div class="main-content-wrapper">
        <div class="main-content">
            <div class="profile-resume">
                <!-- Profile Section -->
                <div class="profile-header">
                    <div class="profile-picture">
                    <img id="profile_picture_preview" 
         src="<?php echo htmlspecialchars($member_info['profile_picture'] ?? 'https://via.placeholder.com/180'); ?>" 
         alt="Profile Picture" 
         width="180" height="180">
</div>

                    <div class="profile-summary">
                        <h1><?php echo htmlspecialchars($member_info['first_name'] . ' ' . $member_info['last_name']); ?></h1>
                        <p class="profile-status">Status: <?php echo htmlspecialchars($member_info['status']); ?></p>
                    </div>
                </div>
                <!-- Editable Details Section -->
                <div class="profile-details">
                <form action="" method="POST" enctype="multipart/form-data" id="edit-member-form">
                <div id="profile-picture-container">
    <label id="upload-btn" for="profile_picture">Choose File</label>
    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(event)">
    <div id="preview-container">
        <img id="preview-image" src="#" alt="Image Preview" style="display:none;">
    </div>
</div>
                    <h2>Edit Member Details</h2>
                    <?php if (isset($error_message)): ?>
                        <p style="color: red;"> <?php echo htmlspecialchars($error_message); ?></p>
                    <?php endif; ?>
                        <div class="details-grid">
                            <div>                               
                                <label for="first_name">First Name:</label>                                
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member_info['first_name']); ?>" required>
                            </div>
                            <div>
                                <label for="last_name">Last Name:</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member_info['last_name']); ?>" required>
                            </div>
                            <div>
                                <label for="gender">Gender:</label>
                                <select id="gender" name="gender" required>
                                    <option value="" disabled>Select gender</option>
                                    <option value="male" <?php echo ($member_info['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($member_info['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member_info['email']); ?>" required>
                            </div>
                            <div>
                                <label for="contact_no">Phone Number:</label>
                                <input type="tel" id="contact_no" name="contact_no" value="<?php echo htmlspecialchars($member_info['contact_no']); ?>" required>
                            </div>
<div></div>                
                            <div>
    <label for="zipcode">Zipcode:</label>
    <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($member_info['zipcode']); ?>" 
           required pattern="\d{4}" title="Zipcode must be 4 digits">
</div>
            <div>
                <label for="municipality">Municipality:</label>
                <input type="text" id="municipality" name="municipality" value="<?php echo htmlspecialchars($member_info['municipality']); ?>" required>
            </div>
            <div>
                <label for="city">City:</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($member_info['city']); ?>" required>
            </div>
            <div>
                <label for="country">Country:</label>
                <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($member_info['country']); ?>" required>
            </div>                               
                        </div>
                        <div class="profile-actions">
                        <button type="button" class="action-btn" onclick="showSaveModal()">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Modal Styles for Success and Error */
.modal {
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

/* Modal Content for Success and Error */
.modal-content {
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
    margin-bottom: 15px;
}


.modal-content.success .modal-icon {
    color: #4caf50; /* Green icon for success */
}



.modal-content.error .modal-icon {
    color: #f44336; /* Red icon for error */
}

.modal-content h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

/* Modal Buttons */
.modal-buttons {
    margin-top: 20px;
}

.modal-content button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

/* Confirm Button (for Success and Error Modals) */
.confirm-btn {
    background-color: #71d4fc;
    color: #ffffff;
}

.confirm-btn:hover {
    background-color: #71d4fc;
    transform: scale(1.05);
}

/* Cancel Button (for Error Modal) */
.cancel-btn {
    background-color: #ccc;
    color: #333;
}

.cancel-btn:hover {
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

  


</style>
    <script>
window.onload = function() {
    // Check if the session status is set to success or error
    <?php if (isset($_SESSION['status']) && $_SESSION['status'] == 'success'): ?>
        document.getElementById('successMessage').innerText = "<?php echo $_SESSION['message']; ?>";
        document.getElementById('successModal').style.display = 'flex';
        // Change icon to green for success
        document.querySelector('#successModal .modal-icon').style.color = '#4caf50';  // Green icon
        <?php unset($_SESSION['status']); unset($_SESSION['message']); ?>
    <?php elseif (isset($_SESSION['status']) && $_SESSION['status'] == 'error'): ?>
        document.getElementById('errorMessage').innerText = "<?php echo $_SESSION['message']; ?>";
        document.getElementById('errorModal').style.display = 'flex';
        // Change icon to red for error
        document.querySelector('#errorModal .modal-icon').style.color = '#f44336';  // Red icon
        
        // Dynamically change the button color and label for the error modal
        const errorButton = document.getElementById('errorModalButton');
        errorButton.innerText = "Retry"; // Set button text to "Retry"
        errorButton.style.backgroundColor = '#f44336'; // Set button color to red for error
        
        <?php unset($_SESSION['status']); unset($_SESSION['message']); ?>
    <?php endif; ?>
}

// Function to close success modal
function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
}

// Function to close error modal
function closeErrorModal() {
    document.getElementById('errorModal').style.display = 'none';
}




        // Show the confirmation modal
function showSaveModal() {
    document.getElementById('saveModal').style.display = 'flex';
}

// Close the confirmation modal
function closeSaveModal() {
    document.getElementById('saveModal').style.display = 'none';
}

// Handle the "Yes, Save Changes" button click
document.getElementById('confirm-save').addEventListener('click', function() {
    // Submit the form after confirmation
    document.getElementById('edit-member-form').submit(); // Replace 'edit-member-form' with your form's ID
    closeSaveModal(); // Close the modal
});

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
function previewImage(event) {
    var reader = new FileReader();
    reader.onload = function() {
        // Get the image element for preview
        var output = document.getElementById('profile_picture_preview');
        output.src = reader.result; // Set the source to the file data URL
    };
    reader.readAsDataURL(event.target.files[0]); // Read the selected file
}
    </script>
</body>
</html>