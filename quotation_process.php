<?php
// File Name: quotation_process.php
// Description: Handles AJAX requests for Quotations (Create, Update, Delete, Status Change)

header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database Connection Failed']);
    exit;
}

$action = $_POST['action'] ?? '';

// --- HELPER: GENERATE QUOTATION NUMBER ---
function getNextQuoteNumber($conn) {
    $prefix = 'QUO-' . date('Y') . '-';
    $res = $conn->query("SELECT quotation_number FROM quotations WHERE quotation_number LIKE '$prefix%' ORDER BY quotation_id DESC LIMIT 1");
    $next = 1;
    if($res->num_rows > 0) {
        $parts = explode('-', $res->fetch_assoc()['quotation_number']);
        $next = intval(end($parts)) + 1;
    }
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// ------------------------------------------------------------------
// ACTION 1: CREATE QUOTATION
// ------------------------------------------------------------------
if ($action === 'create') {
    $client = intval($_POST['client_id']);
    $user = $_SESSION['user_id'];
    $date = $_POST['quotation_date'];
    $valid = !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;
    $sub = floatval($_POST['sub_total']);
    $tax = floatval($_POST['tax_amount']);
    $grand = floatval($_POST['grand_total']);
    $terms = $_POST['quotation_terms'];
    $items = $_POST['items'] ?? [];
    
    $num = getNextQuoteNumber($conn);

    $conn->begin_transaction();
    try {
        // Insert Main Quotation
        $stmt = $conn->prepare("INSERT INTO quotations (quotation_number, client_id, user_id, quotation_date, valid_until, sub_total, tax_amount, grand_total, status, quotation_terms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
        $stmt->bind_param("siissddds", $num, $client, $user, $date, $valid, $sub, $tax, $grand, $terms);
        $stmt->execute();
        $qid = $conn->insert_id;

        // Insert Items
        $stmt_item = $conn->prepare("INSERT INTO quotation_items (quotation_id, product_id, item_name, quantity, unit_price, buy_price) VALUES (?, ?, ?, ?, ?, ?)");
        foreach($items as $i) {
            $buy = isset($i['buy_price']) ? floatval($i['buy_price']) : 0; 
            $stmt_item->bind_param("iisddd", $qid, $i['product_id'], $i['item_name'], $i['quantity'], $i['unit_price'], $buy);
            $stmt_item->execute();
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Quotation Created Successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------
// ACTION 2: UPDATE STATUS (Mark as Accepted/Rejected)
// ------------------------------------------------------------------
elseif ($action === 'update_status') {
    $qid = intval($_POST['id']);
    $status = $_POST['status'];
    
    // Validate Status
    if (!in_array($status, ['Accepted', 'Rejected', 'Pending'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Status']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE quotations SET status = ? WHERE quotation_id = ?");
    $stmt->bind_param("si", $status, $qid);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => "Quotation marked as $status"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $conn->error]);
    }
    $stmt->close();
}

// ------------------------------------------------------------------
// ACTION 3: FETCH SINGLE (For Edit)
// ------------------------------------------------------------------
elseif ($action === 'fetch_single') {
    $qid = intval($_POST['id']);
    $q = $conn->query("SELECT * FROM quotations WHERE quotation_id=$qid")->fetch_assoc();
    
    $items = [];
    $res = $conn->query("SELECT * FROM quotation_items WHERE quotation_id=$qid");
    while($row = $res->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $q, 'items' => $items]);
}

// ------------------------------------------------------------------
// ACTION 4: UPDATE QUOTATION (Edit Save)
// ------------------------------------------------------------------
elseif ($action === 'update') {
    $qid = intval($_POST['quotation_id']);
    $client = intval($_POST['client_id']);
    $date = $_POST['quotation_date'];
    $valid = !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;
    $sub = floatval($_POST['sub_total']);
    $tax = floatval($_POST['tax_amount']);
    $grand = floatval($_POST['grand_total']);
    $terms = $_POST['quotation_terms'];
    $items = $_POST['items'] ?? [];

    $conn->begin_transaction();
    try {
        // Update Main Details
        $stmt = $conn->prepare("UPDATE quotations SET client_id=?, quotation_date=?, valid_until=?, sub_total=?, tax_amount=?, grand_total=?, quotation_terms=? WHERE quotation_id=?");
        $stmt->bind_param("issdddsi", $client, $date, $valid, $sub, $tax, $grand, $terms, $qid);
        $stmt->execute();

        // Delete Old Items
        $conn->query("DELETE FROM quotation_items WHERE quotation_id=$qid");
        
        // Insert New Items
        $stmt_item = $conn->prepare("INSERT INTO quotation_items (quotation_id, product_id, item_name, quantity, unit_price, buy_price) VALUES (?, ?, ?, ?, ?, ?)");
        foreach($items as $i) {
            $buy = isset($i['buy_price']) ? floatval($i['buy_price']) : 0;
            $stmt_item->bind_param("iisddd", $qid, $i['product_id'], $i['item_name'], $i['quantity'], $i['unit_price'], $buy);
            $stmt_item->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Quotation Updated!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ------------------------------------------------------------------
// ACTION 5: DELETE QUOTATION
// ------------------------------------------------------------------
elseif ($action === 'delete') {
    $qid = intval($_POST['id']);
    if($_SESSION['user_type'] === 'Admin') {
        $conn->query("DELETE FROM quotations WHERE quotation_id=$qid");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    }
}

$conn->close();
?>