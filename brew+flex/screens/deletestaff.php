<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

// Check if staff_id is set in the URL
if (isset($_GET['staff_id'])) {
    $staff_id = $_GET['staff_id'];

    // Prepare the delete statement
    $query = "DELETE FROM users WHERE user_id = ? AND usertype = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    
    // Execute the delete query
    if ($stmt->execute()) {
        // Redirect back to manage staff page with a success message
        header("Location: managestaff.php?msg=Staff member removed successfully");
        exit;
    } else {
        echo "Error: Unable to delete staff member.";
    }
} else {
    echo "Invalid staff ID.";
}
?>
