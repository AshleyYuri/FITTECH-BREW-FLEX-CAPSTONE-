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

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $gender = trim($_POST['gender']);
    $profile_picture = 'default-profile.png'; // Default profile picture

    // Validate and upload profile picture
    if (!empty($_FILES['profile_picture']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($_FILES['profile_picture']['name']);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (!getimagesize($_FILES['profile_picture']['tmp_name'])) {
            $response['message'] = 'Uploaded file is not a valid image.';
        } elseif ($_FILES['profile_picture']['size'] > 2000000) {
            $response['message'] = 'Image file size exceeds 2MB.';
        } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $response['message'] = 'Invalid image format. Only JPG, JPEG, PNG, and GIF are allowed.';
        } else {
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture = $target_file;
            } else {
                $response['message'] = 'Failed to upload profile picture.';
            }
        }
    }

    // Validate input fields
    if (empty($username) || empty($email) || empty($contact_number) || empty($password) || empty($confirm_password) || empty($gender)) {
        $response['message'] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contact_number)) {
        $response['message'] = 'Invalid contact number format.';
    } elseif ($password !== $confirm_password) {
        $response['message'] = 'Passwords do not match.';
    } elseif (!in_array($gender, ['male', 'female', 'other'])) {
        $response['message'] = 'Invalid gender selection.';
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert user into the database
        $query = "INSERT INTO users (username, email, contact_no, password, profile_picture, usertype, gender) 
                  VALUES (?, ?, ?, ?, ?, 'staff', ?)";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param('ssssss', $username, $email, $contact_number, $hashed_password, $profile_picture, $gender);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Staff added successfully.';
            } else {
                $response['message'] = 'Failed to add staff.';
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
    <title>Add Staff - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/addstaff.css">
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
        <div class="header">
            <img src="/brew+flex/assets/brewlogo2.png" class="logo">
        </div>
        <div class="form-container">
            <h3>Add a Staff<i class="fas fa-user-friends"></i></h3>
            <form id="addStaffForm">
                <div>
<div class="profile-upload" style="text-align: center;">
    <img id="profilePicturePreview" src="default-profile.png" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; padding:1px">
    <button type="button" class="upload-btn" onclick="document.getElementById('profile_picture').click();">Upload Picture</button>
    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewProfilePicture(event)" style="display: none;">
    <button type="button" class="webcam-btn" onclick="startWebcam();">Capture</button>
                            <div id="webcamModal" class="webcam-modal">
                                <div class="webcam-modal-content">
                                    <span class="close-webcam" onclick="closeWebcamModal()">&times;</span>
                                    <video id="webcam" autoplay></video>
                                    <canvas id="webcamCanvas" style="display: none;"></canvas>
                                    <button type="button" class="webcam-btn" onclick="captureWebcamPicture()">Capture</button>
                                    <audio id="beepSound" src="/brew+flex/assets/Camera_Shutter_Sound_Effect_[_YouConvert.net_]-[AudioTrimmer.com].mp3" volume="1.0"></audio>
                                </div>
                            </div>
                            <input type="hidden" id="webcamImage" name="webcamImage">
                        </div>

                <div class="form-group">
                    <div>
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter username" required>
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter email" required>
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
            <option value="" enable>Select Gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>
    </div>
                </div>
                <div class="form-group">
                <div>
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required 
                           pattern="^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$" 
                           title="Password must be at least 8 characters long, contain at least one letter, one number, and one special character.">
                    </div>
                    <div>
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="next-btn">Add</button>
                </div>
            </form>
        </div>
    </main>
</div>
<!-- Confirmation Modal -->
<div id="confirmationModal" class="modal-confirmation" style="display: none;">
    <div class="modal-confirmation-content">
    <i class="fas fa-question-circle modal-icon"></i> <!-- Icon for confirmation -->
        <h3>Are you sure you want to register this staff as a staff of Brew+Flex?</h3>
        <div class="modal-buttons">
            <button id="confirmAddBtn" class="confirms-btns">Yes, Register</button>
            <button id="cancelAddBtn" class="cancels-btns">Cancel</button>
        </div>
    </div>
</div>
<!-- Modal for Image/Camera Error -->
<div id="imageErrorModal" class="modal-confirmation" style="display: none;">
    <div class="modal-confirmation-content">
        <i class="fas fa-exclamation-triangle modal-icons"></i> <!-- Icon for warning -->
        <h3>Please upload a profile picture or capture one using the webcam.</h3>
        <div class="modal-buttons">
            <button id="closeImageErrorModal" class="confirms-btns">Close</button>
        </div>
    </div>
</div>
<!-- Success Modal -->
<div id="successModal" class="modal-success">
    <div class="modal-content-success">
    <i class="fa fa-check-circle modal-icon"></i> <!-- Success icon -->
        <h3 id="successMessage">Staff added successfully!</h3>
</div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="modal-error">
    <div class="modal-content-error">
    <i class="fa fa-exclamation-circle modal-icon"></i> <!-- Error icon -->
        <h3 id="errorMessage">An error occurred. Please try again.</h3>
</div>
</div>
<style>
 /* Modal Styling */
.modal-success, .modal-error {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
    display: none; /* Initially hidden */
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out; /* Animation for smooth appearance */
}

/* Modal Content */
.modal-content-success, .modal-content-error {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2); /* Gradient background */
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease; /* Slide-in animation for the modal */
    position: relative;
}

/* Icon */
.modal-icon {
    font-size: 40px;
    margin-bottom: 15px;
}

/* Success Modal Icon */
.modal-success .modal-icon {
    color: #28a745; /* Green for success */
}

/* Error Modal Icon */
.modal-error .modal-icon {
    color: #dc3545; /* Red for error */
}

/* Modal Heading */
.modal-content-success h3, .modal-content-error h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}


.modal-content-success button, .modal-content-error button {
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
    background-color: #71d4fc; /* Same blue for confirm button */
    color: #ffffff;
}

.confirm-btn:hover {
    background-color: #5db3d6; /* Slightly darker on hover */
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
<style>
    .modal-icons {
    font-size: 40px;
    color: red; /* Vibrant blue for attention */
    margin-bottom: 15px;
}
</style>
<script>
document.querySelector('.next-btn').addEventListener('click', function (event) {
    event.preventDefault(); // Prevent default form submission

    const form = document.getElementById('addStaffForm');
    const fileInput = document.getElementById('profile_picture');
    const webcamInput = document.getElementById('webcamImage');

    // Check if the form is valid and a profile picture or webcam image is provided
    if (!form.checkValidity()) {
        form.reportValidity(); // Show validation errors
    } else if (!fileInput.files.length && !webcamInput.value) {
        // Show the custom modal for the missing profile picture or webcam image
        document.getElementById('imageErrorModal').style.display = 'flex';
    } else {
        document.getElementById('confirmationModal').style.display = 'flex';
    }
});

document.getElementById('closeImageErrorModal').addEventListener('click', function () {
    document.getElementById('imageErrorModal').style.display = 'none';
});

document.getElementById('cancelAddBtn').addEventListener('click', function () {
    document.getElementById('confirmationModal').style.display = 'none';
});

document.getElementById('confirmAddBtn').addEventListener('click', async function () {
    const form = document.getElementById('addStaffForm');
    const formData = new FormData(form);
    const addButton = document.querySelector('.next-btn');

    addButton.disabled = true;
    addButton.innerHTML = `Adding... <div class="loader"></div>`; // Show loader animation

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();

        if (result.status === 'success') {
            // Display success modal
            document.getElementById('successMessage').textContent = result.message;
            document.getElementById('successModal').style.display = 'flex';

            // Navigate to the Manage Staff page after a brief delay
            setTimeout(() => {
                window.location.href = 'managestaff.php';
            }, 1500);
        } else {
            // Display error modal
            document.getElementById('errorMessage').textContent = result.message;
            showErrorModal();
        }
    } catch (error) {
        // Display error modal in case of an exception
        document.getElementById('errorMessage').textContent = 'Username already taken! Please try again.';
        showErrorModal();
    } finally {
        document.getElementById('confirmationModal').style.display = 'none';
    }

    addButton.disabled = false;
    addButton.innerHTML = 'Add';
});

// Function to hide a modal
function hideModal(modal) {
    modal.style.display = 'none';
}

// Function to show and auto-close the error modal
function showErrorModal() {
    const errorModal = document.getElementById('errorModal');
    errorModal.style.display = 'flex'; // Show the modal

    // Close modal automatically after 2 seconds
    setTimeout(function () {
        hideModal(errorModal);
    }, 2000);
}

// Close error modal on clicking the close button
document.getElementById('closeErrorModal').addEventListener('click', function () {
    hideModal(document.getElementById('errorModal'));
});

// Close error modal on clicking outside the modal
window.addEventListener('click', function (event) {
    const errorModal = document.getElementById('errorModal');
    if (event.target === errorModal) {
        hideModal(errorModal);
    }
});

// Success modal close button
document.getElementById('closeSuccessModal').addEventListener('click', function () {
    document.getElementById('successModal').style.display = 'none';
});

// Close the success modal and reset the form
document.getElementById('closeSuccessBtn').addEventListener('click', function () {
    document.getElementById('successModal').style.display = 'none';
    document.getElementById('addStaffForm').reset(); // Reset form if needed
});
</script>


<script src="/brew+flex/js/addstaff.js"></script>
</body>
</html>
