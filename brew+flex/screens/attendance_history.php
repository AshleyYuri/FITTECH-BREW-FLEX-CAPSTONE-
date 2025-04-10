<?php
require_once 'db_connection.php';

// Retrieve parameters
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

// Check if the member ID is valid
if ($member_id <= 0) {
    echo "<tr><td colspan='3' style='text-align: center;'>Invalid member ID.</td></tr>";
    exit;
}

// Fetch attendance logs and attendance records for the given member
$query = "
    SELECT 
        al.member_id, 
        CONCAT(m.first_name, ' ', m.last_name) AS full_name, 
        al.check_in_date
    FROM 
        attendance_logs al
    INNER JOIN 
        members m ON al.member_id = m.member_id
    WHERE 
        al.member_id = ?
    
    UNION ALL

    SELECT 
        a.member_id, 
        CONCAT(m.first_name, ' ', m.last_name) AS full_name, 
        a.check_in_date
    FROM 
        attendance a
    INNER JOIN 
        members m ON a.member_id = m.member_id
    WHERE 
        a.member_id = ?
    
    ORDER BY 
        check_in_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $member_id, $member_id);
$stmt->execute();
$result = $stmt->get_result();

$total_attendance = $result->num_rows; // Get the total number of attendance records

// Start output
if ($total_attendance > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['member_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars(date("F j, Y g:i a", strtotime($row['check_in_date']))) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3' style='text-align: center;'>No attendance records found for this member.</td></tr>";
}

$stmt->close();
$conn->close();
?>