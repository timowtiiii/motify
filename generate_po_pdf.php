<?php
require('fpdf/fpdf.php');
require('db_connect.php');

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];

    // Fetch order details
    $stmt = $conn->prepare("SELECT o.*, s.name AS supplier_name, s.address AS supplier_address FROM orders o JOIN suppliers s ON o.supplier_id = s.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if ($order) {
        // Fetch order items
        $stmt = $conn->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Create PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Purchase Order');
        $pdf->Ln(15);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Order ID: ' . $order['id']);
        $pdf->Ln();
        $pdf->Cell(40, 10, 'Date: ' . $order['order_date']);
        $pdf->Ln();
        $pdf->Cell(40, 10, 'Supplier: ' . $order['supplier_name']);
        $pdf->Ln();
        $pdf->Cell(40, 10, 'Address: ' . $order['supplier_address']);
        $pdf->Ln(15);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 10, 'Product', 1);
        $pdf->Cell(40, 10, 'Quantity', 1);
        $pdf->Cell(40, 10, 'Price', 1);
        $pdf->Cell(30, 10, 'Total', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        $total = 0;
        foreach ($items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $pdf->Cell(80, 10, $item['product_name'], 1);
            $pdf->Cell(40, 10, $item['quantity'], 1);
            $pdf->Cell(40, 10, '$' . number_format($item['price'], 2), 1);
            $pdf->Cell(30, 10, '$' . number_format($item_total, 2), 1);
            $pdf->Ln();
            $total += $item_total;
        }

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(160, 10, 'Grand Total', 1);
        $pdf->Cell(30, 10, '$' . number_format($total, 2), 1);
        $pdf->Ln();

        $pdf->Output('I', 'PO_' . $order_id . '.pdf');
    } else {
        echo "Order not found.";
    }
} else {
    echo "No order ID specified.";
}
?>
