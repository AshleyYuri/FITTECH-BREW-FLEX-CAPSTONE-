<?php
session_start();

$host = "localhost";
$user = "root";
$password = "";
$db = "brew+flex";

$data = mysqli_connect($host, $user, $password, $db);

if ($data === false) {
    die("Connection error: " . mysqli_connect_error());
}

$message = "";

// Ensure email is set in session
if (!isset($_SESSION['email'])) {
    header("Location: forgot_password.php"); // Redirect to forgot password if email is not set
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['email']; // Get email from session
    $otp_code = mysqli_real_escape_string($data, $_POST['otp_code']);

    $sql = "SELECT otp_code, otp_expiration FROM users WHERE email = ?";
    $stmt = mysqli_prepare($data, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['otp_code'] == $otp_code && strtotime($row['otp_expiration']) > time()) {
            // OTP is valid
            $_SESSION['verified_email'] = $email; // Store verified email in session
            header("Location: reset_password.php"); // Redirect to reset password page
            exit();
        } else {
            $message = "Invalid OTP or it has expired.";
        }
    } else {
        $message = "An error occurred. Please try again.";
    }

    mysqli_stmt_close($stmt);
}
mysqli_close($data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="/brew+flex/css/login2.css">
    <script>
        // Display any error messages as an alert upon page load
        window.onload = function() {
            <?php if (!empty($message)): ?>
                alert("<?php echo $message; ?>");
            <?php endif; ?>
        };
    </script>
</head>
<body>
    <form action="#" method="POST">
        <div class="login-container">
            <div class="login-form">
                <h1 class="header-title">BREW + FLEX GYM</h1>
                <h2>Verify OTP</h2>
                <div>
                    <label>OTP</label>
                    <input type="text" name="otp_code" required>
                </div>
                <button type="submit">Verify OTP</button>
                <p style="text-align: center; margin-top: 10px;">
                    <a href="/brew+flex/auth/login.php" style="text-decoration: none; color: #00bfff;">Back to Login</a>
                </p>
            </div>
            <div class="logo">
                <img src="/brew+flex/assets/brewlogo1.png" alt="Brew + Flex Gym Logo">
            </div>
        </div>
    </form>
</body>
</html>