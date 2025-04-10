<?php
session_start();
require_once 'db_connection.php';

// Redirect to login if the username is not set in the session
if (!isset($_SESSION["username"])) {
    header("location:/brew+flex/auth/login.php");
    exit;
}

// Retrieve session variables
$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];
$user_info = [];
// Fetch profile picture and email address based on the logged-in user
$username = $_SESSION["username"];
$userQuery = "SELECT profile_picture, email FROM users WHERE username = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $username);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();

$user_info = [
    'username' => $username,
    'profile_picture' => $userData['profile_picture'] ?? 'default-profile.png',
    'email' => $userData['email'] ?? 'No Email Provided'
];

// Handle search term
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$isSearchValid = preg_match("/^[a-zA-Z\s]+$/", $searchTerm);

// Handle sorting
$allowedSortColumns = ['id', 'name', 'lastname', 'join_date', 'amount'];
$allowedSortOrders = ['asc', 'desc'];

$sortColumn = isset($_GET['sort_column']) && in_array($_GET['sort_column'], $allowedSortColumns) ? $_GET['sort_column'] : 'id';
$sortBy = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowedSortOrders) ? $_GET['sort_by'] : 'desc';

if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'id'; // Default to 'id'
}
if (!in_array($sortBy, $allowedSortOrders)) {
    $sortBy = 'desc'; // Default to 'desc'
}

// Handle date filter
$dateFilter = $_GET['date_filter'] ?? 'all';
$dateCondition = "";

// Handle custom date range
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

if ($dateFilter == 'custom' && !empty($startDate) && !empty($endDate)) {
    $dateCondition = "AND join_date BETWEEN '$startDate' AND '$endDate'";
} else {
    switch ($dateFilter) {
        case 'today':
            $dateCondition = "AND join_date = CURDATE()";
            break;
        case 'yesterday':
            $dateCondition = "AND join_date = CURDATE() - INTERVAL 1 DAY";
            break;
        case '3days':
            $dateCondition = "AND join_date >= CURDATE() - INTERVAL 3 DAY";
            break;
        case '1week':
            $dateCondition = "AND join_date >= CURDATE() - INTERVAL 1 WEEK";
            break;
        case '1month':
            $dateCondition = "AND join_date >= CURDATE() - INTERVAL 1 MONTH";
            break;
        case 'all':
        default:
            $dateCondition = "";
            break;
    }
}

// Search condition
$searchWildcard = '%' . $searchTerm . '%';

// Pagination setup
$recordsPerPage = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage; // Calculate offset

// Initialize the result variable
$result = null;
$total_results = 0;

// Count the total number of results for pagination
$countSql = "SELECT COUNT(*) AS total FROM walkins WHERE (name LIKE ? OR lastname LIKE ? OR CONCAT(name, ' ', lastname) LIKE ?) $dateCondition";
$stmt = $conn->prepare($countSql);
$stmt->bind_param("sss", $searchWildcard, $searchWildcard, $searchWildcard);
$stmt->execute();
$countResult = $stmt->get_result();
$total_results = $countResult->fetch_assoc()['total'];
$total_pages = ceil($total_results / $recordsPerPage);

// Only proceed if the search term is valid
if ($isSearchValid || $searchTerm == '') {
    // Main query with pagination
    $sql = "SELECT id, CONCAT(name, ' ', lastname) AS fullname, contact_number, join_date, walkin_type, amount, gender
            FROM walkins 
            WHERE (name LIKE ? OR lastname LIKE ? OR CONCAT(name, ' ', lastname) LIKE ?)
            $dateCondition
            ORDER BY $sortColumn $sortBy
            LIMIT ?, ?"; // Apply limit and offset for pagination

    // Prepare the statement
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $searchWildcard, $searchWildcard, $searchWildcard, $offset, $recordsPerPage);
    $stmt->execute();
    $result = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brew & Flex Fitness Gym Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/walkins.css">
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
                    <li class="active"><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
                    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Transaction Logs</a></li>
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
    <div class="main-content">
        <div class="header">
            <h2>Walk-Ins <i class="fas fa-walking"></i></h2>
        </div>
        <div class="action-buttons">
            <button class="add-btn" onclick="location.href='addwalkins.php'">Add Walk-In <i class="fas fa-plus"></i></button>
        </div>
        <div class="header-controls">
            <form method="GET" action="" class="filters">
                <!-- Date Filters -->
                <div class="entity-controls">
                    <label for="date_filter">Filter by Date:</label>
                    <select name="date_filter" id="date_filter" onchange="this.form.submit()">
                        <option value="all" <?php echo ($dateFilter == 'all') ? 'selected' : ''; ?>>All</option>
                        <option value="today" <?php echo ($dateFilter == 'today') ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo ($dateFilter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="3days" <?php echo ($dateFilter == '3days') ? 'selected' : ''; ?>>Last 3 Days</option>
                        <option value="1week" <?php echo ($dateFilter == '1week') ? 'selected' : ''; ?>>Last Week</option>
                        <option value="1month" <?php echo ($dateFilter == '1month') ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?php echo ($dateFilter == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>

                <!-- Custom Date Range -->
                <div class="custom-date-range" <?php echo ($dateFilter == 'custom') ? 'style="display:flex;"' : 'style="display:none;"'; ?>>
                    <label for="start_date"></label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                    <label for="end_date"></label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                    <button type="submit" class="apply-btn">Apply Filter</button>
                </div>
            </form>
            
            <!-- Search Bar -->
            <div class="search-container">
                <label for="search-bar"></label>
                <input type="text" id="search-bar" placeholder="Type a name..." onkeyup="searchCustomer()">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </div>
        </div>
        <div class="table-wrapper">
            <div class="table-container">
                <div class="scrollable-table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <a href="?sort_column=id&sort_by=<?php echo ($sortColumn == 'id' && $sortBy == 'desc') ? 'asc' : 'desc'; ?>">
                                        ID <?php if ($sortColumn == 'id'): ?>
                                            <?php echo $sortBy == 'asc' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Full Name</th>
                                <th>Contact Number</th>
                                <th>Gender</th>
                                <th>Join Date</th>
                                <th>Walk-In Type</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        // Initialize total variables
                        $totalAmount = 0;
                        $totalWalkins = 0;

                        // Check if there are results
                        if ($result && $result->num_rows > 0): 
                            while ($row = $result->fetch_assoc()):
                                // Combine name and lastname into a single string
                                $fullname = htmlspecialchars($row['fullname']);
                                // Sum up the total amount and total walk-ins
                                $totalAmount += $row['amount'];
                                $totalWalkins++;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo $fullname; ?></td>
                                <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td><?php echo htmlspecialchars(date("F j, Y", strtotime($row['join_date']))); ?></td>
                                <td><?php echo htmlspecialchars($row['walkin_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['amount']); ?></td>
                                <td>
                                    <button onclick="viewWalkinHistory(<?php echo htmlspecialchars($row['id']); ?>)" class="view-btn">View</button>           
                                    <button onclick="confirmAddWalkin(<?php echo htmlspecialchars($row['id']); ?>)" class="addt-btn"> Add</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No results found for "<?php echo htmlspecialchars($searchTerm);?>"
                                <?php echo ($dateFilter == 'all') ? 'All' : ''; ?>
                                <?php echo ($dateFilter == 'today') ? 'Today' : ''; ?>
                                <?php echo ($dateFilter == 'yesterday') ? 'Yesterday' : ''; ?>
                                <?php echo ($dateFilter == '3days') ? 'Last 3days' : ''; ?>
                                <?php echo ($dateFilter == '1week') ? 'Last Week' : ''; ?>
                                <?php echo ($dateFilter == '1month') ? 'Last Month' : ''; ?>"
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
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

    .pagination .active {
        background-color: #00bfff;
        color: white;
    }

        </style>
            <?php if ($total_results > 0): ?>
    <div class="pagination">
        <!-- First Page Link -->
        <a href="?page=1&filter=<?php echo urlencode($filter); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="first-page">First</a>

        <!-- Previous Page Link -->
        <a href="?page=<?php echo ($page > 1) ? $page - 1 : 1; ?>&filter=<?php echo urlencode($filter); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="prev-page">Previous</a>

        <!-- Page Numbers -->
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="page-number <?php echo ($page == $i) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <!-- Next Page Link -->
        <a href="?page=<?php echo ($page < $total_pages) ? $page + 1 : $total_pages; ?>&filter=<?php echo urlencode($filter); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="next-page">Next</a>

        <!-- Last Page Link -->
        <a href="?page=<?php echo $total_pages; ?>&filter=<?php echo urlencode($filter); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="last-page">Last</a>
    </div>
<?php endif; ?>

            <div id="no-results-message" style="display: none; color: #000; font-weight: bold;">
    No results found for "<span id="search-term"></span>"
</div>


        </div>
        <div class="totals-summary">
    <div class="summary-item">Total Walk-Ins: <strong><?php echo $totalWalkins; ?></strong></div>
    <div class="summary-item">Total Amount: <strong>₱<?php echo number_format($totalAmount, 2); ?></strong></div>
</div>

    </div>
</div>


<div class="modal-walkins" id="walkinModal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h3>Walk-In Details <i class="fas fa-walking"></i></h3>
        
        <!-- Date Range Filter -->
<div id="date-range-filter" class="datefilter">
    <label for="date-range">Filter by Date:</label>
    <select id="date-range" onchange="applyDateFilter()">
        <option value="all">All</option>
        <option value="today">Today</option>
        <option value="yesterday">Yesterday</option>
        <option value="last3days">Last 3 Days</option>
        <option value="lastweek">Last Week</option>
        <option value="lastmonth">Last Month</option>
        <option value="custom">Custom Range</option>
    </select>

    <!-- Custom Date Range Inputs -->
    <div id="custom-range" class="custom-date-ranges" style="display: none;">
        <label for="start-date"></label>
        <input type="date" id="start-date">
        <label for="end-date"></label>
        <input type="date" id="end-date">
        <button onclick="applyCustomDateRange()">Apply Filter</button>
    </div>
</div>


        <!-- Walk-In Table -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Contact Number</th>
                    <th>Gender</th>
                    <th>Join Date</th>
                    <th>Walk-In Type</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody id="walkinDetails">
                <!-- Walk-in data will be inserted here dynamically -->
            </tbody>
        </table>
        <!-- No results message for walk-ins -->
        <div id="walkin-no-results-message" style="display: none; color: red; font-weight: bold;">
    No results found for <span id="date-range-text">the selected date range</span>.
</div>
        <!-- Totals Section -->
<div id="walkinTotals" style="
    display: flex; 
    justify-content: center; 
    margin-top: 10px; 
    font-weight: bold; 
    font-size: 16px; 
    background: #f8f9fa; 
    padding: 10px; 
    border: 1px solid #ddd; 
    border-radius: 5px; 
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
">
    <div style="margin-right: 20px;">Total Walk-Ins: <span id="totalWalkins" style="color: black;">0</span></div>
    <div>Total Amount: <span id="totalAmount" style="color: black;">₱0.00</span></div>
</div>
    </div>
</div>
<!-- Modal for confirming Add Walk-In -->
<div class="modal-add-walkin" id="addWalkinModal">
    <div class="addwalkin-modal-content">
        <span class="close-btns" onclick="closeAddWalkinModal()">&times;</span> <!-- Close button -->
        <i class="fas fa-question-circle modal-icon"></i> <!-- Icon for confirmation -->
        <h3>Are you sure you want to register this client again as a walk-in?</h3>
        <div class="addwalkin-modal-buttons">
            <button class="addwalkin-confirm-btn" onclick="handleAddWalkin()">Yes, Register</button>
            <button class="addwalkin-cancel-btn" onclick="closeAddWalkinModal()">Cancel</button>
        </div>
    </div>
</div>

<script src="/brew+flex/js/walkins.js"></script>
<script>
// Function to apply the selected date filter
function applyDateFilter() {
    const filterValue = document.getElementById('date-range').value;
    const walkinDetails = document.getElementById('walkinDetails');
    const rows = walkinDetails.querySelectorAll('tr');
    let filteredRows = [];
    let hasResults = false; // Flag to track if there are any filtered rows

    // Clear previous no results message
    const noResultsMessage = document.getElementById('walkin-no-results-message');
    const dateRangeText = document.getElementById('date-range-text');
    dateRangeText.textContent = ''; // Reset message content initially

    // Hide the "No Results Found" message at the start of each filter application
    noResultsMessage.style.display = 'none'; // Hide the message whenever the filter is applied

    rows.forEach(row => {
        const dateCell = row.cells[4]; // Assuming the 'Join Date' is in the 5th column (index 4)
        const joinDate = dateCell ? new Date(dateCell.textContent) : null;
        
        if (!joinDate) return; // If no join date, skip this row

        let showRow = false;

        switch (filterValue) {
            case 'all':
                showRow = true;
                break;
            case 'today':
                showRow = isToday(joinDate);
                dateRangeText.textContent = 'Today'; // Set message for today
                break;
            case 'yesterday':
                showRow = isYesterday(joinDate);
                dateRangeText.textContent = 'Yesterday'; // Set message for yesterday
                break;
            case 'last3days':
                showRow = isWithinLastNDays(joinDate, 3);
                dateRangeText.textContent = 'Last 3 Days';
                break;
            case 'lastweek':
                showRow = isWithinLastNDays(joinDate, 7);
                dateRangeText.textContent = 'Last Week';
                break;
            case 'lastmonth':
                showRow = isWithinLastMonth(joinDate);
                dateRangeText.textContent = 'Last Month';
                break;
            case 'custom':
                const startDate = document.getElementById('start-date').value ? new Date(document.getElementById('start-date').value) : null;
                const endDate = document.getElementById('end-date').value ? new Date(document.getElementById('end-date').value) : null;
                showRow = (startDate && endDate) ? (joinDate >= startDate && joinDate <= endDate) : false;

                // Update the message text for the custom range
                if (startDate && endDate) {
                    dateRangeText.textContent = `from ${startDate.toLocaleDateString()} to ${endDate.toLocaleDateString()}`;
                }

                // Show "No results found" message for custom range
                if (!showRow && startDate && endDate) {
                    noResultsMessage.style.display = 'block';
                    dateRangeText.textContent = `No results found for the selected custom range from ${startDate.toLocaleDateString()} to ${endDate.toLocaleDateString()}`;
                }
                break;
        }

        // Show or hide row based on the condition
        row.style.display = showRow ? '' : 'none';
        if (showRow) {
            filteredRows.push(row);
            hasResults = true; // Set to true if at least one row is visible
        }
    });

    // Update totals dynamically
    updateTotals(filteredRows);

    // Toggle "No Results Found" message
    if (!hasResults) {
        noResultsMessage.style.display = 'block'; // Show the message if no rows match
    } else {
        noResultsMessage.style.display = 'none'; // Hide the message if there are results
    }
}


// Helper function to check if the date is today
function isToday(date) {
    const today = new Date();
    return date.getDate() === today.getDate() &&
           date.getMonth() === today.getMonth() &&
           date.getFullYear() === today.getFullYear();
}

// Helper function to check if the date is yesterday
function isYesterday(date) {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    return date.getDate() === yesterday.getDate() &&
           date.getMonth() === yesterday.getMonth() &&
           date.getFullYear() === yesterday.getFullYear();
}

// Helper function to check if the date is within the last N days
function isWithinLastNDays(date, n) {
    const now = new Date();
    now.setDate(now.getDate() - n);
    return date >= now;
}

// Helper function to check if the date is within the last month
function isWithinLastMonth(date) {
    const now = new Date();
    now.setMonth(now.getMonth() - 1);
    return date >= now;
}

// Function to update totals (Total Walk-ins and Total Amount)
function updateTotals(filteredRows) {
    let totalWalkins = filteredRows.length;
    let totalAmount = 0;

    filteredRows.forEach(row => {
        const amountCell = row.cells[6]; // Assuming the 'Amount' is in the 7th column (index 6)
        const amount = parseFloat(amountCell.textContent.replace(/[^\d.-]/g, '')) || 0;
        totalAmount += amount;
    });

    // Update totals in the modal
    document.getElementById('totalWalkins').textContent = totalWalkins;
    document.getElementById('totalAmount').textContent = '₱' + totalAmount.toFixed(2);
}

// Show custom date inputs if custom range is selected
function showCustomRangeInputs() {
    const dateRange = document.getElementById('date-range').value;
    const customRange = document.getElementById('custom-range');
    customRange.style.display = (dateRange === 'custom') ? 'block' : 'none';
}

// Trigger custom range inputs visibility on page load
window.onload = function() {
    showCustomRangeInputs();
}

// Listen for changes in the date range select
document.getElementById('date-range').addEventListener('change', showCustomRangeInputs);

// Function to apply the custom date range when selected
function applyCustomDateRange() {
    applyDateFilter();
}

// Function to reset the date filter
function resetFilter() {
    // Reset the filter dropdown to 'all' (show all rows)
    document.getElementById('date-range').value = 'all';

    // Hide the custom date range input fields
    document.getElementById('custom-range').style.display = 'none';

    // Reset the start and end date inputs
    document.getElementById('start-date').value = '';
    document.getElementById('end-date').value = '';

    // Get all the rows and show them (reset the filter to show all rows)
    const rows = document.querySelectorAll('#walkinDetails tr');
    rows.forEach(row => {
        row.style.display = ''; // Show all rows
    });

    // Update totals (without any filters applied)
    updateTotals(rows);
}

// Function to search customers by name
function searchCustomer() {
    var input = document.getElementById('search-bar');
    var filter = input.value.toLowerCase();

    var table = document.querySelector('table');
    var rows = table.getElementsByTagName('tr');
    var hasResults = false;

    var totalWalkins = 0; // Initialize counter for walk-ins
    var totalAmount = 0; // Initialize total amount

    // Clear previous "No Results" message
    const noResultsMessage = document.getElementById('no-results-message');
    const searchTermSpan = document.getElementById('search-term');

    for (var i = 1; i < rows.length; i++) { // Skip the header row
        var row = rows[i];
        var nameCell = row.cells[1]; // Assuming "Full Name" is the second column (index 1)
        var amountCell = row.cells[6]; // Assuming "Amount" is the seventh column (index 6)
        var nameText = nameCell.textContent || nameCell.innerText;
        var amount = parseFloat(amountCell.textContent) || 0;

        if (nameText.toLowerCase().indexOf(filter) > -1) {
            // Highlight matched text
            var regex = new RegExp('(' + filter + ')', 'gi');
            nameCell.innerHTML = nameText.replace(regex, '<span class="highlight">$1</span>');

            row.style.display = ''; // Show the row
            hasResults = true;

            // Count and sum up visible rows
            totalWalkins++;
            totalAmount += amount;
        } else {
            nameCell.innerHTML = nameText; // Reset the cell
            row.style.display = 'none'; // Hide the row
        }
    }

    // Update the totals dynamically
    document.querySelector('.summary-item strong').textContent = totalWalkins; // Total Walk-Ins
    document.querySelector('.summary-item:nth-child(2) strong').textContent = '₱' + totalAmount.toFixed(2); // Total Amount

    // Toggle "No Results Found" message and insert the search term dynamically
    if (!hasResults && filter.trim() !== "") {
        searchTermSpan.textContent = filter; // Insert the search term into the message
        noResultsMessage.style.display = 'block'; // Show the message
    } else {
        noResultsMessage.style.display = 'none'; // Hide the message
    }
}

// Function to show the Add Walk-In confirmation modal
function confirmAddWalkin(id) {
    // Store the walk-in ID for later use (optional)
    window.selectedWalkinId = id;

    // Show the modal
    document.getElementById('addWalkinModal').style.display = 'flex';
}

// Function to close the Add Walk-In modal
function closeAddWalkinModal() {
    // Hide the modal
    document.getElementById('addWalkinModal').style.display = 'none';
}

// Function to handle the actual "Add Walk-In" action
function handleAddWalkin() {
    // Redirect or process adding the walk-in (replace with actual logic if needed)
    const walkinId = window.selectedWalkinId;
    if (walkinId) {
        window.location.href = 'addwalkins.php?id=' + walkinId;
    }

    // Close the modal after action
    closeAddWalkinModal();
}

// Function to close the walk-in history modal
function closeModal() {
    document.getElementById('walkinModal').style.display = 'none';
    resetFilter(); // Reset filters when closing the modal

    // Hide the "No Results Found" message when closing the modal
    const noResultsMessage = document.getElementById('walkin-no-results-message');
    noResultsMessage.style.display = 'none';
}


// Function to view walk-in history in a modal
function viewWalkinHistory(walkinId) {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'fetch_walkin_history.php?id=' + walkinId, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            const walkinDetails = document.getElementById('walkinDetails');
            walkinDetails.innerHTML = xhr.responseText; // Insert fetched data

            // Reset totals
            let totalWalkins = 0;
            let totalAmount = 0;

            // Parse the rows in the table dynamically
            const rows = walkinDetails.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.getElementsByTagName('td');
                if (cells.length > 0) { // Ensure it's a valid row
                    totalWalkins++;
                    const amount = parseFloat(cells[6].textContent.replace(/[^\d.-]/g, '')) || 0; // Get amount
                    totalAmount += amount;
                }
            });

            // Update the totals section
            document.getElementById('totalWalkins').textContent = totalWalkins;
            document.getElementById('totalAmount').textContent = '₱' + totalAmount.toFixed(2);

            // Show the modal
            document.getElementById('walkinModal').style.display = 'flex';
        }
    };
    xhr.send();
}

</script>
</body>
</html>