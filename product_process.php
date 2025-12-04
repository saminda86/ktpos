<?php
// File Name: invoice_process.php

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
     echo json_encode(['status' => 'error', 'message' => 'You must be logged in to perform this action.']);
     exit;
}
$user_id = $_SESSION['user_id'];

// --- Database Connection (PDO) ---
try {
    $host = 'localhost'; 
    $db   = 'kawdu_bill_system'; // 🛑 User's DB
    $user = 'root';              
    $pass = 'admin';  
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
     exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// -----------------------------------------------------------
// 1. ACTION: 'create' (Create New Invoice)
// -----------------------------------------------------------
if ($action === 'create') {
    // 1. Sanitize Main Invoice Data (From sample create_invoice.php)
    $client_id = (int)$_POST['client_id'];
    $invoice_date = $_POST['invoice_date'];
    $grand_total = (float)$_POST['grand_total']; 
    $sub_total = (float)$_POST['sub_total'];
    $tax_amount = 0.00; // 'Sample' එකේ මෙන් Tax 0.00
    $payment_status = $_POST['payment_status'];
    
    // Item details (From sample create_invoice.php)
    $product_ids = $_POST['product_id'] ?? [];
    $serial_numbers = $_POST['serial_number'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $buy_prices = $_POST['buy_price'] ?? [];
    
    // Invoice Number Generation (From sample create_invoice.php)
    $stmt_max = $pdo->query("SELECT MAX(id) AS max_id FROM invoices");
    $next_id = ($stmt_max->fetch()['max_id'] ?? 0) + 1;
    $invoice_number = "INV-" . str_pad($next_id, 6, '0', STR_PAD_LEFT);

    try {
        $pdo->beginTransaction();
        
        // 2. Insert into invoices table
        $sql_invoice = "INSERT INTO invoices (invoice_number, client_id, user_id, invoice_date, sub_total, tax_amount, grand_total, payment_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; 
        $stmt_invoice = $pdo->prepare($sql_invoice);
        $stmt_invoice->execute([$invoice_number, $client_id, $user_id, $invoice_date, $sub_total, $tax_amount, $grand_total, $payment_status]);
        $invoice_id = $pdo->lastInsertId();
        
        // 3. Insert into invoice_items table (From sample create_invoice.php)
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, serial_number, quantity, unit_price, buy_price) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_items = $pdo->prepare($sql_items);

        foreach ($product_ids as $key => $product_id) {
            $serial_num = $serial_numbers[$key] ?? NULL;
            $qty = (int)$quantities[$key];
            $price = (float)$unit_prices[$key];
            $buy_price = (float)$buy_prices[$key];

            if ($qty > 0) {
                $stmt_items->execute([$invoice_id, $product_id, $serial_num, $qty, $price, $buy_price]);
                
                // 4. Update Stock (From user's product_process.php logic)
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?")
                    ->execute([$qty, $product_id, $qty]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Invoice $invoice_number successfully created!"]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => "Transaction failed: " . $e->getMessage()]);
    }
}

// -----------------------------------------------------------
// 2. ACTION: 'fetch_for_edit' (Get data for Edit Modal)
// -----------------------------------------------------------
elseif ($action === 'fetch_for_edit') {
    $invoice_id = (int)$_POST['invoice_id'];
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Invoice ID.']);
        exit;
    }

    try {
        // 'Sample' (edit_invoice.php) එකේ මෙන් දත්ත ලබා ගැනීම
        $stmt_inv = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt_inv->execute([$invoice_id]);
        $invoice_data = $stmt_inv->fetch();

        $stmt_items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $stmt_items->execute([$invoice_id]);
        $invoice_items_data = $stmt_items->fetchAll();

        if (!$invoice_data) {
            echo json_encode(['status' => 'error', 'message' => 'Invoice not found.']);
        } else {
            echo json_encode(['status' => 'success', 'invoice' => $invoice_data, 'items' => $invoice_items_data]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// -----------------------------------------------------------
// 3. ACTION: 'update' (Update Existing Invoice)
// -----------------------------------------------------------
elseif ($action === 'update') {
    $invoice_id = (int)$_POST['invoice_id'];
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Invoice ID.']);
        exit;
    }
    
    // 1. Sanitize Main Invoice Data (From sample edit_invoice.php)
    $client_id = (int)$_POST['client_id'];
    $invoice_date = $_POST['invoice_date'];
    $grand_total = (float)$_POST['grand_total']; 
    $sub_total = (float)$_POST['sub_total'];
    $tax_amount = 0.00; // Tax is 0
    $payment_status = $_POST['payment_status'];
    
    // Item details (From sample edit_invoice.php)
    $item_ids = $_POST['item_id'] ?? []; // Existing item IDs
    $product_ids = $_POST['product_id'] ?? [];
    $serial_numbers = $_POST['serial_number'] ?? []; 
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $buy_prices = $_POST['buy_price'] ?? [];

    try {
        $pdo->beginTransaction();
        
        // --- A. Update invoices table ---
        $sql_invoice = "UPDATE invoices SET client_id=?, user_id=?, invoice_date=?, sub_total=?, tax_amount=?, grand_total=?, payment_status=? WHERE id=?";
        $stmt_invoice = $pdo->prepare($sql_invoice);
        $stmt_invoice->execute([$client_id, $user_id, $invoice_date, $sub_total, $tax_amount, $grand_total, $payment_status, $invoice_id]);

        // --- B. Manage invoice_items table (From sample edit_invoice.php) ---
        
        // 1. Get original items to calculate stock adjustment
        $stmt_orig_items = $pdo->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?");
        $stmt_orig_items->execute([$invoice_id]);
        $original_items = $stmt_orig_items->fetchAll(PDO::FETCH_KEY_PAIR); // [product_id => quantity]

        // 2. Delete items that were removed from the form
        $form_item_ids_safe = implode(',', array_map('intval', array_filter($item_ids, fn($id) => $id > 0)));
        if (empty($form_item_ids_safe)) {
            $pdo->query("DELETE FROM invoice_items WHERE invoice_id = $invoice_id");
        } else {
            $pdo->query("DELETE FROM invoice_items WHERE invoice_id = $invoice_id AND id NOT IN ($form_item_ids_safe)");
        }
        
        // 3. Prepare Stock Update statements
        $stmt_stock_add = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        $stmt_stock_sub = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
        
        // 4. Reset original stock quantities
        foreach ($original_items as $prod_id => $qty) {
            $stmt_stock_add->execute([$qty, $prod_id]);
        }

        // 5. Insert/Update items from the form
        $sql_update_item = "UPDATE invoice_items SET product_id=?, serial_number=?, quantity=?, unit_price=?, buy_price=? WHERE id=?";
        $sql_insert_item = "INSERT INTO invoice_items (invoice_id, product_id, serial_number, quantity, unit_price, buy_price) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_update = $pdo->prepare($sql_update_item);
        $stmt_insert = $pdo->prepare($sql_insert_item);

        foreach ($product_ids as $key => $product_id) {
            $item_id = (int)$item_ids[$key];
            $serial_num = $serial_numbers[$key] ?? NULL;
            $qty = (int)$quantities[$key];
            $price = (float)$unit_prices[$key];
            $buy_price = (float)$buy_prices[$key];

            if ($qty > 0) {
                if ($item_id > 0) { // Update existing item
                    $stmt_update->execute([$product_id, $serial_num, $qty, $price, $buy_price, $item_id]);
                } else { // Insert new item
                    $stmt_insert->execute([$invoice_id, $product_id, $serial_num, $qty, $price, $buy_price]);
                }
                
                // 6. Deduct new stock quantity
                $stmt_stock_sub->execute([$qty, $product_id, $qty]);
                if ($stmt_stock_sub->rowCount() == 0) {
                     throw new Exception("Stock is insufficient for product ID $product_id.");
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Invoice successfully updated!']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => "Update failed: " . $e->getMessage()]);
    }
}

// -----------------------------------------------------------
// 4. ACTION: 'delete' (Delete Invoice)
// -----------------------------------------------------------
elseif ($action === 'delete') {
    if ($_SESSION['user_type'] !== 'Admin') {
         echo json_encode(['status' => 'error', 'message' => 'Only Admins can delete invoices.']);
         exit;
    }
    
    $invoice_id = (int)$_POST['invoice_id'];
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Invoice ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Get items to return stock
        $stmt_orig_items = $pdo->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?");
        $stmt_orig_items->execute([$invoice_id]);
        $original_items = $stmt_orig_items->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 2. Return stock
        $stmt_stock_add = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        foreach ($original_items as $prod_id => $qty) {
            $stmt_stock_add->execute([$qty, $prod_id]);
        }
        
        // 3. Delete invoice (CASCADE delete will handle invoice_items)
        $stmt_delete = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt_delete->execute([$invoice_id]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Invoice and all its items deleted. Stock has been returned.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
}
?>