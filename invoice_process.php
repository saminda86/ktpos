<?php
// File Name: invoice_process.php
// Handles AJAX requests for Creating and Updating Invoices

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in. Please log in again.']);
    exit;
}

require_once 'db_connect.php';
require_once 'invoice_helpers.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$action = $_GET['action'] ?? '';

// -----------------------------------------------------------
// ACTION: CREATE NEW INVOICE
// -----------------------------------------------------------
if ($action === 'create') {
    
    $conn->begin_transaction();
    
    try {
        // --- 1. Get Form Data ---
        $client_id = intval($_POST['client_id']);
        $user_id = intval($_SESSION['user_id']);
        $invoice_date = $_POST['invoice_date'];
        $payment_status = $_POST['payment_status'];
        $invoice_terms = $_POST['invoice_terms'] ?? NULL;
        $sub_total = floatval($_POST['sub_total']);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $grand_total = floatval($_POST['grand_total']);
        
        $items_post = $_POST['items'] ?? [];
        
        if (empty($items_post)) {
            throw new Exception("You must add at least one item to the invoice.");
        }

        // --- 2. Generate Invoice Number ---
        $invoice_number = generateInvoiceNumber($conn);

        // --- 3. Insert into `invoices` table ---
        $sql_invoice = "INSERT INTO invoices (invoice_number, client_id, user_id, invoice_date, sub_total, tax_amount, grand_total, payment_status, invoice_terms) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("siisddsss", $invoice_number, $client_id, $user_id, $invoice_date, $sub_total, $tax_amount, $grand_total, $payment_status, $invoice_terms);
        
        if (!$stmt_invoice->execute()) {
            throw new Exception("Failed to save invoice: " . $stmt_invoice->error);
        }
        $invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        // --- 4. Insert into `invoice_items` table ---
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, item_name, serial_number, quantity, unit_price, buy_price) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);
        
        foreach ($items_post as $item) { // $items_post is already an array
            $stmt_items->bind_param(
                "iisssdd",
                $invoice_id,
                $item['product_id'],
                $item['item_name'],
                $item['serial_number'],
                $item['quantity'],
                $item['unit_price'],
                $item['buy_price']
            );
            if (!$stmt_items->execute()) {
                throw new Exception("Failed to save invoice item: " . $stmt_items->error);
            }
        }
        $stmt_items->close();
        
        // --- 5. Adjust Product Stock ---
        if (!adjustStockOnCreate($conn, $items_post)) {
            throw new Exception($_SESSION['error_message'] ?? "Stock adjustment failed.");
        }

        // --- 6. Commit Transaction ---
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Invoice ({$invoice_number}) created successfully!"]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => "Error creating invoice: " . $e->getMessage()]);
    }
    
    $conn->close();
    exit;
}

// -----------------------------------------------------------
// ACTION: UPDATE EXISTING INVOICE
// -----------------------------------------------------------
if ($action === 'update') {
    
    $invoice_id = intval($_GET['id'] ?? 0);
    if ($invoice_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Invoice ID.']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // --- 1. Get Form Data ---
        $client_id = intval($_POST['client_id']);
        $invoice_date = $_POST['invoice_date'];
        $payment_status = $_POST['payment_status'];
        $invoice_terms = $_POST['invoice_terms'] ?? NULL;
        $sub_total = floatval($_POST['sub_total']);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $grand_total = floatval($_POST['grand_total']);
        
        $new_items = $_POST['items'] ?? [];
        
        if (empty($new_items)) {
            throw new Exception("You must add at least one item to the invoice.");
        }

        // --- 2. Return Old Stock ---
        $old_items = returnStockFromInvoice($conn, $invoice_id);
        if ($old_items === null) {
            throw new Exception($_SESSION['error_message'] ?? "Failed to return old stock.");
        }

        // --- 3. Delete Old Invoice Items ---
        $stmt_delete = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt_delete->bind_param("i", $invoice_id);
        if (!$stmt_delete->execute()) {
            throw new Exception("Failed to delete old items: " . $stmt_delete->error);
        }
        $stmt_delete->close();

        // --- 4. Update `invoices` table ---
        $sql_invoice = "UPDATE invoices SET client_id = ?, invoice_date = ?, sub_total = ?, tax_amount = ?, 
                        grand_total = ?, payment_status = ?, invoice_terms = ? 
                        WHERE invoice_id = ?";
        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("isddsssi", $client_id, $invoice_date, $sub_total, $tax_amount, $grand_total, $payment_status, $invoice_terms, $invoice_id);
        
        if (!$stmt_invoice->execute()) {
            throw new Exception("Failed to update invoice: " . $stmt_invoice->error);
        }
        $stmt_invoice->close();

        // --- 5. Insert New `invoice_items` ---
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, item_name, serial_number, quantity, unit_price, buy_price) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);
        
        foreach ($new_items as $item) {
            $stmt_items->bind_param(
                "iisssdd",
                $invoice_id,
                $item['product_id'],
                $item['item_name'],
                $item['serial_number'],
                $item['quantity'],
                $item['unit_price'],
                $item['buy_price']
            );
            if (!$stmt_items->execute()) {
                throw new Exception("Failed to save new invoice item: " . $stmt_items->error);
            }
        }
        $stmt_items->close();
        
        // --- 6. Deduct New Stock ---
        if (!adjustStockOnCreate($conn, $new_items)) { // Re-using create function
            throw new Exception($_SESSION['error_message'] ?? "New stock adjustment failed.");
        }

        // --- 7. Commit Transaction ---
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Invoice updated successfully! Stock has been readjusted."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => "Error updating invoice: " . $e->getMessage()]);
    }
    
    $conn->close();
    exit;
}

// -----------------------------------------------------------
// ACTION: INVALID
// -----------------------------------------------------------
echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
exit;
?>