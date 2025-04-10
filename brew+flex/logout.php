<?php
session_start();
session_unset(); // Remove all session variables
session_destroy(); // Destroy the session

header("Location: /brew+flex/screens/index.php"); // Redirect to index.php after logout
exit;
?>