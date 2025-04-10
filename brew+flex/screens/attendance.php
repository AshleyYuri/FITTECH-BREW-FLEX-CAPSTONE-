<?php
session_start();
require_once 'db_connection.php';

// Set PHP timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Redirect to login if the user isn't logged in
if (!isset($_SESSION["username"])) {
    header("location:/brew+flex/auth/login.php");
    exit;
}

// Retrieve session variables
$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];
$user_info = [];

// Fetch user information from the database
$query = "SELECT username, contact_no, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $usertype);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user_info = $result->fetch_assoc();
} else {
    echo json_encode(["status" => "error", "message" => "User information not found."]);
    exit;
}

// Handle adding attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = intval($_POST['member_id']);

    // Fetch member information, including expiration dates
    $query = "
        SELECT 
            m.first_name, 
            m.last_name, 
            m.expiration_date AS membership_expiration_date, 
            p.monthly_plan_expiration_date 
        FROM members m
        LEFT JOIN payments p ON m.member_id = p.member_id 
        WHERE m.member_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member_result = $stmt->get_result();

    if ($member_result->num_rows == 1) {
        $member = $member_result->fetch_assoc();
        $first_name = $member['first_name'] ?: 'Unknown';
        $last_name = $member['last_name'] ?: 'Unknown';
        $membership_expiration_date = $member['membership_expiration_date'];
        $monthly_plan_expiration_date = $member['monthly_plan_expiration_date'];
        $today_date = date('Y-m-d');

        // Check if membership has expired
        if ($membership_expiration_date && $membership_expiration_date < $today_date) {
            echo json_encode([ 
                "status" => "error", 
                "message" => "Your membership has expired on " . date('F j, Y', strtotime($membership_expiration_date)) . ". Please renew your membership to attend today."
            ]);
            exit;
        }

        // Check if no monthly plan was purchased
        if (!$monthly_plan_expiration_date) {
            echo json_encode([ 
                "status" => "error", 
                "message" => "You have not purchased a monthly plan. Please purchase a plan to mark attendance."
            ]);
            exit;
        }

        // Check if the monthly plan has expired
        if ($monthly_plan_expiration_date < $today_date) {
            echo json_encode([ 
                "status" => "error", 
                "message" => "Your monthly plan has expired on " . date('F j, Y', strtotime($monthly_plan_expiration_date)) . ". Please purchase a new plan to attend today."
            ]);
            exit;
        }

        // Check if the member already has attendance for today
        $check_query = "SELECT * FROM attendance WHERE member_id = ? AND DATE(check_in_date) = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("is", $member_id, $today_date);
        $stmt->execute();
        $check_result = $stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "This member has already been marked for attendance today."]);
        } else {
            // Move existing attendance to logs if exists
            $existing_attendance_query = "SELECT * FROM attendance WHERE member_id = ?";
            $stmt = $conn->prepare($existing_attendance_query);
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $existing_result = $stmt->get_result();

            if ($existing_result->num_rows > 0) {
                $existing_attendance = $existing_result->fetch_assoc();

                // Move to logs
                $log_query = "
                    INSERT INTO attendance_logs (attendance_id, member_id, first_name, last_name, check_in_date)
                    VALUES (?, ?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param(
                    "iisss",
                    $existing_attendance['attendance_id'],
                    $existing_attendance['member_id'],
                    $existing_attendance['first_name'],
                    $existing_attendance['last_name'],
                    $existing_attendance['check_in_date']
                );
                $log_stmt->execute();

                // Update attendance with new check-in date
                $check_in_date = date('Y-m-d H:i:s');
                $update_query = "UPDATE attendance SET check_in_date = ?, first_name = ?, last_name = ? WHERE member_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssi", $check_in_date, $first_name, $last_name, $member_id);
                $update_stmt->execute();
            } else {
                // Insert new record if no previous attendance exists
                $check_in_date = date('Y-m-d H:i:s');
                $insert_query = "INSERT INTO attendance (member_id, first_name, last_name, check_in_date) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("isss", $member_id, $first_name, $last_name, $check_in_date);
                $stmt->execute();
            }
            echo json_encode(["status" => "success", "message" => "Attendance added successfully."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Member not found."]);
    }
    exit;
}

// Fetch date filter and set default to 'all'
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Pagination setup
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;  // Set the number of records to display per page
$offset = ($currentPage - 1) * $records_per_page;  // Calculate the offset for pagination

// Prepare the date filter condition
$date_condition = '';
$today_date = date('Y-m-d');
switch ($date_filter) {
    case 'today':
        $date_condition = "AND DATE(check_in_date) = '$today_date'";
        break;
    case 'yesterday':
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $date_condition = "AND DATE(check_in_date) = '$yesterday'";
        break;
    case 'last_3_days':
        $date_condition = "AND DATE(check_in_date) >= CURDATE() - INTERVAL 3 DAY";
        break;
    case 'last_week':
        $date_condition = "AND check_in_date >= CURDATE() - INTERVAL 1 WEEK";
        break;
    case 'last_month':
        $date_condition = "AND check_in_date >= CURDATE() - INTERVAL 1 MONTH";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "AND DATE(check_in_date) BETWEEN '$start_date' AND '$end_date'";
        }
        break;
    case 'all':
    default:
        $date_condition = ''; // No date filtering
        break;
}

// Prepare the search condition
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchWildcard = '%' . $search . '%';

// Query to count the total number of records (for pagination)
$count_query = "
    SELECT COUNT(*) AS total_records
    FROM attendance
    WHERE CONCAT(first_name, ' ', last_name) LIKE ?
    $date_condition";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("s", $searchWildcard);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total_records'];
$totalPages = ceil($total_records / $records_per_page); // Calculate the total number of pages

// Query to fetch the paginated results
$query = "
    SELECT *, DATE_FORMAT(check_in_date, '%M %d, %Y %h:%i %p') as formatted_date_time
    FROM attendance
    WHERE CONCAT(first_name, ' ', last_name) LIKE ?
    $date_condition
    ORDER BY check_in_date DESC
    LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $searchWildcard, $offset, $records_per_page);
$stmt->execute();
$result = $stmt->get_result();
$attendance_records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

// HTML output to display attendance records and pagination controls
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/attendance.css">
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
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
                    <li><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                    <li class="active"><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
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
                    <h2>Attendance <i class="fas fa-calendar-check"></i></h2>
                </div>
                <button class="attendance-btn" onclick="openAttendanceModal()">Add Attendance <i class="fas fa-plus"></i></button>
                <div class="controls">
                    <form method="GET" action="attendance.php" class="entities-control">
                        <div class="filter-row">
                            <label for="filterByDate">Filter by Date:</label>
                            <select name="date_filter" id="filterByDate" onchange="this.form.submit()">
                                <option value="all" <?php echo ($date_filter == 'all') ? 'selected' : ''; ?>>All</option>
                                <option value="today" <?php echo ($date_filter == 'today') ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo ($date_filter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="last_3_days" <?php echo ($date_filter == 'last_3_days') ? 'selected' : ''; ?>>Last 3 Days</option>
                                <option value="last_week" <?php echo ($date_filter == 'last_week') ? 'selected' : ''; ?>>Last Week</option>
                                <option value="last_month" <?php echo ($date_filter == 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                                <option value="custom" <?php echo ($date_filter == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>

                        <!-- Custom date range picker -->
                        <div id="customDateRange" class="custom-date-range" style="display: <?php echo ($date_filter == 'custom') ? 'block' : 'none'; ?>;">
                            <div class="date-field">
                                <label for="startDate"></label>
                                <input type="date" name="start_date" id="startDate" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                                <label for="endDate"></label>
                                <input type="date" name="end_date" id="endDate" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                                <button type="submit" class="apply-btn">Apply Filter</button>
                            </div>
                        </div>

                    </form>

                    <div>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <form class="search-bar" id="searchForm">
                            <input type="text" id="searchInput" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </form>
                        <audio id="successBeepSound" src="/brew+flex/assets/Barcode_scanner_beep_sound_sound_effect_[_YouConvert.net_].mp3"></audio>
                        <audio id="thankyouBeepSound" src="/brew+flex/assets/THANK_YOU_ATTENDANCE.mp3"></audio>
                        <audio id="errorBeepSound" src="/brew+flex/assets/Error_-_Sound_Effect_Non_copyright_sound_effects_FeeSou_[_YouConvert.net_].mp3"></audio>

                    </div>
                </div>
<!-- Attendance Table -->
<div class="table-container">
    <?php if ($date_filter == 'today'): ?>
    <?php endif; ?>
    <div class="table-wrapper">
        <div class="scrollable-container">
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Check-In Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="attendanceTable">
                    <?php if (empty($attendance_records)): ?>
                        <tr id="noResultsRow">
                            <td colspan="4" class="no-results">No results found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td class="center-align"><?php echo htmlspecialchars($record['member_id']); ?></td>
                                <td class="center-align"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                <td class="center-align"><?php echo htmlspecialchars($record['formatted_date_time']); ?></td>
                                <td class="center-align">
                                    <button class="action-btn view-btn" onclick="viewAttendanceHistory(<?php echo $record['attendance_id']; ?>)">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!empty($attendance_records)): ?>
    <div class="pagination">
        <!-- First Page Link -->
        <a href="?page=1&filter=<?php echo urlencode($filter); ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="first-page">First</a>

        <!-- Previous Page Link -->
        <a href="?page=<?php echo ($currentPage > 1) ? $currentPage - 1 : 1; ?>&filter=<?php echo urlencode($filter); ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="prev-page">Previous</a>

        <!-- Page Numbers -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="page-number <?php echo ($currentPage == $i) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <!-- Next Page Link -->
        <a href="?page=<?php echo ($currentPage < $totalPages) ? $currentPage + 1 : $totalPages; ?>&filter=<?php echo urlencode($filter); ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="next-page">Next</a>

        <!-- Last Page Link -->
        <a href="?page=<?php echo $totalPages; ?>&filter=<?php echo urlencode($filter); ?>&join_start_date=<?php echo htmlspecialchars($_GET['join_start_date'] ?? ''); ?>&join_end_date=<?php echo htmlspecialchars($_GET['join_end_date'] ?? ''); ?>&start_date=<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>&end_date=<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" class="last-page">Last</a>
    </div>
<?php endif; ?>

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
    background-color: #ddd;
}

.pagination .active {
    background-color: #007bff;
    color: white;
}

</style>

                <!-- Total Attendance Summary -->
                <div class="attendance-summary">
                    <span>Total Attendance: <?php echo count($attendance_records); ?></span>
                </div>
        </div>
        <!-- Modal for Attendance -->
        <div id="modalAttendanceSearch" class="modal-attendance">
            <div class="modal-content-attendance">
                <span class="close" onclick="closeAttendanceModal()">&times;</span>
                <h3>Select Member to Mark Attendance</h3>

                <!-- Search Bar -->
                <div class="search-container">
                    <input type="text" id="searchMember" placeholder="Search for a member..." onkeyup="filterMembers()" />
                    <i class="fas fa-search search-icon"></i>
                </div>

                <!-- Member Dropdown List -->
                <select id="selectMember" name="selectMember" onchange="showSelectedMember()" size="5" style="width: 100%; height: 150px; overflow-y: auto; font-size: 14px; border: 1px solid #ccc; border-radius: 5px;">
                    <?php
                    // Fetch all members from the database
                    $query = "SELECT member_id, CONCAT(first_name, ' ', last_name) AS full_name FROM members";
                    $result = $conn->query($query);
                    while ($member = $result->fetch_assoc()) {
                        echo "<option value='{$member['member_id']}' data-name='{$member['full_name']}'>"
                            . htmlspecialchars($member['full_name']) . "</option>";
                    }
                    ?>
                </select>

                <!-- Container to display selected member's data -->
                <div id="selectedMemberDetails" style="margin-top: 10px; padding: 1px; border-top: 1px solid #ccc;">
                    <p style="color: #555; font-size: 14px;">Selected Member Details:</p>
                    <div id="memberDetailsContent" style="font-size: 16px; font-weight: bold; color: #333;"></div>
                </div>

                <button class="attendance-btns" onclick="markAttendance()">Mark Attendance</button>
                <button class="scanner-btn" onclick="openQRScanner()">Scan QR Code</button>
            </div>
        </div>



        <!-- Modal for QR Scanner -->
        <div id="qrScannerModal" class="modal-scanner">
            <div class="qrscanner-modal">
                <span class="close" onclick="closeQRScanner()">&times;</span>
                <h3>Scan QR Code</h3>
                <video id="interactive" class="viewport" autoplay></video>
            </div>
        </div>
        <!-- Attendance History Modal -->
        <div id="attendanceHistoryModal" class="modal-attendance-history">
            <div class="modal-content-history">
                <span class="close-btn" onclick="closeAttendanceHistoryModal()">&times;</span>
                <h3>Attendance History <i class="fas fa-calendar-check"></i></h3>
                <!-- Date Filter Section -->
                <div id="dateFilterSection" class="datefilter">
                    <label for="attendance-date-range">Filter by Date:</label>
                    <select id="attendance-date-range" onchange="applyAttendanceDateFilter()">
                        <option value="all">All</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last3days">Last 3 Days</option>
                        <option value="lastweek">Last Week</option>
                        <option value="lastmonth">Last Month</option>
                        <option value="custom">Custom Range</option>
                    </select>

                    <!-- Custom Date Range Inputs -->
                    <div id="attendance-custom-range" class="custom-date-ranges" style="display: none;">
                        <input type="date" id="attendance-start-date" />
                        <input type="date" id="attendance-end-date" />
                        <button onclick="applyCustomAttendanceDate()">Apply</button>
                    </div>
                </div>



                <!-- Attendance Table -->
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Name</th>
                            <th>Check-In Date</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceHistoryDetails">
                        <!-- Dynamic rows will be inserted here -->
                    </tbody>
                </table>
                <div id="attendance-no-results-message" style="display: none; color: red; font-weight: bold;">
                    No results found for <span id="date-range-text">the selected date range</span>.
                </div>

                <!-- Total Attendance Section -->
                <div id="totalAttendanceSection" style="
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
                    Total Attendance: <span id="totalAttendance">0</span>
                </div>

            </div>
        </div>
        <script src="/brew+flex/js/attendance.js"></script>
        <script>

            
        </script>
</body>
</html>