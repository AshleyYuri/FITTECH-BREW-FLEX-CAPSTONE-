<?php
session_start();
require_once 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];

// Fetch user profile info
$query = "SELECT username, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ss", $username, $usertype);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle filters
$date_filter = $_GET['date_filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$search_query = trim($_GET['search_query'] ?? '');

// Define pagination variables
$limit = 20; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; // Current page
$offset = ($page - 1) * $limit;

// Build SQL conditions for filters
$date_condition_transaction_logs = "1=1";
$date_condition_pos_logs = "1=1";
$date_condition_walkins = "1=1";
$date_condition_walkins_logs = "1=1";

switch ($date_filter) {
    case 'today':
        $date_condition_transaction_logs = "DATE(payment_date) = CURDATE()";
        $date_condition_pos_logs = "DATE(date) = CURDATE()";
        $date_condition_walkins = "DATE(join_date) = CURDATE()";
        $date_condition_walkins_logs = "DATE(join_date) = CURDATE()";
        break;
    case 'yesterday':
        $date_condition_transaction_logs = "DATE(payment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_condition_pos_logs = "DATE(date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_condition_walkins = "DATE(join_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $date_condition_walkins_logs = "DATE(join_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case '1_week':
        $date_condition_transaction_logs = "DATE(payment_date) >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        $date_condition_pos_logs = "DATE(date) >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        $date_condition_walkins = "DATE(join_date) >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        $date_condition_walkins_logs = "DATE(join_date) >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
    case '1_month':
        $date_condition_transaction_logs = "DATE(payment_date) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $date_condition_pos_logs = "DATE(date) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $date_condition_walkins = "DATE(join_date) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $date_condition_walkins_logs = "DATE(join_date) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'date_range':
        if ($start_date && $end_date) {
            $start_date_escaped = $conn->real_escape_string($start_date);
            $end_date_escaped = $conn->real_escape_string($end_date);
            $date_condition_transaction_logs = "DATE(payment_date) BETWEEN '$start_date_escaped' AND '$end_date_escaped'";
            $date_condition_pos_logs = "DATE(date) BETWEEN '$start_date_escaped' AND '$end_date_escaped'";
            $date_condition_walkins = "DATE(join_date) BETWEEN '$start_date_escaped' AND '$end_date_escaped'";
            $date_condition_walkins_logs = "DATE(join_date) BETWEEN '$start_date_escaped' AND '$end_date_escaped'";
        }
        break;
}

// Search condition for names
$search_condition = "1=1";
if (!empty($search_query)) {
    $search_query_escaped = $conn->real_escape_string($search_query);
    $search_condition = "CONCAT(m.first_name, ' ', m.last_name) LIKE '%$search_query_escaped%' 
        OR CONCAT(w.name, ' ', w.lastname) LIKE '%$search_query_escaped%' 
        OR CONCAT(wl.name, ' ', wl.lastname) LIKE '%$search_query_escaped%'";
}
$count_query = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT t.report_id
        FROM transaction_logs t
        JOIN members m ON t.member_id = m.member_id
        WHERE ($date_condition_transaction_logs)
        AND ($search_condition)

        UNION ALL

        SELECT p.log_id
        FROM pos_logs p
        LEFT JOIN members m ON p.member_id = m.member_id
        LEFT JOIN walkins w ON p.id = w.id
        LEFT JOIN coaches c ON p.coach_id = c.coach_id
        WHERE ($date_condition_pos_logs)
        AND ($search_condition)

        UNION ALL

        SELECT w.id
        FROM walkins w
        WHERE ($date_condition_walkins)
        AND ($search_condition)

        UNION ALL

        SELECT wl.id
        FROM walkins_logs wl
        WHERE ($date_condition_walkins_logs)
        AND ($search_condition)
    ) AS subquery
";

$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'] ?? 0; // Fallback to 0 if no records found
$total_pages = ceil($total_items / $limit);

// Fetch logs with filters and pagination
$query = "
    SELECT 
        t.report_id AS log_id,
        t.member_id,
        CONCAT(m.first_name, ' ', m.last_name) AS full_name,
        t.transaction_type,
        t.payment_date,
        t.payment_amount,
        t.customer_type,
        t.plan_type
    FROM transaction_logs t
    JOIN members m ON t.member_id = m.member_id
    WHERE ($date_condition_transaction_logs)
    AND ($search_condition)

    UNION ALL

    SELECT 
        p.log_id,
        COALESCE(p.member_id, p.id, p.coach_id) AS member_id,
        COALESCE(CONCAT(m.first_name, ' ', m.last_name), CONCAT(w.name, ' ', w.lastname), CONCAT(c.first_name, ' ', c.last_name)) AS full_name,
        'Gym Goods' AS transaction_type,
        p.date AS payment_date,
        p.total_amount AS payment_amount,
        CASE 
            WHEN p.member_id IS NOT NULL THEN 'Member'
            WHEN p.id IS NOT NULL THEN 'Walk-in'
            WHEN p.coach_id IS NOT NULL THEN 'Coach'
            ELSE 'Unknown'
        END AS customer_type,
        NULL AS plan_type
    FROM pos_logs p
    LEFT JOIN members m ON p.member_id = m.member_id
    LEFT JOIN walkins w ON p.id = w.id
    LEFT JOIN coaches c ON p.coach_id = c.coach_id
    WHERE ($date_condition_pos_logs)
    AND ($search_condition)

    UNION ALL

    SELECT 
        w.id AS log_id,
        w.id AS member_id,
        CONCAT(w.name, ' ', w.lastname) AS full_name,
        'Walkin Payment' AS transaction_type,
        w.join_date AS payment_date,
        w.amount AS payment_amount,
        'Walkin' AS customer_type,
        NULL AS plan_type
    FROM walkins w
    WHERE ($date_condition_walkins)
    AND ($search_condition)

    UNION ALL

    SELECT 
        wl.id AS log_id,
        wl.id AS member_id,
        CONCAT(wl.name, ' ', wl.lastname) AS full_name,
        'Walkin Payment' AS transaction_type,
        wl.join_date AS payment_date,
        wl.amount AS payment_amount,
        'Walkin' AS customer_type,
        NULL AS plan_type
    FROM walkins_logs wl
    WHERE ($date_condition_walkins_logs)
    AND ($search_condition)

    ORDER BY payment_date DESC
    LIMIT $limit OFFSET $offset
";


$result = $conn->query($query);
if (!$result) {
    die("Query failed: " . $conn->error);
}
$total_revenue = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Reports - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.3.0/css/flag-icon.min.css">
    <link rel="stylesheet" href="/brew+flex/css/reports.css">
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="admin-info">
                <img src="<?php echo htmlspecialchars($user_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Admin Avatar">
                <h3><?php echo htmlspecialchars($user_info['username']); ?></h3>
                <p><?php echo htmlspecialchars($user_info['email']); ?></p>
            </div>
            <nav class="menu">
                <ul>
                    <li ><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php if ($usertype === 'admin'): ?>
                        <li><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                        <li><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <?php endif; ?>
                    <li><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
                    <li><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                    <li ><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                    <li ><a href="payment.php"><i class="fas fa-credit-card"></i> Payment</a></li>
                    <li ><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                    <li ><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
                    <li><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
                    <li ><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
                    <li class="active"><a href="reports.php"><i class="fas fa-file-alt"></i> Transaction Logs</a></li>
                    <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                </ul>
 
                <div class="logout">
    <a href="#" onclick="showLogoutModal(); return true;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
<div class="modal-logouts" id="logoutModal">
    <div class="logouts-content">
        <i class="fas fa-sign-out-alt modal-icon"></i>
        <h3>Are you sure you want to log out?</h3>
        <div class="modal-buttons">
            <button class="confirms-buttons" onclick="handleLogout()">Yes, Log Out</button>
            <button class="cancels-buttons" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>
</aside>
</nav>
        <div class="content-wrapper">
    <main class="main-content">
        <div class="header">
            <h2>Transaction Logs <i class="fas fa-calendar-check"></i></h2>
        </div>
        <!-- Sort Options -->
        <div class="filter-container">
            <form method="GET" action="">
                <label for="date-filter">Filter by Date:</label>
                <select id="date-filter" name="date_filter" onchange="this.form.submit()">
                    <option value="all" <?= isset($_GET['date_filter']) && $_GET['date_filter'] === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="today" <?= isset($_GET['date_filter']) && $_GET['date_filter'] === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= isset($_GET['date_filter']) && $_GET['date_filter'] === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="1_week" <?= isset($_GET['date_filter']) && $_GET['date_filter'] === '1_week' ? 'selected' : '' ?>>Last 1 Week</option>
                    <option value="1_month" <?= isset($_GET['date_filter']) && $_GET['date_filter'] === '1_month' ? 'selected' : '' ?>>Last 1 Month</option>
                    <option value="date_range" <?= isset($_GET['date_filter']) && $_GET['date_filter'] === 'date_range' ? 'selected' : '' ?>>Custom Range</option>
                </select>
                <div id="date-range-fields" class="custom-date-range" style="display: <?= isset($_GET['date_filter']) && $_GET['date_filter'] === 'date_range' ? 'flex' : 'none' ?>;">
                    <label for="start-date"></label>
                    <input type="date" id="start-date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                    <label for="end-date"></label>
                    <input type="date" id="end-date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                    <button type="submit" class="filter-btn">Apply Filter <i class="fas fa-filter"></i></button>

                </div>
            </form>
            <div class="search-container">
                <label for="search-bar"></label>
                <input type="text" id="search-bar" placeholder="Type a name..." onkeyup="searchCustomer()">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </div>
        
       <!-- Reports Table -->
<div class="table-wrapper">
    <div class="table-container">
        <div class="scrollable-container">
            <table>
                <thead>
                    <tr>
                        
                        <th>Full Name</th>
                        <th>Transaction Type</th>
                        <th>Customer Type</th>
                        <th>Payment Date</th>
                      
                        <th>Amount</th>
                
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                       
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['transaction_type']) ?></td>
                        <td><?= htmlspecialchars($row['customer_type']) ?></td>
                        <td><?= htmlspecialchars((new DateTime($row['payment_date']))->format('F j, Y')) ?></td>


                        <td><?= number_format($row['payment_amount'], 2) ?></td>
                   
                    </tr>
                    <?php 
                        $total_revenue += $row['payment_amount']; // Accumulate total revenue
                    ?>
                    <?php endwhile; ?>

                    
                </tbody>
            </table>
        </div>
    </div>
    <!-- No Results Message -->
    <div id="no-results-message" style="display: none; color: #000; font-weight: bold;">
        No results found for your search.
    </div>
</div>
<style>
    .pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.pagination a {
    margin: 0 5px;
    padding: 7px 14px;
    border: 1px solid #ccc;
    text-decoration: none;
    color: #333;
    border-radius: 5px;
}

.pagination a:hover {
    background-color: #009acd;
}

.pagination a.active {
    background-color: #00bfff;
    color: white;
}

</style>
<?php if ($total_items > 0): ?>
<div class="pagination">
    <!-- First Page -->
    <a href="?page=1&date_filter=<?= htmlspecialchars($date_filter) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&search_query=<?= urlencode($search_query) ?>" class="<?= $page == 1 ? 'disabled' : '' ?>">First</a>

    <!-- Previous Page -->
    <a href="?page=<?= max(1, $page - 1) ?>&date_filter=<?= htmlspecialchars($date_filter) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&search_query=<?= urlencode($search_query) ?>" class="<?= $page == 1 ? 'disabled' : '' ?>">Previous</a>

    <!-- Page Numbers -->
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&date_filter=<?= htmlspecialchars($date_filter) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&search_query=<?= urlencode($search_query) ?>" class="<?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <!-- Next Page -->
    <a href="?page=<?= min($total_pages, $page + 1) ?>&date_filter=<?= htmlspecialchars($date_filter) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&search_query=<?= urlencode($search_query) ?>" class="<?= $page == $total_pages ? 'disabled' : '' ?>">Next</a>

    <!-- Last Page -->
    <a href="?page=<?= $total_pages ?>&date_filter=<?= htmlspecialchars($date_filter) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&search_query=<?= urlencode($search_query) ?>" class="<?= $page == $total_pages ? 'disabled' : '' ?>">Last</a>
</div>
<?php endif; ?>

<!-- Display Total Revenue --><!-- Display Total Revenue -->
<div class="total-revenue">
    <strong>Total Revenue:</strong> <span>₱<?php echo number_format($total_revenue, 2); ?></span>
</div>



<script>
        function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function handleLogout() {
    alert("You have been logged out.");
    window.location.href = "/brew+flex/logout.php";
}

function searchCustomer() {
    // Get the input value
    var input = document.getElementById('search-bar');
    var filter = input.value.toLowerCase();

    // Get the table and rows
    var table = document.querySelector('table');
    var rows = table.getElementsByTagName('tr');
    var noResultsMessage = document.getElementById('no-results-message');
    var totalRevenueContainer = document.querySelector('.total-revenue span'); // Target only the span for numeric value
    var hasResults = false;
    var totalRevenue = 0; // Initialize total revenue

    // Loop through all rows and hide those that don't match the search
    for (var i = 1; i < rows.length; i++) { // Start at 1 to skip the header row
        var row = rows[i];
        var cells = row.getElementsByTagName('td');
        var matchFound = false;

        // Loop through each cell in the row to check if it matches the search filter
        for (var j = 0; j < cells.length; j++) {
            var cell = cells[j];
            var cellText = cell.textContent || cell.innerText;

            // If the cell text matches the filter, highlight it
            if (cellText.toLowerCase().indexOf(filter) > -1) {
                var regex = new RegExp('(' + filter + ')', 'gi');
                cell.innerHTML = cellText.replace(regex, '<span class="highlight">$1</span>');
                matchFound = true;
            } else {
                cell.innerHTML = cellText; // Reset if there's no match in this cell
            }
        }

        // Show or hide the row based on whether a match was found
        if (matchFound) {
            row.style.display = ''; // Show row
            hasResults = true;

            // Add the payment amount to the total revenue
            var paymentAmountCell = row.querySelectorAll('td')[6]; // Assuming the 7th column contains the payment amount
            if (paymentAmountCell) {
                var paymentAmount = parseFloat(paymentAmountCell.textContent.replace(/[^0-9.-]+/g, '')) || 0;
                totalRevenue += paymentAmount;
            }
        } else {
            row.style.display = 'none'; // Hide row
        }
    }

    // Show "No results found" message if no rows match
    if (!hasResults) {
        noResultsMessage.style.display = 'block';
    } else {
        noResultsMessage.style.display = 'none';
    }

    // Update the total revenue container
    totalRevenueContainer.textContent = `₱${totalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}


</script>


</body>
</html>
<?php
$conn->close();
?>

