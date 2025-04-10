<?php
session_start();

// Check if the user is already logged in, if yes, redirect them to the dashboard
if (isset($_SESSION["username"])) {
    header("Location: /brew+flex/screens/dashboard.php");
    exit();
}

// Database connection details
$host = "localhost";
$user = "root";
$password = "";
$db = "brew+flex";

// Establish connection to the database
$data = mysqli_connect($host, $user, $password, $db);

// Check connection
if ($data === false) {
    die("Connection error: " . mysqli_connect_error());
}

$error = ""; // Initialize error variable
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($data, $_POST["username"]);
    $password = $_POST["password"]; // Do not escape the password

    // Use prepared statements to prevent SQL injection
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($data, $sql);

    if ($stmt) { // Check if statement was prepared successfully
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);

            // Use password_verify to compare entered password with the hashed password in the database
            if (password_verify($password, $row["password"])) {
                // Set session variables upon successful login
                $_SESSION["username"] = $username;
                $_SESSION["usertype"] = $row["usertype"]; // Set the user type in session

                // Handle "Remember Me" functionality
                if (isset($_POST['remember_me'])) {
                    setcookie('username', $username, time() + (86400 * 7), "/"); // Cookie expires in 7 days
                } else {
                    setcookie('username', '', time() - 3600, "/");
                }

                // Redirect to the dashboard
                header("location:/brew+flex/screens/dashboard.php");
                exit();
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "Invalid username. Please try again.";
        }

        mysqli_stmt_close($stmt);
    } else {
        // Handle SQL preparation error
        $error = "An error occurred while processing your request. Please try again.";
    }
}

mysqli_close($data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="/brew+flex/css/login2.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script>
        // Display any error messages as an alert upon page load
        window.onload = function() {
            <?php if (!empty($error)): ?>
                alert("<?php echo $error; ?>");
            <?php endif; ?>
        };
    </script>

   
<style>

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

.header-title {
text-align: center;
font-size: 32px;
margin-bottom: 20px;
font-weight: bold;
color: #03045e;
background: linear-gradient(90deg, #0077b6, #00bfff);
-webkit-background-clip: text; /* WebKit-specific */
    background-clip: text; /* Optional fallback */
color: transparent;
}

.login-container {
display: flex;
background: rgba(255, 255, 255, 0.9);
padding: 40px;
border-radius: 15px;
box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
max-width: 800px;
width: 90%;
gap: 20px;
}

.login-form {
width: 60%;
}

.login-form h2 {
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

input[type="text"],
input[type="password"] {
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

input[type="text"]:focus,
input[type="password"]:focus {
border-color: #0077b6;
background: #ffffff;
}

.forgot-password {
text-align: center;
margin-top: 10px;
}

.forgot-password a {
text-decoration: none;
color: #007bff;
font-size: 14px;
transition: color 0.3s;
}

.forgot-password a:hover {
color: #0056b3;
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

.logo {
width: 40%;
display: flex;
justify-content: center;
align-items: center;
}

.logo img {
max-width: 100%;
border-radius: 10px;
}
/* Modal Styling */
/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.4); /* Light, semi-transparent overlay */
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
    backdrop-filter: blur(2px); /* Light blur for subtle focus effect */
}

.modal-content {
    background: linear-gradient(135deg, #f0f9ff, #dbe7ff, #a8d8ff); /* Softer pastel colors */
    color: #333; /* Darker text for higher contrast */
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); /* Subtle shadow */
}

.modal-content h3 {
    font-size: 24px;
    margin-bottom: 15px;
    font-weight: bold;
    color: #00509e; /* Single readable color for better contrast */
}

.modal-content p {
    font-size: 16px;
    margin-bottom: 20px;
    color: #333; /* Standard readable text */
}

.modal-button {
    display: inline-block;
    padding: 10px 20px;
    font-size: 16px;
    font-weight: bold;
    color: #fff;
    background: linear-gradient(90deg, #00b4d8, #0077b6); /* Softer button gradient */
    border: none;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); /* Subtle shadow */
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
    <form action="#" method="POST">
        <div class="login-container">
            <div class="login-form">
                <h1 class="header-title">BREW + FLEX GYM</h1>
                <h2>Sign-in <i class="fas fa-key"></i></h2>
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($_COOKIE['username'] ?? ''); ?>" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember_me" <?= isset($_COOKIE['username']) ? 'checked' : ''; ?>> Remember me
                    </label>
                </div>
                <p class="forgot-password">
                    <a href="/brew+flex/auth/forgot_password.php">Forgot your password?</a>
                </p>
                <button type="submit">Login  <i class="fas fa-lock"></i></button>
            </div>
            <div class="logo">
                <img src="/brew+flex/assets/brewlogo1.png" alt="Brew + Flex Gym Logo">
            </div>
        </div>
    </form>
 <!-- Modal for Errors -->
 <!-- Modal for Errors -->
 <div id="errorModal" class="modal" style="display: <?= !empty($error) ? 'flex' : 'none'; ?>;">
    <div class="modal-content">
        <h3>Authentication Failed</h3>
        <p><?= htmlspecialchars($error); ?></p>
        <button class="modal-button" onclick="closeModal()">Close</button>
    </div>
</div>


    <script>
    window.onload = function() {
    const error = "<?= addslashes($error); ?>";
    if (error) {
        document.getElementById("errorModal").style.display = "flex";
    }
};

function closeModal() {
    document.getElementById("errorModal").style.display = "none";
}

    </script>
</body>
</html>