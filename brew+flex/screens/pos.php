<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];
$user_info = [];

// Fetch user info
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

// Set timezone
$conn->query("SET time_zone = '+08:00'");

// Fetch members, walk-ins, and coaches
$member_query = "SELECT member_id, first_name, last_name FROM members";
$walkin_query = "SELECT id, name, lastname FROM walkins";
$coach_query = "SELECT coach_id, first_name, last_name FROM coaches";

$members_result = $conn->query($member_query);
$walkins_result = $conn->query($walkin_query);
$coaches_result = $conn->query($coach_query);

// Check for errors in queries
if (!$members_result || !$walkins_result || !$coaches_result) {
    die("Error fetching data: " . $conn->error);
}

// Combine members, walk-ins, and coaches into a single array
$customers = [];
while ($member = $members_result->fetch_assoc()) {
    $customers[] = ['type' => 'member', 'id' => $member['member_id'], 'name' => $member['first_name'] . ' ' . $member['last_name']];
}
while ($walkin = $walkins_result->fetch_assoc()) {
    $customers[] = ['type' => 'walkin', 'id' => $walkin['id'], 'name' => $walkin['name'] . ' ' . $walkin['lastname']];
}
while ($coach = $coaches_result->fetch_assoc()) {
    $customers[] = ['type' => 'coach', 'id' => $coach['coach_id'], 'name' => $coach['first_name'] . ' ' . $coach['last_name']];
}

// Pagination for inventory
$items_per_page = 10; // Number of items per page
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Get current page or default to 1
$start_item = ($currentPage - 1) * $items_per_page;

// Query to fetch inventory data with LIMIT for pagination
$query = "SELECT * FROM inventory LIMIT $start_item, $items_per_page";
$inventory_result = $conn->query($query);

// Check if the query was successful
if (!$inventory_result) {
    die("Error fetching inventory: " . $conn->error);
}

// Get total number of items for pagination
$total_items_query = "SELECT COUNT(*) AS total FROM inventory";
$total_items_result = $conn->query($total_items_query);
$total_items = $total_items_result->fetch_assoc()['total'];
$totalPages = ceil($total_items / $items_per_page);

// Fetch distinct types from the inventory table
$sql = "SELECT DISTINCT type FROM inventory";
$result = $conn->query($sql);
$types = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $types[] = $row['type'];
    }
}

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $customerId = intval($_POST['customer_id']);
    $customerType = $_POST['customer_type'];
    $totalAmount = floatval($_POST['total_amount']);
    $cartItems = json_decode($_POST['cart_items'], true);

    if (!$customerId || !$customerType || empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Invalid customer or cart data.']);
        exit;
    }

    $memberId = $customerType === 'member' ? $customerId : null;
    $walkinId = $customerType === 'walkin' ? $customerId : null;
    $coachId = $customerType === 'coach' ? $customerId : null;
    $itemsJson = json_encode($cartItems);

    $insertQuery = "INSERT INTO pos_logs (member_id, id, coach_id, date, total_amount, items) VALUES (?, ?, ?, NOW(), ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    if ($stmt) {
        $stmt->bind_param("iiids", $memberId, $walkinId, $coachId, $totalAmount, $itemsJson);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Transaction logged successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to log transaction.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Point of Sale - Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/pos.css">
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
                    <li class="active"><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
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
     
        <div class="main-content">
    <div class="pos-container">
        <!-- Inventory Section on the Left -->
        <div class="inventory-section">
            <!-- Modal Structure -->
            <div id="modal" class="modal">
                <div class="modal-content">
                    <p id="modal-message"></p>
                    <button id="modal-btn">OK</button>
                </div>
            </div>
            <div id="confirmation-modal" class="confirmation-modal">
    <div class="confirmation-content">
    <i class="modal-icon fas fa-question-circle"></i> <!-- Icon -->
        <h3 id="confirmation-message"></h3>
        <button class="confirm-btn" id="confirm-checkout-btn">Confirm</button>
        <button class="cancel-btn"id="cancel-checkout-btn">Cancel</button>
    </div>
</div>




    <h2>Point of Sale <i class="fas fa-money-bill"></i></h2>
<!-- Filters Container -->
<div class="filters-container">
    <!-- Select Customer -->
    <div class="customer-selection">
        <label for="customer-select">Select Customer:</label>
        <select id="customer-select">
            <option value="">--Select Customer--</option>
            <!-- Options will be populated dynamically -->
        </select>
    </div>
    <!-- Search and Sort Container -->
    <div class="search-sort-container">
        <!-- Sort By Type Dropdown -->
        <div class="sort-by-type">
            <label for="type-select">Sort by Type:</label>
            <select id="type-select">
                <option value="" disabled selected>Select Category</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Search Bar for Inventory -->
        <div class="inventory-search">
            <div class="search-bar">
                <input type="text" id="search-bar" placeholder="Search item by name...">
                <button type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>
</div>
            <!-- Inventory Table -->
            <div class="scrollable-table">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $inventory_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>₱<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>

                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="item-type"><?php echo htmlspecialchars($item['type']); ?></td>
                                <td>
                                    <button class="add-to-cart-btn">Add to Cart</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            </div>
            <?php if ($total_items > 0): ?>
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
        background-color: #009acd;
}
    

    .pagination .active {
        background-color: #00bfff;
        color: white;
    }

        </style>
        <!-- Cart Summary Section on the Right -->
        <div class="cart-summary">
            <h4>Cart Summary</h4>
            <div class="cart-items">
                <!-- Cart items will be dynamically inserted here -->
            </div>
            <div class="cart-total">
              
                <div>
                Total:
                </div>
                <span></span>
            </div>
            <button class="checkout-btn">Checkout</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cart = [];
    let customerType = '';
    let customerId = '';

    // Modal Elements
    const modal = document.getElementById('modal');
    const modalMessage = document.getElementById('modal-message');
    const modalBtn = document.getElementById('modal-btn');

    // Confirmation Modal Elements
    const confirmationModal = document.getElementById('confirmation-modal');
    const confirmationMessage = document.getElementById('confirmation-message');
    const confirmCheckoutBtn = document.getElementById('confirm-checkout-btn');
    const cancelCheckoutBtn = document.getElementById('cancel-checkout-btn');

    /**
     * Show a generic modal with a specific message
     * @param {string} message - Message to display
     */
    function showModal(message) {
        modalMessage.textContent = message;
        modal.style.display = 'flex';
    }

    /**
     * Close the generic modal
     */
    function closeModal() {
        modal.style.display = 'none';
    }

    modalBtn.addEventListener('click', closeModal);

    /**
     * Show the confirmation modal with a specific message
     * @param {string} message - Message to display in the confirmation modal
     */
    function showConfirmationModal(message) {
        confirmationMessage.textContent = message;
        confirmationModal.style.display = 'flex';
    }

    /**
     * Close the confirmation modal
     */
    function closeConfirmationModal() {
        confirmationModal.style.display = 'none';
    }

    cancelCheckoutBtn.addEventListener('click', closeConfirmationModal);

    /**
     * Fetch customer details
     * @returns {boolean} - Returns true if customer is selected, false otherwise
     */
    function getCustomerDetails() {
        const customerSelect = document.getElementById('customer-select');
        const selectedCustomerId = customerSelect.value;

        if (selectedCustomerId === '') {
            showModal('Please select a customer (member, walk-in, or coach)');
            return false;
        }

        const customer = customers.find((c) => c.id == selectedCustomerId);
        if (customer) {
            customerType = customer.type;
            customerId = customer.id;
            return true;
        } else {
            showModal('Invalid customer selected');
            return false;
        }
    }

// Function to add items to the cart
function addToCart(name, price, availableStock) {
    if (!getCustomerDetails()) return; // Check if customer is selected

    const existingItem = cart.find(item => item.name === name);

    if (existingItem) {
        if (existingItem.quantity < availableStock) {
            existingItem.quantity += 1;
        } else {
            showModal('Cannot add more items than the available stock!');
            return;
        }
    } else {
        if (availableStock > 0) {
            cart.push({
                name,
                price: parseFloat(price) || 0, // Ensure the price is a valid number
                quantity: 1,
                customerType,  // Add customer type
                customerId     // Add customer ID
            });
        } else {
            showModal('Item is out of stock!');
            return;
        }
    }
    updateCartDisplay();
}
// Function to update the cart display
function updateCartDisplay() {
    const cartContainer = document.querySelector('.cart-summary');
    const cartItemsContainer = cartContainer.querySelector('.cart-items');
    const totalContainer = cartContainer.querySelector('.cart-total span');

    cartItemsContainer.innerHTML = '';

    // Display cart items
    cart.forEach(item => {
        const cartItem = document.createElement('div');
        cartItem.classList.add('cart-item');
        cartItem.innerHTML = `
            <span>${item.name} (x${item.quantity})</span>
            <span>₱${(item.price * item.quantity).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
            <button class="remove-item-btn" data-name="${item.name}">Remove</button>
        `;
        cartItemsContainer.appendChild(cartItem);
    });

    // Calculate and display the total
    const total = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
    totalContainer.textContent = `₱${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Event listener for Add to Cart buttons
document.querySelectorAll('.inventory-table button.add-to-cart-btn').forEach(button => {
    button.addEventListener('click', function () {
        const row = button.closest('tr');
        const name = row.querySelector('td:nth-child(1)').textContent.trim();
        const priceText = row.querySelector('td:nth-child(2)').textContent.trim();
        const price = parseFloat(priceText.replace(/[₱$,]/g, '')); // Remove ₱, $, and commas
        const stock = parseInt(row.querySelector('td:nth-child(3)').textContent.trim());

        if (!isNaN(price) && !isNaN(stock)) {
            addToCart(name, price, stock);
        } else {
            console.error('Invalid price or stock:', { price, stock });
        }
    });
});


// Event listener for Remove from Cart buttons
document.querySelector('.cart-summary').addEventListener('click', function (event) {
    if (event.target && event.target.classList.contains('remove-item-btn')) {
        const name = event.target.getAttribute('data-name');
        removeFromCart(name);
    }
});

// Function to remove item from cart
function removeFromCart(name) {
    const index = cart.findIndex(item => item.name === name);
    if (index !== -1) {
        cart.splice(index, 1);
        updateCartDisplay();
    }
}

// Checkout button event listener
document.querySelector('.checkout-btn')?.addEventListener('click', function () {
    if (cart.length === 0) {
        showModal('Your cart is empty!');
    } else {
        showConfirmationModal('Are you sure you want to proceed with the checkout?');
    }
});

    // Confirm checkout
    confirmCheckoutBtn.addEventListener('click', function () {
        closeConfirmationModal(); // Close the confirmation modal

        const formData = new FormData();
        formData.append('checkout', true);
        formData.append('customer_id', customerId);
        formData.append('customer_type', customerType);
        formData.append('total_amount', cart.reduce((acc, item) => acc + item.price * item.quantity, 0).toFixed(2));

        // Prepare cart_items with names and quantities
        const cartItems = cart.map(item => ({ name: item.name, quantity: item.quantity }));
        formData.append('cart_items', JSON.stringify(cartItems));

        // Step 1: Log the transaction
        fetch('pos.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Step 2: Update inventory
                    fetch('update_inventory.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(updateResponse => {
                            if (updateResponse.success) {
                                showModal('Transaction and inventory update successful!');
                                cart.length = 0; // Clear the cart
                                updateCartDisplay();
                                setTimeout(() => window.location.reload(true), 1500);
                            } else {
                                showModal('Transaction successful, but inventory update failed.');
                                console.error(updateResponse.errors);
                            }
                        })
                        .catch(error => {
                            console.error('Inventory update error:', error);
                            showModal('Transaction successful, but inventory update failed.');
                        });
                } else {
                    showModal(data.message || 'Transaction failed.');
                }
            })
            .catch(error => {
                console.error('Transaction error:', error);
                showModal('An error occurred during checkout.');
            });
    });

    // Fetch combined customers data from PHP
    const customers = <?php echo json_encode($customers); ?>;
    // Get the customer dropdown
    const customerSelect = document.getElementById('customer-select');
    // Populate the dropdown with customers
    customers.forEach(customer => {
        const option = document.createElement('option');
        option.value = customer.id;
        option.textContent = `${customer.name} (${customer.type.charAt(0).toUpperCase() + customer.type.slice(1)})`;
        customerSelect.appendChild(option);
    });

    document.getElementById('search-bar').addEventListener('input', function () {
        let searchQuery = this.value.toLowerCase();
        let rows = document.querySelectorAll('.inventory-table tbody tr');

        rows.forEach(row => {
            let itemName = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            if (itemName.includes(searchQuery)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    document.getElementById('type-select').addEventListener('change', function () {
        let selectedType = this.value;
        let rows = document.querySelectorAll('.inventory-table tbody tr');

        rows.forEach(row => {
            let itemType = row.querySelector('.item-type').textContent.toLowerCase();

            // If the selected type is empty or matches the item's type, show the row
            if (!selectedType || itemType.includes(selectedType.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});



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


    </script>
</body>

</html>