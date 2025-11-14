<?php
// --- Force Error Reporting for Debugging ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Ensure only the owner can access this script
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    die("Access Denied. You must be an owner to download reports.");
}

require_once 'db_connect.php'; // Use your existing database connection

// --- Main Logic ---

// Get the selected week from the form submission (e.g., '2025-W46')
$selectedWeek = $_GET['week'] ?? '';

if (!preg_match('/^\d{4}-W\d{2}$/', $selectedWeek)) {
    die("Invalid week format. Please go back and select a valid week.");
}

try {
    // 1. Calculate the start and end dates of the selected week
    $year = (int) substr($selectedWeek, 0, 4);
    $weekNumber = (int) substr($selectedWeek, 6, 2);

    $startDate = new DateTime();
    $startDate->setISODate($year, $weekNumber, 1)->setTime(0, 0, 0); // Monday of the week
    $endDate = new DateTime();
    $endDate->setISODate($year, $weekNumber, 7)->setTime(23, 59, 59); // Sunday of the week

    // 2. Fetch data for the selected week from the 'receipts' table
    $stmt = $mysqli->prepare(
        "SELECT r.receipt_no, r.items, r.total, r.payment_mode, r.created_at, u.username, b.name as branch_name
         FROM receipts r
         LEFT JOIN users u ON r.created_by = u.id
         LEFT JOIN branches b ON r.branch_id = b.id
         WHERE r.created_at BETWEEN ? AND ?
         ORDER BY r.created_at ASC"
    );
    $start_date_str = $startDate->format('Y-m-d H:i:s');
    $end_date_str = $endDate->format('Y-m-d H:i:s');
    $stmt->bind_param('ss', $start_date_str, $end_date_str);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. Set headers to trigger browser download as a CSV file
    $filename = "weekly_report_{$year}_W{$weekNumber}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // 4. Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // 5. Write the CSV header row
    $headers = ['Receipt No', 'Date', 'Items Sold', 'Total', 'Payment', 'Branch', 'Cashier'];
    fputcsv($output, $headers);

    // 6. Loop through the database results and write each row to the CSV
    while ($row = $result->fetch_assoc()) {
        $items_sold = json_decode($row['items'], true);
        $item_details = [];
        if (is_array($items_sold)) {
            foreach ($items_sold as $item) {
                $item_details[] = "{$item['qty']}x {$item['name']} (Size: {$item['size']})"; // Newline separated items
            }
        }

        $csvRow = [$row['receipt_no'], $row['created_at'], implode("\n", $item_details), $row['total'], $row['payment_mode'], $row['branch_name'] ?? 'N/A', $row['username'] ?? 'N/A'];
        fputcsv($output, $csvRow);
    }

    exit;

} catch (Exception $e) {
    // A simple error handler
    error_log($e->getMessage());
    die('Error: Could not generate the report. Please check the logs or contact support.');
}