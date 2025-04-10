<?php
require_once 'db_connection.php';

header('Content-Type: application/json');

// Check if member_id is provided
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : null;
if (!$member_id) {
    echo json_encode(['error' => 'No member ID provided']);
    exit;
}

// Query to fetch combined data from pos_logs and transaction_logs
$combined_logs_query = "
    SELECT 
        pl.member_id, 
        CONCAT(m.first_name, ' ', m.last_name) AS full_name,
        pl.date AS transaction_date, 
        pl.total_amount AS amount,
        'Gym Goods' AS transaction_type
    FROM 
        pos_logs pl
    LEFT JOIN 
        members m 
    ON 
        pl.member_id = m.member_id
    WHERE 
        pl.member_id = ?

    UNION ALL

    SELECT 
        tl.member_id,
        CONCAT(m.first_name, ' ', m.last_name) AS full_name,
        tl.payment_date AS transaction_date,
        tl.payment_amount AS amount,
        tl.transaction_type
    FROM 
        transaction_logs tl
    LEFT JOIN 
        members m 
    ON 
        tl.member_id = m.member_id
    WHERE 
        tl.member_id = ?
    ORDER BY 
        transaction_date DESC
";

$stmt = $conn->prepare($combined_logs_query);
if (!$stmt) {
    echo json_encode(['error' => 'Failed to prepare the query']);
    exit;
}
$stmt->bind_param("ii", $member_id, $member_id);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($logs);
