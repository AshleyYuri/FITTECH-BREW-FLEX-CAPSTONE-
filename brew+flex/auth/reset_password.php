<?php
session_start();

// Redirect if no username is set
if (!isset($_SESSION["reset_username"])) {
    header("Location: forgot_password.php");
    exit();
}

// Database connection details
$host = "localhost";
$user = "root";
$password = "";
$db = "brew+flex";

$data = mysqli_connect($host, $user, $password, $db);

if ($data === false) {
    die("Connection error: " . mysqli_connect_error());
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    if ($new_password === $confirm_password) {
        if (strlen($new_password) < 8 || !preg_match("/[A-Z]/", $new_password) || !preg_match("/\d/", $new_password)) {
            $error = "Password must be at least 8 characters long, contain an uppercase letter, and a number.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $username = $_SESSION["reset_username"];

            $sql = "UPDATE users SET password = ? WHERE username = ?";
            $stmt = mysqli_prepare($data, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $username);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Password successfully reset.";
                    unset($_SESSION["reset_username"]);
                } else {
                    $error = "Failed to reset password.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        $error = "Passwords do not match.";
    }
}

mysqli_close($data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        /* Use the consistent styles from your login page */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #caf0f8, #90e0ef, #0077b6);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-size: 400% 400%;
            animation: gradient-animation 15s ease infinite;
        }

        @keyframes gradient-animation {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        .form-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }

        h2 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 15px;
            color: #03045e;
        }

        .input-group {
            margin-bottom: 20px;
        }

        label {
            font-size: 14px;
            color: #023e8a;
            margin-bottom: 5px;
            display: block;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border: 2px solid transparent;
            border-radius: 8px;
            outline: none;
            background: #f1f1f1;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        input:focus {
            border-color: #0077b6;
            background: #ffffff;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #00bfff, #0077b6);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        button:hover {
            background: linear-gradient(90deg, #0077b6, #00bfff);
            transform: scale(1.05);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background: linear-gradient(135deg, #f0f9ff, #dbe7ff, #a8d8ff);
            color: #333;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-content h3 {
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: bold;
            color: #00509e;
        }

        .modal-content p {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
        }

        .modal-button {
            padding: 10px 20px;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(90deg, #00b4d8, #0077b6);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transition: background 0.3s, transform 0.2s;
        }

        .modal-button:hover {
            background: linear-gradient(90deg, #0077b6, #00b4d8);
            transform: scale(1.05);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Reset Password</h2>
        <form action="#" method="POST">
            <div class="input-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Reset Password  <i class="fas fa-sync"></i> </button>
        </form>
        <?php if (!empty($success)): ?>
            <!-- Success Modal -->
            <div id="successModal" class="modal" style="display: flex;">
                <div class="modal-content">
                    <h3>Password Reset Successful <i class="fas fa-unlock"></i></h3>
                    <p>You can now log in with your new password.</p>
                    <button class="modal-button" onclick="redirectToLogin()">Go to Login</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <!-- Error Modal -->
            <div id="errorModal" class="modal" style="display: flex;">
                <div class="modal-content">
                    <h3>Error <i class="fas fa-exclamation-triangle"></i></h3>
                    <p><?= htmlspecialchars($error); ?></p>
                    <button class="modal-button" onclick="closeErrorModal()">Close</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (!empty($success)): ?>
                // Show the success modal if reset was successful
                document.getElementById('successModal').style.display = 'flex';
            <?php elseif (!empty($error)): ?>
                // Show the error modal if there was a validation error
                document.getElementById('errorModal').style.display = 'flex';
            <?php endif; ?>
        });

        function redirectToLogin() {
            window.location.href = '/brew+flex/auth/login.php'; // Adjust the login page path as needed
        }

        function closeErrorModal() {
            document.getElementById('errorModal').style.display = 'none';
        }
    </script>
</body>

</html>