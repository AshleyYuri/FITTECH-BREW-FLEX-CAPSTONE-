<?php
session_start();
require_once 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];

// Fetch user profile info
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
date_default_timezone_set('Asia/Manila');



date_default_timezone_set('Asia/Manila'); // Set timezone to Philippines

$currentDateTime = date('Y-m-d\TH:i'); // Format for datetime-local input



// Generate a random code for the member
function generateRandomCode($length = 10)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize and prepare form data
    $first_name = ucfirst(strtolower(trim($_POST['first_name'])));
    $last_name = ucfirst(strtolower(trim($_POST['last_name'])));
    $gender = strtoupper(trim($_POST['gender'] ?? ''));
    $birthday = trim($_POST['birthday'] ?? '');
    $email = strtolower(trim($_POST['email']));
    $contact_no = trim($_POST['contact_no']);
    $country = strtoupper(trim($_POST['country']));
    $zipcode = strtoupper(trim($_POST['zipcode']));
    $municipality = strtoupper(trim($_POST['municipality']));
    $city = strtoupper(trim($_POST['city']));
    $date_enrolled = trim($_POST['membership_enrolled_date']);
    $amount = isset($_POST['amount']) ? $_POST['amount'] : 300; // If user edits the amount, use that; otherwise, default to 300
    $profile_picture = "default-profile.png"; // Default profile picture



    // Handle file upload or webcam image
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Ensure the uploads directory exists
    }

    // Check for webcam image
    if (!empty($_POST['webcamImage'])) {
        $data = $_POST['webcamImage'];
        list(, $data) = explode(',', $data); // Remove data:image/png;base64,
        $data = base64_decode($data);
        $filename = uniqid("profile_", true) . ".png";
        $filePath = $uploadDir . $filename;
        if (file_put_contents($filePath, $data)) {
            $profile_picture = $filePath; // Save webcam image path
        }
    }
    // Check for uploaded image
    elseif (!empty($_FILES['profile_picture']['tmp_name'])) {
        $filename = uniqid("profile_", true) . "_" . basename($_FILES['profile_picture']['name']);
        $filePath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
            $profile_picture = $filePath; // Save uploaded image path
        }
    }

    // Calculate expiration date
    try {
        $enrollment_date = new DateTime($date_enrolled);
        $expiration_date = $enrollment_date->modify('+1 year')->format('Y-m-d');
    } catch (Exception $e) {
        die("Invalid date format: " . $e->getMessage());
    }

    // Insert member into the database
    $query = "INSERT INTO members 
              (first_name, last_name, gender, birthday, email, contact_no, country, zipcode, municipality, city, date_enrolled, expiration_date, amount, profile_picture) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param(
            "ssssssssssssss",
            $first_name,
            $last_name,
            $gender,
            $birthday,
            $email,
            $contact_no,
            $country,
            $zipcode,
            $municipality,
            $city,
            $date_enrolled,
            $expiration_date,
            $amount,
            $profile_picture
        );

        if ($stmt->execute()) {
            $member_id = $stmt->insert_id;

  // Insert into the payments table
  $payment_query = "INSERT INTO payments (member_id, membership_renewal_payment_date, membership_expiration_date, renewal_amount) 
  VALUES (?, ?, ?, ?)";
$payment_stmt = $conn->prepare($payment_query);
if ($payment_stmt) {
$payment_stmt->bind_param(
"issd", 
$member_id, 
$date_enrolled, 
$expiration_date, 
$amount
);

if ($payment_stmt->execute()) {
// Insert transaction log after payment
$transaction_type = "Membership Plan Payment";
$customer_type = "Member";
$plan_type = "Membership Plan";

$insertTransactionQuery = "INSERT INTO transaction_logs 
                   (member_id, transaction_type, payment_date, payment_amount, customer_type, plan_type) 
                   VALUES (?, ?, ?, ?, ?, ?)";
$transaction_stmt = $conn->prepare($insertTransactionQuery);
if ($transaction_stmt) {
$transaction_stmt->bind_param(
"issdss",
$member_id,
$transaction_type,
$date_enrolled, // Use the enrolled date as the payment date
$amount,
$customer_type,
$plan_type
);
$transaction_stmt->execute();
$transaction_stmt->close();
}
} else {
die("Error adding payment: " . $payment_stmt->error);
}
$payment_stmt->close();
}




            // Generate unique code and QR data
            $generated_code = generateRandomCode();
            $qr_data = "ID: $member_id\nName: $first_name $last_name\nCode: $generated_code";

            // Save the generated code in the database
            $update_query = "UPDATE members SET generated_code = ? WHERE member_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $generated_code, $member_id);
            $update_stmt->execute();

            // Generate QR Code URL
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

            // Redirect to success page with QR code
            header("Location: successfullyadded.php?qr_code=" . urlencode($qr_url) . "&member_id=$member_id&name=" . urlencode("$first_name $last_name"));
            exit;
        } else {
            die("Error adding member: " . $stmt->error);
        }
    } else {
        die("Error preparing query: " . $conn->error);
    }
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registration - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.3.0/css/flag-icon.min.css">

    <link rel="stylesheet" href="/brew+flex/css/registration.css">
    <style>
        /* Main Content Styles */
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
                        <li ><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <?php endif; ?>
                    <li class="active"><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
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
            <form id="form1" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <div class="screen active" id="screen1">
                    <div class="form-container">
                        <div class="profile-container">
                            <h3>Add a Member! <i class="fas fa-user-friends"></i></h3>
                        </div>
                        <h4>Personal Details</h4>
                        <div class="form-group">
                            <div>
                                <label for="name">First Name</label>
                                <input type="text" id="name" name="first_name" placeholder="Enter first name" required pattern="^[a-zA-Z\s]+$" title="Only letters and spaces are allowed." minlength="2" maxlength="32">
                            </div>
                            <div>
                                <label for="lastname">Last Name</label>
                                <input type="text" id="lastname" name="last_name" placeholder="Enter last name" required pattern="^[a-zA-Z\s]+$" title="Only letters and spaces are allowed." minlength="2" maxlength="32">
                            </div>
                        </div>
                        <div class="form-group">
                            <div>
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" required>
                                    <option value="" disabled selected>Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
    <label for="birthday">Birthday</label>
    <input type="date" id="birthday" name="birthday" required min="" max="" />
</div>
                        </div>
                        <h4>Contact Information</h4>
                        <div class="form-group">
                            <div>
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="Enter email address" required>
                            </div>
                            <div>
                                <label for="contact">Contact Number</label>
                                <input type="tel" id="contact" name="contact_no" placeholder="Enter contact number" pattern="[0-9]{11}" maxlength="11" required>
                            </div>
                        </div>
                        <h4>Address</h4>
                        <div class="form-group">
                            <div>
                                <label for="country">Country</label>
                                <select id="country" name="country" required>
                                    <option value="" disabled selected>Select your country</option>
                                </select>
                            </div>
                            <div>
                                <label for="zipcode">Zip Code</label>
                                <input type="text" id="zipcode" name="zipcode" placeholder="Enter zip code" pattern="^\d{4}$" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <div>
                                <label for="municipality">Municipality</label>
                                <input type="text" id="municipality" name="municipality" placeholder="Enter municipality" required pattern="^[a-zA-Z\s]+$" title="Only letters and spaces are allowed." minlength="4" maxlength="32">
                            </div>
                            <div>
                                <label for="city">City/Province</label>
                                <input type="text" id="city" name="city" placeholder="Enter city" required pattern="^[a-zA-Z\s]+$" title="Only letters and spaces are allowed." minlength="4" maxlength="32">
                            </div>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="next-btn" onclick="goToNextScreen(1, 2)">Next</button>
                        </div>
                    </div>
                </div>
                <div class="screen" id="screen2">
                    <div class="form-container">
                        <h3>Profile Upload <i class="fas fa-camera"></i></h3>
                        <div class="profile-upload">
                            <img id="profilePicturePreview" src="default-profile.png">
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
                        <div class="form-buttons">
                            <button type="button" class="cancel-btn" onclick="goToPreviousScreen(2, 1)">Previous</button>
                            <button type="button" class="next-btn" onclick="goToNextScreen(2, 3)">Next</button>
                        </div>
                    </div>
                </div>

                <div class="screen" id="screen3">
                    <div class="form-container">
                        <h3>Annual Membership <i class="fas fa-user"></i></h3>
                        <div class="form-group">
                            <div>
                                <label for="MembershipEnrolleddateInput">Membership Enrolled Date</label>
                                <input type="date" id="MembershipEnrolleddateInput" name="membership_enrolled_date" required>
                            </div>
                            <div>
                                <label for="MemberExpiryContainerInput">Membership Expired Date</label>
                                <input type="date" id="MemberExpiryContainerInput" name="membership_expiry_date" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <div>
                                <label for="amount">Amount</label>
                                <span id="amount" class="amount-display">300</span>
                                <input type="hidden" id="hiddenAmount" name="amount" value="300"> <!-- Hidden field for amount -->
                            </div>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="cancel-btn" onclick="goToPreviousScreen(3, 2)">Previous</button>
                            <button type="submit" class="next-btn">Submit</button>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
<!-- Confirmation Modal -->
<div id="confirmationModal" class="modal-confirmation">
    <div class="modal-confirmation-content">
        <i class="fas fa-question-circle modal-icon"></i> <!-- Icon for confirmation -->
        <h3>Are you sure you want to register as a member of Brew+Flex?</h3>
        <div class="modal-buttons">
            <button class="confirmation-btn" id="confirmSubmitBtn">Yes, Register</button>
            <button class="cancels-btn" id="cancelModalBtn">No, Cancel</button>
        </div>
    </div>
</div>


<style>
 /* Confirmation Modal */
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

/* Modal Content */
.modal-confirmation-content {
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

.modal-confirmation h3 {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

/* Modal Buttons */
.modal-buttons {
    margin-top: 20px;
}

.modal-confirmation button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

/* Confirm Button */
.confirmation-btn {
    background-color: #71d4fc; /* Same blue as the icon */
    color: #ffffff;
}

.confirmation-btn:hover {
    background-color: #5bb8d0; /* Slightly darker on hover */
    transform: scale(1.05); /* Slight zoom effect */
}

/* Cancel Button */
.cancels-btn {
    background-color: #ccc;
    color: #333;
}

.cancels-btn:hover {
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
<script>document.addEventListener("DOMContentLoaded", function () {
    const submitBtn = document.querySelector(".next-btn[type='submit']");
    const confirmationModal = document.getElementById("confirmationModal");
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");
    const cancelModalBtn = document.getElementById("cancelModalBtn");
    const form = document.getElementById("form1");

    // Show confirmation modal when clicking submit
    submitBtn.addEventListener("click", function (event) {
        // Validate the form before showing the modal
        if (!form.checkValidity()) {
            form.reportValidity(); // Trigger native HTML5 validation messages
            return; // Exit if the form is invalid
        }
        
        event.preventDefault(); // Prevent the form from submitting immediately
        confirmationModal.style.display = "flex"; // Show the modal
    });

    // If "Yes" is clicked, submit the form
    confirmSubmitBtn.addEventListener("click", function () {
        form.submit(); // Submit the form
    });

    // If "No" is clicked, hide the modal and stay on the registration page
    cancelModalBtn.addEventListener("click", function () {
        confirmationModal.style.display = "none"; // Hide the modal
    });

    // Close the modal if clicked outside of the modal content
    window.addEventListener("click", function (event) {
        if (event.target === confirmationModal) {
            confirmationModal.style.display = "none";
        }
    });
});
// Get today's date
const today = new Date();

// Set the max date to 3 years before today
today.setFullYear(today.getFullYear() - 3);

// Format the max date to YYYY-MM-DD
const maxDate = today.toISOString().split('T')[0];

// Set the max attribute of the input
document.getElementById('birthday').max = maxDate;





</script>
    <script src="/brew+flex/js/registration.js"></script>
</body>
</html>