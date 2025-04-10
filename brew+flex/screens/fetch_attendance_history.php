<?php
require_once 'db_connection.php';

// Retrieve parameters
$attendance_id = isset($_GET['attendance_id']) ? intval($_GET['attendance_id']) : 0;

if ($attendance_id > 0) {
    // Query for attendance logs
    $query_logs = "
        SELECT member_id, first_name, last_name, check_in_date
        FROM attendance_logs
        WHERE attendance_id = ?
    ";
    $stmt_logs = $conn->prepare($query_logs);
    $stmt_logs->bind_param("i", $attendance_id);
    $stmt_logs->execute();
    $result_logs = $stmt_logs->get_result();

    if ($result_logs->num_rows > 0) {
        while ($attendance = $result_logs->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($attendance['member_id']) . "</td>";
            echo "<td>" . htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars(date("F j, Y g:i a", strtotime($attendance['check_in_date']))) . "</td>";
            echo "</tr>";
        }
    }

    // Query for current attendance
    $query_attendance = "
        SELECT member_id, first_name, last_name, check_in_date
        FROM attendance
        WHERE attendance_id = ?
    ";
    $stmt_attendance = $conn->prepare($query_attendance);
    $stmt_attendance->bind_param("i", $attendance_id);
    $stmt_attendance->execute();
    $result_attendance = $stmt_attendance->get_result();

    if ($result_attendance->num_rows > 0) {
        while ($attendance = $result_attendance->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($attendance['member_id']) . "</td>";
            echo "<td>" . htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars(date("F j, Y g:i a", strtotime($attendance['check_in_date']))) . "</td>";
            echo "</tr>";
        }
    }

    // If no records found
    if ($result_logs->num_rows === 0 && $result_attendance->num_rows === 0) {
        echo "<tr><td colspan='3' style='text-align: center;'>No attendance records found.</td></tr>";
    }
} else {
    echo "<tr><td colspan='3' style='text-align: center;'>Invalid attendance ID.</td></tr>";
}
?>
