<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode the cart items from the POST request
    $cartItems = json_decode($_POST['cart_items'], true);

    if (empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'No items to update in inventory.']);
        exit;
    }

    // Start a database transaction
    $conn->begin_transaction();

    try {
        foreach ($cartItems as $item) {
            $name = $item['name'];
            $quantity = intval($item['quantity']); // Ensure quantity is an integer

            // Update the inventory: Reduce stock for each item
            $query = "UPDATE inventory SET quantity = quantity - ? WHERE name = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            $stmt->bind_param("is", $quantity, $name);
            if (!$stmt->execute()) {
                throw new Exception("Error updating inventory for $name: " . $stmt->error);
            }

            if ($stmt->affected_rows === 0) {
                throw new Exception("No matching inventory item found or insufficient stock: $name");
            }

            $stmt->close();
        }

        // Commit the transaction if all updates succeed
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Inventory updated successfully.']);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        error_log($e->getMessage()); // Log the error for debugging
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
