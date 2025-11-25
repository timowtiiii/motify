<?php
header('Content-Type: application/json');
include 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['supplier_id']) || !isset($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

$supplier_id = $input['supplier_id'];
$items = $input['items'];

// Start transaction
$mysqli->begin_transaction();

try {
    // 1. Create a new order
    $stmt = $mysqli->prepare("INSERT INTO orders (supplier_id, status) VALUES (?, 'Placed')");
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $order_id = $mysqli->insert_id;
    $stmt->close();

    // 2. Add items to the order
    $stmt = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, size, quantity) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        if (isset($item['product_id']) && isset($item['quantity']) && isset($item['size'])) {
            $stmt->bind_param('iisi', $order_id, $item['product_id'], $item['size'], $item['quantity']);
            $stmt->execute();
        }
    }
    $stmt->close();

    // Commit transaction
    $mysqli->commit();

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Order created successfully.', 'order_id' => $order_id]);

} catch (mysqli_sql_exception $exception) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $exception->getMessage()]);
}

$mysqli->close();
?>