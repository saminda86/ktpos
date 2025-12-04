<?php
// File Name: invoice_helpers.php
// Contains helper functions for Invoice Management

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates a unique invoice number (e.g., KINV-20250001)
 *
 * @param mysqli $conn The database connection
 * @return string A unique invoice number
 */
function generateInvoiceNumber($conn) {
    $prefix = 'KINV-';
    $year = date('Y');
    
    $sql = "SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $search_prefix = $prefix . $year . '%';
    $stmt->bind_param("s", $search_prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $new_number = 1;
    if ($result->num_rows > 0) {
        $last_invoice = $result->fetch_assoc();
        $last_num = (int)str_replace($prefix . $year, '', $last_invoice['invoice_number']);
        $new_number = $last_num + 1;
    }
    
    $stmt->close();
    return $prefix . $year . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

/**
 * Adjusts product stock when a new invoice is created.
 *
 * @param mysqli $conn The database connection
 * @param array $items Array of items from the form
 * @return bool True on success, false on failure
 */
function adjustStockOnCreate($conn, $items) {
    $sql = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error_message'] = "Stock update preparation failed: " . $conn->error;
        return false;
    }
    
    foreach ($items as $item) {
        $quantity = floatval($item['quantity']);
        $product_id = intval($item['product_id']);
        
        // Only adjust stock for valid product IDs (not services with ID 0)
        // We check buy_price > 0 to identify 'Products' vs 'Services'
        if ($product_id > 0 && floatval($item['buy_price']) > 0) {
            $stmt->bind_param("di", $quantity, $product_id);
            if (!$stmt->execute()) {
                $_SESSION['error_message'] = "Stock update failed for product ID {$product_id}: " . $stmt->error;
                $stmt->close();
                return false;
            }
        }
    }
    $stmt->close();
    return true;
}

/**
 * Returns stock to inventory when an invoice is deleted or updated.
 *
 * @param mysqli $conn The database connection
 * @param int $invoice_id The invoice ID
 * @return array|null The list of old items (for updates) or null on failure
 */
function returnStockFromInvoice($conn, $invoice_id) {
    // 1. Get old items
    $sql_get_items = "SELECT product_id, quantity, buy_price FROM invoice_items WHERE invoice_id = ?";
    $stmt_get = $conn->prepare($sql_get_items);
    if (!$stmt_get) {
        $_SESSION['error_message'] = "Failed to prepare item fetch: " . $conn->error;
        return null;
    }
    $stmt_get->bind_param("i", $invoice_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $old_items = [];
    while ($row = $result->fetch_assoc()) {
        $old_items[] = $row;
    }
    $stmt_get->close();

    // 2. Prepare stock update
    $sql_update_stock = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?";
    $stmt_update = $conn->prepare($sql_update_stock);
    if (!$stmt_update) {
        $_SESSION['error_message'] = "Failed to prepare stock return: " . $conn->error;
        return null;
    }

    // 3. Loop and return stock
    foreach ($old_items as $item) {
        $quantity = floatval($item['quantity']);
        $product_id = intval($item['product_id']);
        
        // Only return stock for 'Products' (identified by buy_price > 0 at time of sale)
        if ($product_id > 0 && floatval($item['buy_price']) > 0) {
            $stmt_update->bind_param("di", $quantity, $product_id);
            if (!$stmt_update->execute()) {
                $_SESSION['error_message'] = "Stock return failed for product ID {$product_id}: " . $stmt_update->error;
                $stmt_update->close();
                return null;
            }
        }
    }
    
    $stmt_update->close();
    return $old_items; // Return the list of items
}
?>