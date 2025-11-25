<?php
header('Content-Type: application/json');
include 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required.']);
    exit;
}

$order_id = $input['order_id'];

// Start transaction
$mysqli->begin_transaction();

try {
    // 1. Get all items from the order
    $stmt = $mysqli->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 2. Update product quantities in the product_stocks table
    $update_stmt = $mysqli->prepare("UPDATE product_stocks SET quantity = quantity + ? WHERE product_id = ? AND size = ?");
    foreach ($order_items as $item) {
        $update_stmt->bind_param('iis', $item['quantity'], $item['product_id'], $item['size']);
        $update_stmt->execute();
    }
    $update_stmt->close();

    // 3. Update the order status to 'Received'
    $status_stmt = $mysqli->prepare("UPDATE orders SET status = 'Received' WHERE id = ?");
    $status_stmt->bind_param('i', $order_id);
    $status_stmt->execute();
    $status_stmt->close();

    $mysqli->commit();
    echo json_encode(['status' => 'success', 'message' => 'Order received and inventory updated.']);
} catch (mysqli_sql_exception $exception) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $exception->getMessage()]);
}

$mysqli->close();
?>