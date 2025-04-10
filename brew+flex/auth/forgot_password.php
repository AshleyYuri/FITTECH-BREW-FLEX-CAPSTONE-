<?php
session_start();

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($data, $_POST["username"]);
    $contact_no = mysqli_real_escape_string($data, $_POST["contact_no"]);
    $email = mysqli_real_escape_string($data, $_POST["email"]);

    // Check if the username, contact_no, and email match a record
    $sql = "SELECT * FROM users WHERE username = ? AND contact_no = ? AND email = ?";
    $stmt = mysqli_prepare($data, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $username, $contact_no, $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) == 1) {
            // If details are valid, redirect to reset password page
            $_SESSION["reset_username"] = $username;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "No user found with the provided details.";
        }

        mysqli_stmt_close($stmt);
    } else {
        $error = "An error occurred. Please try again.";
    }
}

mysqli_close($data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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

        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }

        .success-message {
            color: green;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Forgot Password <i class="fas fa-question-circle"></i></h2>
        <form action="#" method="POST">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="contact_no">Contact Number</label>
                <input type="text" id="contact_no" name="contact_no" required>
            </div>
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit">Submit</button>
        </form>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error); ?></p>
        <?php elseif (!empty($success)): ?>
            <p class="success-message"><?= htmlspecialchars($success); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>