<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
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
    echo "User information not found.";
    exit;
}


date_default_timezone_set('Asia/Manila');


date_default_timezone_set('Asia/Manila'); // Set timezone to Philippines

$currentDateTime = date('Y-m-d\TH:i'); // Format for datetime-local input

// Fetch member data
$member_id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$member_id) {
    die("Error: No member ID provided.");
}

$member_query = "
    SELECT 
        m.member_id, 
        m.first_name, 
        m.last_name, 
        m.email, 
        m.contact_no, 
        m.birthday, 
        m.municipality,
        m.country, 
        m.zipcode, 
        m.city, 
        m.gender, 
        m.profile_picture,
        COALESCE(p.membership_renewal_payment_date, m.date_enrolled) AS date_enrolled,
        COALESCE(p.membership_expiration_date, m.expiration_date) AS expiration_date,
        p.monthly_plan_payment_date, 
        p.monthly_plan_expiration_date, 
        p.locker_payment_date,
        p.locker_expiration_date,
        p.coaching_payment_date,
        CONCAT(c.first_name, ' ', c.last_name, ' (', c.expertise, ')') AS coach_full_details,
        MAX(a.check_in_date) AS last_attendance_date,
        CASE 
            -- Status Pending: No monthly plan purchased
            WHEN p.monthly_plan_payment_date IS NULL THEN 'Pending'
            
            -- Status Inactive: Plan expired
            WHEN p.monthly_plan_expiration_date < NOW() THEN 'Inactive'

            -- Status Inactive: Plan is valid but no attendance in the last month
            WHEN MAX(a.check_in_date) IS NULL OR MAX(a.check_in_date) < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 'Inactive'
            
            -- Status Active: Plan is valid and attendance exists within the last month
            ELSE 'Active'
        END AS status
    FROM 
        members m
    LEFT JOIN 
        payments p 
    ON 
        m.member_id = p.member_id
    LEFT JOIN 
        coaches c 
    ON 
        p.coach_id = c.coach_id
    LEFT JOIN 
        attendance a 
    ON 
        m.member_id = a.member_id
    WHERE 
        m.member_id = ?
    GROUP BY 
        m.member_id
";


$stmt = $conn->prepare($member_query);
if (!$stmt) {
    die("Error preparing member query: " . $conn->error);
}
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member_info) {
    die("Error: Member not found.");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Member - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/viewmember.css">
    <style>
        #pos-no-results-message {
            display: none;
            /* Initially hidden */
            font-weight: bold;
            font-size: 16px;
            padding: 10px;
            text-align: center;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }

        .logout-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            /* Initially hidden */
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-in-out;
            /* Animation for smooth appearance */
        }

        .modal-logouts {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            /* Initially hidden */
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-in-out;
        }

        /* Modal Content */
        .logouts-content {
            background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            width: 320px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.4s ease;
            position: relative;
        }

        .modal-icon {
            font-size: 40px;
            color: #71d4fc;
            margin-bottom: 15px;
        }

        .logouts-content h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* Modal Buttons */
        .modal-buttons {
            margin-top: 20px;
        }

        .logouts-content button {
            margin: 10px 5px;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        /* Confirm Button */
        .confirms-buttons {
            background-color: #71d4fc;
            color: #ffffff;
        }

        .confirms-buttons:hover {
            background-color: #71d4fc;
            transform: scale(1.05);
        }

        /* Cancel Button */
        .cancels-buttons {
            background-color: #ccc;
            color: #333;
        }

        .cancels-buttons:hover {
            background-color: #bbb;
            transform: scale(1.05);
        }

        /* Modal Animations */
        @keyframes fadeIn {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            0% {
                transform: translateY(-50px);
                opacity: 0;
            }

            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        body {
  margin: 0;
  font-family: Arial, sans-serif;
  background-color: #F7FBFC;
  height: 100%;
}

.container {
  display: flex;
  height: 100vh; /* Full viewport height */
}


.main-content-wrapper {
  flex: 1; /* Take up the remaining space */
  display: flex;
  justify-content: center; /* Center horizontally */
  align-items: center; /* Center vertically */
  margin-left: 250px;
  padding: 20px;
  background-color: #F7FBFC;
  overflow-y: auto; /* Handle vertical overflow */
  box-sizing: border-box;
  min-height: 100vh; /* Ensure it spans full height */

}

.main-content {
  max-width: 900px;
  width: 100%;
  overflow-x: hidden; /* Prevent horizontal overflow */
  align-items: center;

}

.profile-resume {
  background: #ffffff;
  border-radius: 15px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
  padding: 30px;
  width: 100%;
  max-width: 900px; /* Set max width */
  box-sizing: border-box; /* Include padding in dimensions */
  overflow: hidden; /* Prevent overflow from child elements */
}



    .profile-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
    }

    .profile-picture {
        width: 150px;
        height: 150px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .profile-picture img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-summary {
        font-size: 1.2rem;
    }

    .profile-summary h1 {
        margin: 0;
        font-size: 2.5rem;
        color: #333;
    }

    .profile-summary .profile-status {
        margin: 5px 0 0;
        color: #555;
        font-size: 1.2rem;
    }

    .profile-details {
        margin-top: 20px;
        
        
    }

    .profile-details h2 {
    margin-top: 30px; /* Adds spacing above the section */
    margin-bottom: 2px; /* Keeps consistent spacing below */
    font-size: 1.4rem; /* Adjust size slightly smaller if needed */
    color: #333; /* Matches the overall theme */
    text-align: left; /* Ensures alignment with content */
   
    padding-bottom: 5px; /* Adds some space under the text if a border is used */
}

.profile-details h4 {
    margin-top: 20px; /* Adds spacing above the section */
    margin-bottom: 20px; /* Keeps consistent spacing below */
    font-size: 1.1rem; /* Adjust size slightly smaller if needed */
    color: #333; /* Matches the overall theme */
    text-align: left; /* Ensures alignment with content */
    border-bottom: 1px solid #ddd; /* Optional: Adds a subtle underline for section separation */
    padding-bottom: 5px; /* Adds some space under the text if a border is used */
    
}


    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        
    }

    .details-grid div {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .details-grid label {
        font-size: 0.9rem;
        color: #666;
    }

    .details-grid span {
        font-size: 1rem;
        color: #333;
    }

    .profile-actions {
        margin-top: 30px;
        text-align: center;
    }

    

    .action-btn {
        padding: 12px 30px;
        background-color: #00bfff; 
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 1.2rem;
        cursor: pointer;
        transition: background-color 0.3s, box-shadow 0.3s;
        margin-right: 15px; /* Adds a gap between buttons */
        margin-bottom: 20px;
        

    }

    .action-btn:hover {
        background-color: #009acd;
        box-shadow: 0 8px 20px rgba(0, 156, 205, 0.3);
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

    <div class="main-content-wrapper">
        <div class="main-content">
            <div class="profile-resume">
                <!-- Profile Section -->
                <div class="profile-header">
                    <div class="profile-picture">
                        <img src="<?php echo htmlspecialchars($member_info['profile_picture'] ?? 'https://via.placeholder.com/180'); ?>" alt="Profile Picture">
                    </div>
                    <div class="profile-summary">
                        <h1><?php echo htmlspecialchars($member_info['first_name'] . ' ' . $member_info['last_name']); ?></h1>
                        <p class="profile-status">Status: <?php echo htmlspecialchars($member_info['status']); ?></p>
                    </div>
                </div>

             <!-- Details Section -->
<div class="profile-details">
    <h2>Personal Info</h2>
    <div class="details-grid">
        <div>
            <label>First Name:</label>
            <span><?php echo htmlspecialchars($member_info['first_name']); ?></span>
        </div>
        <div>
            <label>Last Name:</label>
            <span><?php echo htmlspecialchars($member_info['last_name']); ?></span>
        </div>
        <div>
            <label>Gender:</label>
            <span><?php echo htmlspecialchars($member_info['gender']); ?></span>
        </div>
        <div>
            <label>Email:</label>
            <span><?php echo htmlspecialchars($member_info['email']); ?></span>
        </div>
        <div>
            <label>Phone:</label>
            <span><?php echo htmlspecialchars($member_info['contact_no']); ?></span>
        </div>
        <div>
            <label>Address:</label>
            <span>
                <?php 
                echo htmlspecialchars(
                    trim(
                        ($member_info['zipcode'] ?? '') . ', ' .
                        ($member_info['municipality'] ?? '') . ', ' . 
                        ($member_info['city'] ?? '') . ', ' . 
                        ($member_info['country'] ?? ''), ', '
                    )
                ); 
                ?>
            </span>
        </div>
    </div>

    <h4>Membership Plan</h4>
    <div class="details-grid">
        <div>
            <label>Membership Enrolled Date:</label>
            <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['date_enrolled']))); ?></span>
        </div>
        <div>
            <label>Membership Expiration Date:</label>
            <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['expiration_date']))); ?></span>
        </div>
    </div>

    <?php if (!empty($member_info['monthly_plan_payment_date']) || !empty($member_info['monthly_plan_expiration_date'])): ?>
        <h4>Monthly Plan</h4>
        <div class="details-grid">
            <?php if (!empty($member_info['monthly_plan_payment_date'])): ?>
                <div>
                    <label>Monthly Plan Payment Date:</label>
                    <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['monthly_plan_payment_date']))); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($member_info['monthly_plan_expiration_date'])): ?>
                <div>
                    <label>Monthly Plan Expiration Date:</label>
                    <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['monthly_plan_expiration_date']))); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($member_info['coaching_payment_date']) || !empty($member_info['coach_full_details'])): ?>
        <h4>Coaching Plan</h4>
        <div class="details-grid">
            <?php if (!empty($member_info['coaching_payment_date'])): ?>
                <div>
                    <label>Coaching Plan Payment Date:</label>
                    <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['coaching_payment_date']))); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($member_info['coach_full_details'])): ?>
                <div>
                    <label>Chosen Coach:</label>
                    <span><?php echo htmlspecialchars($member_info['coach_full_details']); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($member_info['locker_payment_date']) || !empty($member_info['locker_expiration_date'])): ?>
        <h4>Locker Plan</h4>
        <div class="details-grid">
            <?php if (!empty($member_info['locker_payment_date'])): ?>
                <div>
                    <label>Locker Plan Payment Date:</label>
                    <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['locker_payment_date']))); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($member_info['locker_expiration_date'])): ?>
                <div>
                    <label>Locker Plan Expiration Date:</label>
                    <span><?php echo htmlspecialchars(date("F j, Y", strtotime($member_info['locker_expiration_date']))); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
 <!-- Actions -->
 <div class="profile-actions">
                    <button class="action-btn" onclick="viewAttendanceHistory(<?php echo $member_info['member_id']; ?>)">View Attendance History</button>
                    <button class="action-btn" onclick="openPosLogsModal(<?php echo $member_info['member_id']; ?>)">View Transaction Records</button>
                </div>
                </div>

               
            </div>
        </div>
    </div>
</div>

    <div id="posLogsModal" class="modal-pos-logs">
        <div class="modal-content-history">
            <span class="close-btn" onclick="closePosLogsModal()">&times;</span>
            <h3>Transactions <i class="fas fa-cash-register"></i></h3>

            <!-- Date Filter Section -->
            <div id="dateFilterSection" class="datefilter">
                <label for="pos-date-range">Filter by Date:</label>
                <select id="pos-date-range" onchange="applyPosDateFilter()">
                    <option value="all">All</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="last3days">Last 3 Days</option>
                    <option value="lastweek">Last Week</option>
                    <option value="lastmonth">Last Month</option>
                    <option value="custom">Custom Range</option>
                </select>

                <!-- Custom Date Range Inputs -->
                <div id="pos-custom-range" class="custom-date-ranges" style="display: none;">
                    <input type="date" id="pos-start-date" />
                    <input type="date" id="pos-end-date" />
                    <button onclick="applyCustomPosDate()">Apply</button>
                </div>
            </div>

            <!-- POS Logs Table -->
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Full Name</th>
                        <th>Transaction Date</th>
                        <th>Amount</th>
                        <th>Transaction Type</th>
                    </tr>
                </thead>
                <tbody id="posLogsDetails">
                    <!-- Dynamic rows will be inserted here -->
                </tbody>
            </table>

            <!-- No Results Message -->
            <div id="pos-no-results-message" style="
            display: none; 
            color: red; 
            font-weight: bold;">
                No results found for <span id="pos-date-range-text">the selected date range</span>.
            </div>

            <!-- Total Transactions Section -->
            <div id="totalTransactionsSection" style="
            display: flex; 
            justify-content: center; 
            margin-top: 10px; 
            font-weight: bold; 
            font-size: 16px; 
            background: #f8f9fa; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                Total Transactions: <span id="totalTransactions">0</span>
            </div>
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

            <!-- No Results Message -->
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
    <style>
        /* Date Filter Section */
        .datefilter {
            align-items: center;
            /* Aligns the label and select vertically */
            margin-bottom: 10px;
            flex-direction: column;
            /* Aligns the label and select vertically */

        }

        .datefilter label {
            font-weight: bold;
            font-size: 16px;
            margin-right: 10px;
            /* Adds some space between the label and the select */
            white-space: nowrap;
            /* Prevents text from wrapping */
        }

        .datefilter select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #fff;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 7px;

        }

        /* Custom Date Range Section */
        .custom-date-ranges {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .custom-date-ranges input {
            padding: 8px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #fff;
        }

        .custom-date-ranges button {
            padding: 8px 12px;
            background-color: #00bfff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .custom-date-ranges button:hover {
            background-color: #009acd;
        }

        /* No Results Message */
        #attendance-no-results-message {
            display: none;
            /* Initially hidden */
            font-weight: bold;
            font-size: 16px;
            padding: 10px;
            text-align: center;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }
    </style>

    <script>
        function applyAttendanceDateFilter() {
            const filterValue = document.getElementById('attendance-date-range').value;
            const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
            const noResultsMessage = document.getElementById('attendance-no-results-message');
            const totalAttendance = document.getElementById('totalAttendance');
            const customRange = document.getElementById('attendance-custom-range');
            const dateRangeText = document.getElementById('date-range-text');

            // Show or hide custom date inputs when 'Custom Range' is selected
            if (filterValue === 'custom') {
                customRange.style.display = 'block';
            } else {
                customRange.style.display = 'none';
            }

            let filteredRows = [];
            rows.forEach(row => {
                const dateCell = row.cells[2]; // Check-In Date is in the 3rd column
                const checkInDate = dateCell ? new Date(dateCell.textContent) : null;
                let showRow = false;

                // Filter based on selected range
                switch (filterValue) {
                    case 'all':
                        showRow = true; // Show all rows
                        break;
                    case 'today':
                        showRow = isToday(checkInDate);
                        break;
                    case 'yesterday':
                        showRow = isYesterday(checkInDate);
                        break;
                    case 'last3days':
                        showRow = isWithinLastNDays(checkInDate, 3);
                        break;
                    case 'lastweek':
                        showRow = isWithinLastNDays(checkInDate, 7);
                        break;
                    case 'lastmonth':
                        showRow = isWithinLastMonth(checkInDate);
                        break;
                }

                row.style.display = showRow ? '' : 'none';
                if (showRow) filteredRows.push(row); // Collect filtered rows
            });

            // Show or hide the "No attendance records" placeholder
            noResultsMessage.style.display = filteredRows.length > 0 ? 'none' : 'block';
            totalAttendance.textContent = filteredRows.length; // Update attendance total
            dateRangeText.textContent = getNoResultSpecificMessage(filterValue); // Update the no results message
            updateTotalAttendance(); // Ensure accurate count
        }

     // Function to apply custom date filter for attendance history
function applyCustomAttendanceDate() {
    const startDate = document.getElementById('attendance-start-date').value;
    const endDate = document.getElementById('attendance-end-date').value;
    const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
    const noResultsMessage = document.getElementById('attendance-no-results-message');

    // Validate custom date range
    if (!startDate || !endDate) {
        alert('Please select both start and end dates.');
        return;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);

    // Adjust the time range: start at 00:00:00 for start date and 23:59:59.999 for end date
    start.setHours(0, 0, 0, 0);  // Set start date to the start of the day (00:00:00)
    end.setHours(23, 59, 59, 999);  // Set end date to the end of the day (23:59:59.999)

    let filteredRows = [];

    rows.forEach(row => {
        const dateCell = row.cells[2]; // Check-In Date column (assuming it's in the 3rd column)
        const checkInDate = dateCell ? new Date(dateCell.textContent) : null;

        // Check if the check-in date is within the adjusted range
        if (checkInDate >= start && checkInDate <= end) {
            row.style.display = ''; // Show the row if it's within the date range
            filteredRows.push(row);
        } else {
            row.style.display = 'none'; // Hide the row if it's outside the range
        }
    });

            // Update attendance totals and display no results message if applicable
            const totalAttendance = document.getElementById('totalAttendance');
            totalAttendance.textContent = filteredRows.length;
            if (filteredRows.length > 0) {
                noResultsMessage.style.display = 'none';
            } else {
                noResultsMessage.style.display = 'block';
                document.getElementById('date-range-text').textContent = `${formatDate(start)} to ${formatDate(end)}`; // Update custom range text
            }
        }

        // Helper functions
        function isToday(date) {
            const today = new Date();
            return date && date.toDateString() === today.toDateString();
        }

        function isYesterday(date) {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            return date && date.toDateString() === yesterday.toDateString();
        }

        function isWithinLastNDays(date, n) {
            const now = new Date();
            const pastDate = new Date();
            pastDate.setDate(now.getDate() - n);
            return date && date >= pastDate && date <= now;
        }

        function isWithinLastMonth(date) {
            const now = new Date();
            const pastDate = new Date();
            pastDate.setMonth(now.getMonth() - 1);
            return date && date >= pastDate && date <= now;
        }

        function formatDate(date) {
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            const year = date.getFullYear();
            return `${month}/${day}/${year}`;
        }

        function getNoResultSpecificMessage(filterValue) {
            switch (filterValue) {
                case 'today':
                    return 'today';
                case 'yesterday':
                    return 'yesterday';
                case 'last3days':
                    return 'the last 3 days';
                case 'lastweek':
                    return 'last week';
                case 'lastmonth':
                    return 'last month';
                case 'custom':
                    return 'the selected custom range';
                default:
                    return 'the selected date range';
            }
        }

        // Function to close Attendance History Modal and reset filters
        function closeAttendanceHistoryModal() {
            document.getElementById('attendanceHistoryModal').style.display = 'none';
            document.getElementById('attendance-date-range').value = 'all';
            document.getElementById('attendance-custom-range').style.display = 'none';
            document.getElementById('attendance-start-date').value = '';
            document.getElementById('attendance-end-date').value = '';
            const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
            rows.forEach(row => row.style.display = ''); // Show all rows
            updateTotalAttendance(); // Reset the total attendance
        }

        function updateTotalAttendance() {
            const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
            let totalAttendance = 0;

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                // Exclude rows that are placeholders or contain "No attendance records found"
                if (
                    cells.length > 0 && // Row has cells
                    !row.textContent.includes('No attendance records found') && // Exclude placeholder rows
                    row.style.display !== 'none' // Only count visible rows
                ) {
                    totalAttendance++;
                }
            });

            document.getElementById('totalAttendance').textContent = totalAttendance; // Update the total count
        }

        function viewAttendanceHistory(memberId) {
            console.log('Fetching attendance for Member ID:', memberId); // Debugging

            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'attendance_history.php?member_id=' + memberId, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const attendanceDetails = document.getElementById('attendanceHistoryDetails');
                    attendanceDetails.innerHTML = xhr.responseText || '<tr><td colspan="3">No attendance records found.</td></tr>';

                    // Display the modal
                    document.getElementById('attendanceHistoryModal').style.display = 'flex';

                    // Update the total attendance count
                    updateTotalAttendance();
                }
            };
            xhr.send();
        }
    </script>



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

        // Function to apply the selected date filter for POS Logs
        function applyPosDateFilter() {
            const filterValue = document.getElementById('pos-date-range').value;
            const rows = document.querySelectorAll('#posLogsDetails tr');
            const noResultsMessage = document.getElementById('pos-no-results-message');
            const totalTransactions = document.getElementById('totalTransactions');
            const customRange = document.getElementById('pos-custom-range');
            const dateRangeText = document.getElementById('pos-date-range-text');

            // Show or hide custom date inputs when 'Custom Range' is selected
            if (filterValue === 'custom') {
                customRange.style.display = 'block';
            } else {
                customRange.style.display = 'none';
            }

            let filteredRows = [];
            rows.forEach(row => {
                const dateCell = row.cells[2]; // Transaction Date is in the 3rd column
                const transactionDate = dateCell ? new Date(dateCell.textContent) : null;
                let showRow = false;

                // Filter based on selected range
                switch (filterValue) {
                    case 'all':
                        showRow = true;
                        break;
                    case 'today':
                        showRow = isToday(transactionDate);
                        break;
                    case 'yesterday':
                        showRow = isYesterday(transactionDate);
                        break;
                    case 'last3days':
                        showRow = isWithinLastNDays(transactionDate, 3);
                        break;
                    case 'lastweek':
                        showRow = isWithinLastNDays(transactionDate, 7);
                        break;
                    case 'lastmonth':
                        showRow = isWithinLastMonth(transactionDate);
                        break;
                }

                row.style.display = showRow ? '' : 'none';
                if (showRow) filteredRows.push(row);
            });

            // Update total transactions and handle no results message
            totalTransactions.textContent = filteredRows.length;
            if (filteredRows.length > 0) {
                noResultsMessage.style.display = 'none';
            } else {
                noResultsMessage.style.display = 'block';
                dateRangeText.textContent = getNoResultSpecificMessage(filterValue);
            }
        }

        // Function to apply custom date filter for POS Logs
        function applyCustomPosDate() {
    const startDate = document.getElementById('pos-start-date').value;
    const endDate = document.getElementById('pos-end-date').value;
    const rows = document.querySelectorAll('#posLogsDetails tr');
    const noResultsMessage = document.getElementById('pos-no-results-message');
    const totalTransactions = document.getElementById('totalTransactions');

    if (!startDate || !endDate) {
        alert('Please select both start and end dates.');
        return;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);

    // Adjust time to include full day (start at 00:00:00 for start date, 23:59:59 for end date)
    start.setHours(0, 0, 0, 0); // Set start date to 00:00:00
    end.setHours(23, 59, 59, 999); // Set end date to 23:59:59

    let filteredRows = [];

    rows.forEach(row => {
        const dateCell = row.cells[2]; // Transaction Date column
        const transactionDate = dateCell ? new Date(dateCell.textContent) : null;

        // Compare transaction date with the full day range
        if (transactionDate >= start && transactionDate <= end) {
            row.style.display = '';
            filteredRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

            // Update total transactions and handle no results message
            totalTransactions.textContent = filteredRows.length;
            if (filteredRows.length > 0) {
                noResultsMessage.style.display = 'none';
            } else {
                noResultsMessage.style.display = 'block';
                document.getElementById('pos-date-range-text').textContent = `${formatDate(start)} to ${formatDate(end)}`;
            }
        }

        // Fetch and populate POS Logs
        function fetchPosLogs(memberId) {
            const posLogsDetails = document.getElementById('posLogsDetails');
            const totalTransactions = document.getElementById('totalTransactions');
            const noResultsMessage = document.getElementById('pos-no-results-message');

            posLogsDetails.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
            totalTransactions.textContent = '0';
            noResultsMessage.style.display = 'none';

            fetch(`fetch_pos_logs.php?member_id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        posLogsDetails.innerHTML = `<tr><td colspan="5">${data.error}</td></tr>`;
                        noResultsMessage.style.display = 'block';
                    } else if (data.length === 0) {
                        posLogsDetails.innerHTML = `<tr><td colspan="5">No transaction records found.</td></tr>`;
                        noResultsMessage.style.display = 'block';
                    } else {
                        let rows = '';
                        data.forEach(log => {
                            rows += `
                        <tr>
                            <td>${log.member_id}</td>
                            <td>${log.full_name}</td>
                            <td>${log.transaction_date}</td>
                            <td>${log.amount}</td>
                            <td>${log.transaction_type}</td>
                        </tr>
                    `;
                        });
                        posLogsDetails.innerHTML = rows;
                        totalTransactions.textContent = data.length;
                    }
                })
                .catch(error => {
                    console.error('Error fetching POS logs:', error);
                    posLogsDetails.innerHTML = `<tr><td colspan="5">Error loading logs.</td></tr>`;
                    noResultsMessage.style.display = 'block';
                });
        }

        // Helper functions
        function isToday(date) {
            const today = new Date();
            return date && date.toDateString() === today.toDateString();
        }

        function isYesterday(date) {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            return date && date.toDateString() === yesterday.toDateString();
        }

        function isWithinLastNDays(date, n) {
            const now = new Date();
            const pastDate = new Date();
            pastDate.setDate(now.getDate() - n);
            return date && date >= pastDate && date <= now;
        }

        function isWithinLastMonth(date) {
            const now = new Date();
            const pastDate = new Date();
            pastDate.setMonth(now.getMonth() - 1);
            return date && date >= pastDate && date <= now;
        }

        function formatDate(date) {
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            const year = date.getFullYear();
            return `${month}/${day}/${year}`;
        }

        function getNoResultSpecificMessage(filterValue) {
            switch (filterValue) {
                case 'today':
                    return 'today';
                case 'yesterday':
                    return 'yesterday';
                case 'last3days':
                    return 'the last 3 days';
                case 'lastweek':
                    return 'last week';
                case 'lastmonth':
                    return 'last month';
                case 'custom':
                    return 'the selected custom range';
                default:
                    return 'the selected date range';
            }
        }

        // Open and close modal functions
        function openPosLogsModal(memberId) {
            fetchPosLogs(memberId);
            document.getElementById('posLogsModal').style.display = 'flex';

            // Reset and apply date filter
            document.getElementById('pos-date-range').value = 'all';
            document.getElementById('pos-custom-range').style.display = 'none';
            applyPosDateFilter();
        }

        function closePosLogsModal() {
            document.getElementById('posLogsModal').style.display = 'none';
            document.getElementById('posLogsDetails').innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
            document.getElementById('totalTransactions').textContent = '0';
        }
    </script>
</body>

</html>