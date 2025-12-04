<?php
// File Name: delete_invoice.php (Script to delete invoice and return stock)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';
require_once 'invoice_helpers.php'; // Include helpers

// -----------------------------------------------------------
// Security Checks
// -----------------------------------------------------------

// 1. Admin-Only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    $_SESSION['error_message'] = "Delete කිරීමට අවසර ඇත්තේ පරිපාලක (Administrator) හට පමණි.";
    header('Location: invoices.php');
    exit();
}

// 2. Check ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid Invoice ID.";
    header('Location: invoices.php');
    exit();
}
$invoice_id = intval($_GET['id']);

// -----------------------------------------------------------
// Deletion Logic
// -----------------------------------------------------------
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // This error is critical, set session message and redirect
    $_SESSION['error_message'] = "Database connection failed: " . $conn->connect_error;
    header('Location: invoices.php');
    exit();
}

$conn->begin_transaction();

try {
    // --- 1. Return Stock From Invoice ---
    // This helper function gets items, loops, and updates stock.
    $returned_items = returnStockFromInvoice($conn, $invoice_id);
    
    if ($returned_items === null) {
        // Error message is set inside the helper function
        throw new Exception($_SESSION['error_message'] ?? "Failed to return stock.");
    }

    // --- 2. Delete Invoice Items (Handled by Foreign Key ON DELETE CASCADE) ---
    // The `invoice_items` table was set with ON DELETE CASCADE.
    // So, we only need to delete from the `invoices` table.
    
    // --- 3. Delete Main Invoice ---
    $sql_delete_invoice = "DELETE FROM invoices WHERE invoice_id = ?";
    $stmt_delete = $conn->prepare($sql_delete_invoice);
    if (!$stmt_delete) {
        throw new Exception("Failed to prepare invoice deletion: " . $conn->error);
    }
    
    $stmt_delete->bind_param("i", $invoice_id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Failed to execute invoice deletion: " . $stmt_delete->error);
    }
    
    $affected_rows = $stmt_delete->affected_rows;
    $stmt_delete->close();
    
    if ($affected_rows === 0) {
        throw new Exception("Invoice ID {$invoice_id} not found or already deleted.");
    }

    // --- 4. Commit ---
    $conn->commit();
    $_SESSION['success_message'] = "Invoice (ID: {$invoice_id}) successfully deleted. Stock for " . count($returned_items) . " item types has been returned.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Error deleting invoice: " . $e->getMessage();
}

$conn->close();
header('Location: invoices.php');
exit();
?>