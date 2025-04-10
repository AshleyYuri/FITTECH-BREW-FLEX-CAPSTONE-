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



// Set PHP timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');


// Set default or user-specified date range
$start_date = $_GET['start_date'] ?? '2000-01-01'; // Default to all-time
$end_date = $_GET['end_date'] ?? date('Y-m-d');   // Default to today


// Updated fetchRevenueBreakdown function
function fetchRevenueBreakdown($conn, $start_date = '2000-01-01', $end_date = null) {
    // Use today's date if end_date is not provided
    $end_date = $end_date ?? date('Y-m-d');

    // Add 23:59:59 to the end_date for full-day inclusion
    $end_date_full = $end_date . ' 23:59:59';

    $query = "SELECT transaction_type, SUM(payment_amount) AS total 
              FROM transaction_logs 
              WHERE payment_date BETWEEN ? AND ? 
              GROUP BY transaction_type";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date_full);
    $stmt->execute();
    $result = $stmt->get_result();

    $breakdown = [];
    while ($row = $result->fetch_assoc()) {
        $breakdown[$row['transaction_type']] = $row['total'];
    }

    // Combine walkins and walkins_logs revenue using join_date
    $walkins_revenue = fetchSum($conn, "walkins", "amount", $start_date, $end_date_full, "join_date");
    $walkins_logs_revenue = fetchSum($conn, "walkins_logs", "amount", $start_date, $end_date_full, "join_date");
    $breakdown['Walk-ins'] = $walkins_revenue + $walkins_logs_revenue;

    $breakdown['Gym Goods'] = fetchSum($conn, "pos_logs", "total_amount", $start_date, $end_date_full);

    return $breakdown;
}

// Updated fetchSum function
function fetchSum($conn, $table, $column, $start_date, $end_date, $date_column = 'date') {
    // Add 23:59:59 to end_date for full-day inclusion
    $end_date_full = $end_date . ' 23:59:59';

    $query = "SELECT SUM($column) AS total 
              FROM $table 
              WHERE $date_column BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date_full);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()["total"] ?? 0;
}













// Fetch total members, walk-ins, and attendance
$total_members = fetchCount($conn, "members");
$total_walkins = fetchCount($conn, "walkins");
$total_attendance = fetchCount($conn, "attendance");

// Helper function to fetch counts
function fetchCount($conn, $table) {
    $query = "SELECT COUNT(*) AS total FROM $table";
    $result = $conn->query($query);
    return $result->fetch_assoc()["total"] ?? 0;
}

// Date range variables
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch revenue breakdown
$transaction_breakdown = fetchRevenueBreakdown($conn, $start_date, $end_date);



// Calculate total gym revenue
$total_gym_revenue = array_sum($transaction_breakdown);

// Generate labels and data for charts
$revenue_labels = array_keys($transaction_breakdown);
$revenue_data = array_values($transaction_breakdown);

// Pass data to JavaScript
echo "<script>
    const revenueLabels = " . json_encode($revenue_labels) . ";
    const revenueData = " . json_encode($revenue_data) . ";
    const totalGymRevenue = $total_gym_revenue;
</script>";






// Set the selected year (default to the current year)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch total revenue from all tables for the selected year
$tables = [
    ['table' => 'transaction_logs', 'column' => 'payment_amount', 'date_column' => 'payment_date'],
    ['table' => 'walkins', 'column' => 'amount', 'date_column' => 'join_date'],
    ['table' => 'walkins_logs', 'column' => 'amount', 'date_column' => 'join_date'],
    ['table' => 'pos_logs', 'column' => 'total_amount', 'date_column' => 'date'],
];

$total_revenue = 0;
$monthly_revenue = array_fill(0, 12, 0); // Initialize monthly revenue for Jan-Dec

foreach ($tables as $table) {
    $query = "SELECT MONTH({$table['date_column']}) AS month, SUM({$table['column']}) AS total 
              FROM {$table['table']} 
              WHERE YEAR({$table['date_column']}) = ? 
              GROUP BY MONTH({$table['date_column']})";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $month_index = $row['month'] - 1; // Month is 1-based
        $monthly_revenue[$month_index] += $row['total'];
        $total_revenue += $row['total'];
    }
}

// Pass data to JavaScript
echo "<script>
    const totalRevenue = $total_revenue;
    const monthlyRevenue = " . json_encode($monthly_revenue) . ";
    const selectedYear = $selected_year;
</script>";





// Close the database connection
$conn->close();
?>















<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/attendance.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <style>
   




        /* Chart Styles */
        canvas {
            display: block;
            margin: 0 auto;
            max-width: 90%;
            background-color: #fefefe;
            border-radius: 12px;
            /* Rounded edges for charts */
            padding: 15px;
        }

        /* Specific Styles for Revenue Breakdown Chart */
        #revenueBreakdownChart {
            width: 950px;
            /* Increased size for bar chart */
            height: 650px;
            margin: 0 auto;
        }

        /* Specific Styles for Pie Chart */
        #customerTypeChart {
            max-width: 400px;
            /* Increased size for pie chart */
            margin: 0 auto;
        }

        /* Text and Strong Styles */
        p {
            font-size: 18px;
            color: #555;
        }

        p strong {
            font-weight: bold;
            color: #222;
        }
        .total-revenue-value {
    color: black !important; /* Forces black, overrides other styles */
}


        /* Tooltip Styling */
        .chartjs-tooltip {
            background: rgba(50, 50, 50, 0.8);
            /* Dark tooltip background */
            color: #fff;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 14px;
        }

        /* Center Labels in Charts */
        .chartjs-datalabels {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            color: #fff;
        }

        /* General Styles for All Containers */
.sleek-container {
    background: #f1f1f1;

    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Subtle Shadow */
    transition: transform 0.2s, box-shadow 0.2s;
    color: #ffffff;
    margin-bottom: 20px; /* Space between sections */
}

.sleek-container:hover {
    transform: translateY(-5px); /* Lift on hover */
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2); /* Intensified shadow */
}

/* Revenue Description */
.chart-description {
    font-size: 1.2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.total-revenue-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: #fdcb6e; /* Highlight total value */
}

/* Totals Cards */
.totals-container {
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.sleek-card {
    flex: 1;
    max-width: 300px;
    text-align: center;
    background: linear-gradient(135deg, #74b9ff, #0984e3); /* Gradient for cards */
    color: #ffffff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.sleek-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
}

.sleek-card h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.sleek-card .total-number {
    font-size: 3rem;
    font-weight: bold;
    margin: 0;
}



.filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 60px;
        }

        .filter-container label,
        .year-filter-container label {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            margin-right: 5px;
        }

        .filter-container input[type="date"],
        .year-filter-container select {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-button {
            padding: 5px 10px;
            background-color: #00bfff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-button:hover {
            background-color: #009acd;
        }

        .year-filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
            
        }

        .center-text {
            text-align: center;
            margin-top: 20px;
            font-size: 18 px;
        }

        .center-text strong {
            font-size: 18px;
        }

        .center-text span#yearDisplay {
            font-weight: bold;
        }

        .center-text strong:first-of-type {
            font-weight: bold;
        }

        .center-text span#yearDisplay {
    font-weight: bold;
}


    

/* Responsive Design for Smaller Screens */
@media (max-width: 768px) {
    .sleek-container {
        padding: 15px;
    }

    .chart-title {
        font-size: 1.5rem;
    }

    .chart-description {
        font-size: 1rem;
    }

    .total-revenue-value {
        font-size: 1.5rem;
    }

    .totals-container {
        flex-direction: column;
        align-items: center;
    }

    .sleek-card {
        width: 80%;
    }
}

.logout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none; /* Initially hidden */
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out; /* Animation for smooth appearance */
    }
    
    .modal-logouts {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none; /* Initially hidden */
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

.chart-title {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 15px;
  letter-spacing: 1px;
  font-family: 'Poppins', sans-serif;
  text-transform: uppercase;
  background: linear-gradient(90deg, #000000, #434343, #000000);

  -webkit-background-clip: text; /* WebKit-specific */
    background-clip: text; /* Optional fallback */
color: transparent;
  display: inline-block;
}


.chart-title i {
  margin-left: 8px;
  font-size: 20px;
  vertical-align: middle;
  color: #000;
}











      
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Include Chart.js DataLabels Plugin -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>


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
                    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Transaction Logs</a></li>
                    <li  class="active"><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
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
                  

                </div>

                <div>


                
                
                    <!-- Total Gym Revenue -->
    


    

    <!-- Revenue Breakdown -->
    <div class="chart-container sleek-container">

    <div class="chart-container sleek-container">
        <h2 class="chart-title">
            Total Gym Revenue: <span class="total-revenue-value"><strong><?php echo number_format($total_gym_revenue, 2); ?> PHP</strong> <i class="fas fa-money-bill"></i></span>
        </h2>
       
           
           
        </p>
        
    </div>


        <h2 class="chart-title">
            Revenue Breakdown <i class="fas fa-coins"></i>
        </h2>
   <!-- Date Range Filter -->
   <div class="filter-container">
    <label for="start_date">Start Date:</label>
    <input type="date" id="start_date" value="<?php echo $start_date; ?>">
    <label for="end_date">End Date:</label>
    <input type="date" id="end_date" value="<?php echo $end_date; ?>">
    <button id="applyFilter" class="filter-button">Apply Filter</button>
</div>
<canvas id="revenueBreakdownChart" style="max-height: 800px; width: 100%;"></canvas>

</div>

        <!-- Total Revenue Section -->
<div class="chart-container sleek-container">
    <h2 class="chart-title">
        Yearly Revenue Overview <i class="fas fa-money-check-alt"></i>
    </h2>
  <!-- Year Filter -->
  <div class="year-filter-container">
        <label for="yearFilter">Select Year:</label>
        <select id="yearFilter">
            <?php
            $current_year = date('Y');
            for ($year = $current_year; $year >= 2024; $year--) {
                $selected = ($year == $selected_year) ? 'selected' : '';
                echo "<option value=\"$year\" $selected>$year</option>";
            }
            ?>
        </select>
    </div>

   <!-- Revenue Display -->
   <p class="center-text"><strong>Total Revenue for year </strong> <span id="yearDisplay"><?php echo $selected_year; ?> </span>: 
       <strong><span id="totalRevenueDisplay">0</span> PHP</strong>
    </p>
    <canvas id="totalRevenueChart" style="max-height: 400px; width: 100%;"></canvas>
</div>
        </div>
        



        </main>
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
    // Debugging Data in Console
    console.log('Debugging Totals Data:', {
        total_members: <?php echo $total_members; ?>,
        total_walkins: <?php echo $total_walkins; ?>,
        total_attendance: <?php echo $total_attendance; ?>
    });

    console.log('Debugging Revenue Data:', {
        labels: revenueLabels,
        data: revenueData
    });

    if (revenueLabels.length !== revenueData.length) {
        console.error('Mismatch between labels and data:', revenueLabels, revenueData);
    }

    // Chart rendering logic
const ctx = document.getElementById('revenueBreakdownChart').getContext('2d');

// Create gradient colors dynamically
const createGradient = (context, color1, color2) => {
    const gradient = context.createLinearGradient(0, 0, 0, 400); // Vertical gradient
    gradient.addColorStop(0, color1);
    gradient.addColorStop(1, color2);
    return gradient;
};

// Modern gradient palette
const gradientColors = [
    createGradient(ctx, 'rgba(29, 78, 216, 1)', 'rgba(147, 197, 253, 0.5)'), // Cool Blue
    createGradient(ctx, 'rgba(16, 185, 129, 1)', 'rgba(110, 231, 183, 0.5)'), // Teal
    createGradient(ctx, 'rgba(234, 179, 8, 1)', 'rgba(250, 204, 21, 0.5)'), // Soft Gold
    createGradient(ctx, 'rgba(168, 85, 247, 1)', 'rgba(196, 181, 253, 0.5)'), // Muted Purple
    createGradient(ctx, 'rgba(239, 68, 68, 1)', 'rgba(252, 165, 165, 0.5)'), // Coral Red
    createGradient(ctx, 'rgba(245, 158, 11, 1)', 'rgba(251, 191, 36, 0.5)'), // Warm Orange
    createGradient(ctx, 'rgba(75, 85, 99, 1)', 'rgba(156, 163, 175, 0.5)') // Cool Gray
];

// Chart data
const revenueBreakdownData = {
    labels: revenueLabels,
    datasets: [{
        label: 'Revenue Breakdown',
        data: revenueData,
        backgroundColor: gradientColors,
        borderColor: [
            'rgba(29, 78, 216, 1)', // Cool Blue
            'rgba(16, 185, 129, 1)', // Teal
            'rgba(234, 179, 8, 1)', // Soft Gold
            'rgba(168, 85, 247, 1)', // Muted Purple
            'rgba(239, 68, 68, 1)', // Coral Red
            'rgba(245, 158, 11, 1)', // Warm Orange
            'rgba(75, 85, 99, 1)' // Cool Gray
        ],
        borderWidth: 1
    }]
};

// Render the chart
new Chart(ctx, {
    type: 'bar',
    data: revenueBreakdownData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true
            },
            datalabels: {
                color: 'black', // Label text color
                anchor: 'end',  // Positioning of the labels (start, center, end)
                align: 'top',   // Align labels relative to the bars
                font: {
                    size: 16 // Set font size for data labels
                },
                formatter: (value) => {
                    return `₱${value.toLocaleString()}`; // Format as PHP currency
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true
            },
            x: {
                ticks: {
                    autoSkip: false,
                    maxRotation: 0,
                    minRotation: 0
                }
            }
        }
    },
    plugins: [ChartDataLabels] // Register the datalabels plugin
});




window.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date') || '2000-01-01'; // Default to earliest possible date
    const endDate = urlParams.get('end_date') || new Date().toISOString().split('T')[0]; // Today's date

    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
});



document.getElementById('applyFilter').addEventListener('click', function () {
    let startDate = document.getElementById('start_date').value;
    let endDate = document.getElementById('end_date').value;

    if (!startDate || !endDate) {
        startDate = '2000-01-01'; // Earliest possible date
        endDate = new Date().toISOString().split('T')[0]; // Today's date
    }

    const url = new URL(window.location.href);
    url.searchParams.set('start_date', startDate);
    url.searchParams.set('end_date', endDate);

    window.location.href = url; // Reload with updated date range
});

// Hide chart if no data is available
if (revenueData.every(val => val === 0)) {
    console.warn('No data available for the selected range.');
    document.getElementById('revenueBreakdownChart').style.display = 'none';
    const noDataMessage = document.createElement('p');
    noDataMessage.innerText = 'No data available for the selected date range.';
    document.querySelector('.chart-container').appendChild(noDataMessage);
} else {
    // Render the chart
}





if (revenueLabels.length !== revenueData.length) {
    console.error('Labels and data length mismatch:', revenueLabels, revenueData);
}

         // Handle Year Filter Change
    document.getElementById('yearFilter').addEventListener('change', function () {
        const selectedYear = this.value;
        const url = new URL(window.location.href);
        url.searchParams.set('year', selectedYear);
        window.location.href = url; // Reload the page with the selected year
    });

    // Update Year Display
    document.getElementById('yearDisplay').textContent = selectedYear;

    // Display Total Revenue
    document.getElementById('totalRevenueDisplay').textContent = totalRevenue.toLocaleString('en-US');

    // Data for Total Revenue Chart
    const totalRevenueData = {
        labels: [
            'January', 'February', 'March', 'April', 'May', 
            'June', 'July', 'August', 'September', 'October', 'November', 'December'
        ],
        datasets: [{
            label: `Total Revenue (PHP) - ${selectedYear}`,
            data: monthlyRevenue, // Monthly revenue data from PHP
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };

    // Render Total Revenue Chart
    const totalRevenueCtx = document.getElementById('totalRevenueChart');
    if (totalRevenueCtx) {
        new Chart(totalRevenueCtx.getContext('2d'), {
            type: 'bar',
            data: totalRevenueData,
            options: {
                plugins: {
                    legend: {
                        labels: {
                            color: '#000000', // Legend labels color
                            font: {
                                size: 14 // Adjust legend font size if needed
                            }
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        ticks: {
                            color: '#000000', // Y-axis labels color
                            font: {
                                size: 14 // Adjust font size if needed
                            }
                        },
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(220, 220, 220, 0.3)' // Subtle gridlines
                        }
                    },
                    x: {
                        ticks: {
                            color: '#000000', // X-axis labels color
                            font: {
                                size: 14 // Adjust font size if needed
                            }
                        },
                        grid: {
                            display: false // Remove gridlines for a cleaner look
                        }
                    }
                }
            }
        });
    } else {
        console.error('Canvas element for Total Revenue Chart not found.');
    }
</script>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Include Chart.js DataLabels Plugin -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>


<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>


</body>

</html>