<?php
// File Name: invoice_load.php
// Handles AJAX request to fetch data for the EDIT modal

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

// 2. ID Check
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Invoice ID.']);
    exit;
}
$invoice_id = intval($_GET['id']);

require_once 'db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

try {
    // 1. Fetch Invoice Details
    $stmt_invoice = $conn->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
    $stmt_invoice->bind_param("i", $invoice_id);
    $stmt_invoice->execute();
    $invoice_result = $stmt_invoice->get_result();
    if ($invoice_result->num_rows === 0) {
        throw new Exception("Invoice not found.");
    }
    $invoice = $invoice_result->fetch_assoc();
    $stmt_invoice->close();

    // 2. Fetch Invoice Items (with image_path from products table)
    $items = [];
    $sql_items = "SELECT ii.*, p.image_path 
                  FROM invoice_items ii
                  LEFT JOIN products p ON ii.product_id = p.product_id
                  WHERE ii.invoice_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $invoice_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();

    // 3. Send data
    $response_data = [
        'invoice' => $invoice,
        'items' => $items
    ];
    
    echo json_encode(['status' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
exit;
?>