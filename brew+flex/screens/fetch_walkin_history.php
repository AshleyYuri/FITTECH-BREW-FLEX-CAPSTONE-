<?php
require_once 'db_connection.php';

// Get the walk-in ID from the URL parameter
$walkinId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if walk-in ID is valid
if ($walkinId <= 0) {
    echo "<tr><td colspan='7' style='text-align: center;'>Invalid walk-in ID provided.</td></tr>";
    exit;
}

// Query to fetch combined walk-in history
$query = "
    SELECT 
        w.id AS walkin_id,
        CONCAT(w.name, ' ', w.lastname) AS fullname,
        w.contact_number,
        w.join_date AS join_date,
        w.walkin_type AS walkin_type,
        w.amount AS amount,
        w.gender AS gender
    FROM walkins AS w
    WHERE w.id = ?
    
    UNION ALL
    
    SELECT 
        l.id AS walkin_id,
        CONCAT(w.name, ' ', w.lastname) AS fullname,
        w.contact_number,
        l.join_date AS join_date,
        l.walkin_type AS walkin_type,
        l.amount AS amount,
        l.gender AS gender
    FROM walkins_logs AS l
    INNER JOIN walkins AS w ON w.id = l.id
    WHERE l.id = ?
    ORDER BY join_date DESC
";

// Query to calculate totals
$totalsQuery = "
    SELECT 
        COUNT(*) AS total_walkins,
        SUM(amount) AS total_amount
    FROM (
        SELECT amount FROM walkins WHERE id = ?
        UNION ALL
        SELECT amount FROM walkins_logs WHERE id = ?
    ) AS combined
";

// Execute walk-in history query
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $walkinId, $walkinId);
$stmt->execute();
$result = $stmt->get_result();

// Execute totals query
$totalsStmt = $conn->prepare($totalsQuery);
$totalsStmt->bind_param("ii", $walkinId, $walkinId);
$totalsStmt->execute();
$totalsResult = $totalsStmt->get_result();
$totals = $totalsResult->fetch_assoc();

$totalWalkins = $totals['total_walkins'] ?? 0;
$totalAmount = $totals['total_amount'] ?? 0;

// Output the table
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['walkin_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['gender']) . "</td>";
        echo "<td>" . htmlspecialchars(date("F j, Y", strtotime($row['join_date']))) . "</td>";
        echo "<td>" . htmlspecialchars($row['walkin_type']) . "</td>";
        echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7' style='text-align: center;'>No walk-in history available for this ID.</td></tr>";
}

// Close connections
$stmt->close();
$totalsStmt->close();
$conn->close();
?>
