<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

$username = $_SESSION['username'];
$usertype = $_SESSION['usertype'];

// Fetch profile picture, username, and email of the logged-in user
$query = "SELECT username, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $usertype);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->num_rows === 1 ? $result->fetch_assoc() : null;

// Get search query from GET parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchWildcard = '%' . $search . '%';

// Sorting parameters
$sortColumn = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'member_id';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'desc';

// Validate sort column and sort direction to prevent SQL injection
$validSortColumns = ['member_id', 'first_name', 'last_name', 'contact_no', 'date_enrolled', 'expiration_date'];
$validSortOrders = ['asc', 'desc'];
if (!in_array($sortColumn, $validSortColumns)) {
    $sortColumn = 'member_id';
}
if (!in_array($sortBy, $validSortOrders)) {
    $sortBy = 'desc';
}

// Get filter and date range parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$joinStartDate = isset($_GET['join_start_date']) ? $_GET['join_start_date'] : null;
$joinEndDate = isset($_GET['join_end_date']) ? $_GET['join_end_date'] : null;

// Build base query
$query = "
    SELECT 
        m.member_id, 
        m.first_name, 
        m.last_name, 
        m.contact_no, 
        m.date_enrolled, 
        m.expiration_date, 
        COALESCE(p.coaching_payment_date, '') AS coaching_payment_date, 
        COALESCE(p.monthly_plan_payment_date, '') AS monthly_plan_payment_date, 
        COALESCE(p.monthly_plan_expiration_date, '') AS monthly_plan_expiration_date, 
        COALESCE(p.membership_renewal_payment_date, '') AS membership_renewal_payment_date, 
        COALESCE(p.membership_expiration_date, '') AS membership_expiration_date, 
        COALESCE(p.locker_payment_date, '') AS locker_payment_date, 
        COALESCE(p.locker_expiration_date, '') AS locker_expiration_date, 
        m.generated_code
    FROM members m
    LEFT JOIN payments p ON m.member_id = p.member_id
    WHERE CONCAT(m.first_name, ' ', m.last_name) LIKE ?
";
// Initialize query parameters
$params = [$searchWildcard];
$types = 's';

// Add filter conditions based on selected filter
if ($filter === 'expired') {
    $query .= " AND (
        m.expiration_date <= CURDATE() OR 
        p.membership_expiration_date <= CURDATE() OR 
        p.monthly_plan_expiration_date <= CURDATE() OR 
        p.locker_expiration_date <= CURDATE()
    )";
} elseif ($filter === 'near_expiry') {
    $query .= " AND (
        m.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) OR 
        p.membership_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) OR 
        p.monthly_plan_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) OR 
        p.locker_expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    )";
} elseif ($filter === 'date_expiry' && $startDate && $endDate) {
    $query .= " AND (
        p.membership_expiration_date BETWEEN ? AND ? OR 
        p.monthly_plan_expiration_date BETWEEN ? AND ? OR 
        p.locker_expiration_date BETWEEN ? AND ?
    )";
    array_push($params, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
    $types .= 'ssssss';
} elseif ($filter === 'date_joined' && $joinStartDate && $joinEndDate) {
    $query .= " AND m.date_enrolled BETWEEN ? AND ?";
    array_push($params, $joinStartDate, $joinEndDate);
    $types .= 'ss';
}

// Add sorting
$query .= " ORDER BY $sortColumn $sortBy";

// Pagination settings
$recordsPerPage = 20; // **Number of records per page changed to 20**
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// For maintaining filter and search state in the URL
$filter_url = isset($_GET['filter']) ? "&filter=" . $_GET['filter'] : '';
$search_url = isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : '';
$sort_url = isset($_GET['sort_column']) && isset($_GET['sort_by']) ? "&sort_column=" . $_GET['sort_column'] . "&sort_by=" . $_GET['sort_by'] : '';

// Modify SQL query to include pagination
$query .= " LIMIT ? OFFSET ?";
array_push($params, $recordsPerPage, $offset);  // **Bind LIMIT and OFFSET**
$types .= 'ii'; // **Binding the new parameters**

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params); // Bind the parameters (including LIMIT and OFFSET)
$stmt->execute();
$result = $stmt->get_result();

// Fetch all members
$members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

function highlightDate($date, $startDate, $endDate, $currentFilter, $targetFilter, $isJoinDate = false)
{
    if (empty($date)) {
        return 'N/A';
    }

    $formattedDate = date('F j, Y', strtotime($date));

    // Highlight for "Date Joined" filter
    if ($isJoinDate) {
        if ($currentFilter === 'date_joined' && !empty($startDate) && !empty($endDate) && strtotime($date) >= strtotime($startDate) && strtotime($date) <= strtotime($endDate)) {
            return '<span class="highlight">' . htmlspecialchars($formattedDate) . '</span>';
        }
        return htmlspecialchars($formattedDate);
    }

    // Highlight for expired and near-expiry filters
    if ($currentFilter === 'expired' && strtotime($date) < time()) {
        return '<span class="highlight expired">' . htmlspecialchars($formattedDate) . '</span>';
    }

    if ($currentFilter === 'near_expiry' && strtotime($date) <= strtotime('+3 days')) {
        return '<span class="highlight near-expiry">' . htmlspecialchars($formattedDate) . '</span>';
    }

    // Highlight for target filter date range
    if ($currentFilter === $targetFilter && !empty($startDate) && !empty($endDate) && strtotime($date) >= strtotime($startDate) && strtotime($date) <= strtotime($endDate)) {
        return '<span class="highlight">' . htmlspecialchars($formattedDate) . '</span>';
    }

    return htmlspecialchars($formattedDate);
}

// Count the number of records after applying the 'date_expiry' filter (without pagination)
$totalQuery = "
    SELECT COUNT(*) AS total
    FROM members m
    LEFT JOIN payments p ON m.member_id = p.member_id
    WHERE CONCAT(m.first_name, ' ', m.last_name) LIKE ?
";

$totalStmt = $conn->prepare($totalQuery);
$totalStmt->bind_param("s", $searchWildcard);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $recordsPerPage);  // Calculate total pages

// Close the database connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head><!--  -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/memberspage.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <style>
        /* Red highlight for expired dates */
.highlight.expired {
    background-color: red;
    color: black;
    font-weight: bold;
}

/* Yellow highlight for near-expiry dates */
.highlight.near-expiry {
    background-color: yellow;
    color: black;
    font-weight: bold;
}


.id-card {
    width: 350px;
    height: 520px;
    background: linear-gradient(135deg,rgb(202, 218, 224), #71d4fc); /* Modern gradient background */
    color: #fff; /* Light text for better contrast */
    text-align: center;
    padding: 25px;
    border-radius: 20px; /* Rounded corners for a sleek, modern look */
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2); /* Deep shadow for depth */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.gym-name {
    font-family: 'Montserrat', sans-serif; /* Sleek and modern sans-serif font */
    font-size: 36px; /* Larger font size for sleekness and impact */
    font-weight: 700; 
    color: rgba(9, 9, 9, 0.8); /* Light footer text */
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 2px; /* Subtle letter-spacing for a more refined feel */
    text-align: center;
    line-height: 1.2; /* Slightly tighter line-height for a more compact look */
}



.gym-logo {
    width: 80px;
    height: 80px;
    margin-bottom: 20px;
    border-radius: 50%; /* Circular logo for sleekness */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.id-card h2 {
    font-size: 22px;
    color: #fff;
    font-weight: 600;
    margin: 10px 0;
}

.id-card img {
    width: 160px;
    height: 160px;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); /* Stronger shadow for QR code */
    margin-top: 10px;
}

.id-footer {
    font-size: 16px;
    color: rgba(9, 9, 9, 0.8); /* Light footer text */
   
    margin-top: 8px;
   
    
}

    </style>
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
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php if ($usertype === 'admin'): ?>
                        <li><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                        <li><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <?php endif; ?>
                    <li><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
                    <li class="active"><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                    <li><a href="payment.php"><i class="fas fa-credit-card"></i> Payment</a></li>
                    <li><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                    <li><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
                    <li><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
                    <li><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
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
            <main class="main-content">
                <div class="header">
                    <h2>Members <i class="fas fa-user-friends"></i></h2>
                </div>
                <div class="header-controls">
                    <!-- Add Member Button -->
                    <div class="add-member">
                        <button class="add-btn" onclick="location.href='registration.php'">Add Member <i class="fas fa-plus"></i></button>
                    </div>
                    <!-- Show Entities and Search Bar (Side by Side) -->
                    <div class="filters-and-search">
                        <div class="entity-controls">
                            <h3>Filter by:</h3>
                            <form method="GET" action="memberspage.php">
                                <!-- Dropdown Filter -->
                                <select name="filter" id="filter-dropdown" onchange="this.form.submit(); toggleDateInputs();">
                                    <option value="all" <?php echo ($filter == 'all') ? 'selected' : ''; ?>>Show All</option>
                                    <option value="expired" <?php echo ($filter == 'expired') ? 'selected' : ''; ?>>Show Expired</option>
                                    <option value="near_expiry" <?php echo ($filter == 'near_expiry') ? 'selected' : ''; ?>>Show Near Expiry</option>
                                    <option value="date_joined" <?php echo ($filter == 'date_joined') ? 'selected' : ''; ?>>Date Joined</option>
                                    <option value="date_expiry" <?php echo ($filter == 'date_expiry') ? 'selected' : ''; ?>>Date Expiry</option>
                                </select>
                                <div id="date-joined-filters" class="date-filters" style="display:block">
                                    <label for="join_start_date"></label>
                                    <input type="date" id="join_start_date" name="join_start_date" value="<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>">
                                    <label for="join_end_date"></label>
                                    <input type="date" id="join_end_date" name="join_end_date" value="<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>">
                                    <!-- Apply Filters Button -->
                                    <button type="submit" id="apply-filters-button">Apply Filters</button>
                                </div>
                                <div id="date-expiry-filters" class="date-filters">
                                    <label for="start_date"></label>
                                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                                    <label for="end_date"></label>
                                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                                    <!-- Apply Filters Button -->
                                    <button type="submit" id="apply-filters-button">Apply Filters</button>
                                </div>
                            </form>
                        </div>
                        <div class="search-bar">
                            <form method="GET" action="memberspage.php">
                                <input type="text" name="search" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-expired-row">
                    <div class="table-container">
                    <div class="scrollable-container">

                        <table>
                            <thead>
                                <tr>
                                    <th><a href="?sort_column=member_id&sort_by=<?php echo $sortBy === 'asc' ? 'desc' : 'asc'; ?>">Member ID</a></th>
                                    <th>Name</th>
                                    <th>Contact Number</th>
                                    <th>Date of Join</th>
                                    <th>Membership Expiry Date</th>
                                    <th>Monthly Expiry Date</th>
                                    <th>Locker Expiry Date</th>
                                    <th>Edit</th>
                                    <th>View</th>
                                    <th>View QR Code</th>
                                </tr>
                            </thead>
                            <tbody id="member-table-body">
                                <?php
                                $today = date('Y-m-d'); // Today's date for comparisons
                                // Check if there are members to display
                                if (empty($members)): ?>
                                    <!-- Display a message when there are no results -->
                                    <tr>
                                        <td colspan="10" id="no-results">
                                            <p>No results found for "<?php echo htmlspecialchars($search); ?>"</p>
                                        </td>
                                    </tr>
                                    <?php else:
                                    foreach ($members as $member):
                                        // Membership expiration checks
                                        $membership_expiration = $member['membership_expiration_date'] ?? $member['expiration_date'];
                                        $membership_class = '';
                                        if ($membership_expiration < $today) {
                                            $membership_class = 'expired'; // Expired
                                        } elseif ($membership_expiration <= date('Y-m-d', strtotime('+3 days'))) {
                                            $membership_class = 'near-expiry'; // Near-expiry
                                        }
                                        // Monthly plan expiration checks
                                        $monthly_plan_expiration = $member['monthly_plan_expiration_date'] ?? null;
                                        $monthly_plan_class = '';
                                        if ($monthly_plan_expiration && $monthly_plan_expiration < $today) {
                                            $monthly_plan_class = 'expired'; // Expired
                                        } elseif ($monthly_plan_expiration && $monthly_plan_expiration <= date('Y-m-d', strtotime('+3 days'))) {
                                            $monthly_plan_class = 'near-expiry'; // Near-expiry
                                        }
                                        // Locker expiration checks
                                        $locker_expiration = $member['locker_expiration_date'] ?? null;
                                        $locker_class = '';
                                        if ($locker_expiration && $locker_expiration < $today) {
                                            $locker_class = 'expired'; // Expired
                                        } elseif ($locker_expiration && $locker_expiration <= date('Y-m-d', strtotime('+3 days'))) {
                                            $locker_class = 'near-expiry'; // Near-expiry
                                        }
                                    ?>
                                        <!-- Each row contains data attributes for JavaScript filtering -->
                                        <tr
                                            data-fullname="<?php echo htmlspecialchars(strtolower($member['first_name'] . ' ' . $member['last_name'])); ?>"
                                            data-member-id="<?php echo htmlspecialchars($member['member_id']); ?>"
                                            data-contact="<?php echo htmlspecialchars($member['contact_no']); ?>">
                                            <td><?php echo htmlspecialchars($member['member_id']); ?></td>
                                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['contact_no']); ?></td>
                                            <td><?php echo highlightDate($member['date_enrolled'], $joinStartDate, $joinEndDate, $filter, 'date_joined', true); ?></td>
                                            <td><?php echo highlightDate($member['membership_expiration_date'], $startDate, $endDate, $filter, 'date_expiry'); ?></td>
                                            <td><?php echo empty($member['monthly_plan_expiration_date']) ? '' : highlightDate($member['monthly_plan_expiration_date'], $startDate, $endDate, $filter, 'date_expiry'); ?></td>
                                            <td><?php echo empty($member['locker_expiration_date']) ? '' : highlightDate($member['locker_expiration_date'], $startDate, $endDate, $filter, 'date_expiry'); ?></td>
                                            <td>
                                                <button class="button" onclick="location.href='editmember.php?id=<?php echo htmlspecialchars($member['member_id']); ?>'">
                                                    Edit
                                                </button>
                                            </td>
                                            <td>
                                                <button class="button" onclick="location.href='viewmember.php?id=<?php echo htmlspecialchars($member['member_id']); ?>'">
                                                    View
                                                </button>
                                            </td>
                                            <td>
                                                <button class="button" onclick="showQRCodeModal('<?php echo htmlspecialchars($member['member_id']); ?>', '<?php echo htmlspecialchars($member['generated_code']); ?>', '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($members)): ?>
<div class="pagination">
    <!-- First Page Link -->
    <a href="?page=1&filter=<?php echo $filter; ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="first-page">First</a>

    <!-- Previous Page Link -->
    <a href="?page=<?php echo ($currentPage > 1) ? $currentPage - 1 : 1; ?>&filter=<?php echo $filter; ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="prev-page">Previous</a>

    <!-- Page Numbers -->
    <?php if ($totalPages === 1): ?>
        <!-- If only one page, show only Page 1 -->
        <a href="?page=1&filter=<?php echo $filter; ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="page-number active">1</a>
    <?php else: ?>
        <!-- Multiple page numbers if total pages > 1 -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="page-number <?php echo ($currentPage == $i) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    <?php endif; ?>

    <!-- Next Page Link -->
    <a href="?page=<?php echo ($currentPage < $totalPages) ? $currentPage + 1 : $totalPages; ?>&filter=<?php echo $filter; ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="next-page">Next</a>

    <!-- Last Page Link -->
    <a href="?page=<?php echo $totalPages; ?>&filter=<?php echo $filter; ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="last-page">Last</a>
</div>
<?php endif; ?>


                                    </div>                                                               
                </div>
                <div class="export-buttons">
    <button onclick="printTable()">Print Table</button>
    <button onclick="downloadPDF()">Export as PDF</button>
</div>
            </main>
        </div>
    </div>
    
<!-- QR Code Modal -->
<div class="modal" id="qrCodeModal">
    <div class="modal-content">
        <div class="modal-header">
       
            <h3 id="qrModalTitle"></h3>
           
        </div>
        <button class="close-btn" onclick="closeQRCodeModal()">&times;</button>
<style>
    .close-btn {
  position: absolute;
  top: 5px; /* Adjust as needed for vertical positioning */
  right: 10px; /* Adjust as needed for horizontal positioning */
  background: transparent;
  border: none;
  font-size: 24px; /* Adjust size as needed */
  color: black; /* Adjust color if necessary */
  cursor: pointer;
}
</style>
        <div class="modal-body">
            
            <div class="id-card" id="idCard">
                
                <div class="gym-name">BREW+FLEX GYM</div>
                <img class="gym-logo" src="/brew+flex/assets/brewlogo1.png" alt="Brew+Flex Gym Logo">
                <img id="qrCodeImage" class="qr-code" src="" alt="QR Code">
                <div class="id-footer" id="memberName"></div>
                <div class="id-footer" id="memberID"></div>
            </div>
        </div>
        <div class="qr-action-buttons">
            <button onclick="printQRCode()" class="qr-btn">Print QR Code</button>
            <button onclick="downloadQRCode()" class="qr-btn">Download QR Code</button>
        </div>
    </div>
</div>
    <script src="/brew+flex/js/memberspage.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <script>
     
// Function to show the QR Code modal with the member's QR code
function showQRCodeModal(member_id, code, name) {
    const qrData = JSON.stringify({
        member_id: member_id,
        name: name,
        generated_code: code
    });

    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(qrData)}`;

   
    document.getElementById('qrCodeImage').src = qrUrl;
    document.getElementById('memberName').textContent = `Name: ${name}`;
    document.getElementById('memberID').textContent = `Member ID: ${member_id}`;

    document.getElementById('qrCodeModal').style.display = 'flex';
}

function closeQRCodeModal() {
    document.getElementById('qrCodeModal').style.display = 'none';
}

function printQRCode() {
    const idCard = document.getElementById("idCard").outerHTML;
    const printWindow = window.open('', '', 'width=350,height=500');

    printWindow.document.write(`
        <html>
            <head>
                <title>Print ID Card</title>
                <style>
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        background-color: #1a1a1a;
                    }
                    .id-card {
                        width: 350px;
                        height: 500px;
                        background: linear-gradient(135deg, #1a1a1a, #444);
                        color: white;
                        text-align: center;
                        padding: 20px;
                        border-radius: 15px;
                        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: space-between;
                        margin: 50px auto;
                    }
                    .id-card img {
                        width: 160px;
                        height: 160px;
                        margin-bottom: 20px;
                    }
                </style>
            </head>
            <body>
                ${idCard}
            </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.onload = function() {
        printWindow.print();
        printWindow.close();
    };
}
function downloadQRCode() {
    try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a6'
        });
        
        // Get information from the ID card
        const qrCodeImg = document.getElementById('qrCodeImage');
        const memberName = document.getElementById('memberName').textContent;
        const memberID = document.getElementById('memberID').textContent;
        
        // Add gym name
        pdf.setFontSize(18);
        pdf.setFont('helvetica', 'bold');
        pdf.text('BREW+FLEX GYM', 45, 20, { align: 'center' });
        
        // Add QR code
        // We need to handle the cross-origin image by converting it first
        const img = new Image();
        img.onload = function() {
            // Create a canvas to draw the image
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Get the data URL from the canvas
            const dataUrl = canvas.toDataURL('image/png');
            
            // Add image to PDF
            pdf.addImage(dataUrl, 'PNG', 35, 40, 40, 40);
            
            // Add member details
            pdf.setFontSize(12);
            pdf.text(memberName, 45, 95, { align: 'center' });
            pdf.text(memberID, 45, 105, { align: 'center' });
            
            // Save the PDF
            pdf.save('BrewFlex_ID.pdf');
        };
        
        // Load the image
        img.crossOrigin = 'Anonymous';
        img.src = qrCodeImg.src;
        
    } catch (error) {
        console.error("Error generating PDF: ", error);
        alert("Failed to generate PDF. Please try again.");
    }
}



    </script>
</body>
</html>