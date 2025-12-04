<?php
// File Name: ktpos/supplier_process.php (Final Version: Handles Supplier CRUD)

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
     echo json_encode(['status' => 'error', 'title' => 'අවසර නැත!', 'message' => 'මෙම ක්‍රියාවලියට ප්‍රවේශ වීමට ඔබ ලොග් විය යුතුය.', 'icon' => 'error']);
     exit;
}

require_once 'db_connect.php'; 

// -----------------------------------------------------------
// Database Connection (PDO)
// -----------------------------------------------------------
try {
    $host = 'localhost'; 
    $db   = 'kawdu_bill_system'; 
    $user = 'root';              
    $pass = 'admin'; 
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
     echo json_encode(['status' => 'error', 'title' => 'සම්බන්ධතා දෝෂය!', 'message' => 'දත්ත ගබඩාවට සම්බන්ධ වීමේ ගැටලුවකි.', 'icon' => 'error']);
     exit;
}

$action = $_POST['action'] ?? '';

// -----------------------------------------------------------
// 1. INSERT Logic (Add New Supplier)
// -----------------------------------------------------------
if ($action === 'insert') {
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($supplier_name) || empty($contact_no)) {
        echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'Supplier Name සහ Contact No අත්‍යවශ්‍ය වේ.', 'icon' => 'warning']);
        exit;
    }
    
    $final_address = (empty($address) || trim($address) === '') ? NULL : $address;

    $sql = "INSERT INTO suppliers (supplier_name, contact_no, address) VALUES (:name, :contact, :address)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $supplier_name,
            ':contact' => $contact_no,
            ':address' => $final_address
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        echo json_encode([
            'status' => 'success', 
            'title' => 'සාර්ථකයි!', 
            'message' => 'නව Supplier කෙනෙකු සාර්ථකව එකතු කරන ලදි!', 
            'icon' => 'success',
            'data' => [
                'supplier_id' => $new_id,
                'supplier_name' => $supplier_name
            ]
        ]);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { 
            $msg = '⚠️ දෝෂය: ඔබ ඇතුළත් කළ **Supplier නම** හෝ **Contact No** එක දැනටමත් පවතී.';
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත ඇතුළත් කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
} 
// -----------------------------------------------------------
// 2. UPDATE Logic (Edit Existing Supplier)
// -----------------------------------------------------------
elseif ($action === 'update') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($supplier_id === 0 || empty($supplier_name) || empty($contact_no)) {
        echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'Supplier ID, නම සහ Contact No අත්‍යවශ්‍ය වේ.', 'icon' => 'warning']);
        exit;
    }
    
    $final_address = (empty($address) || trim($address) === '') ? NULL : $address;

    $sql = "UPDATE suppliers SET supplier_name = :name, contact_no = :contact, address = :address WHERE supplier_id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $supplier_name,
            ':contact' => $contact_no,
            ':address' => $final_address,
            ':id' => $supplier_id
        ]);
        
        echo json_encode([
            'status' => 'success', 
            'title' => 'සාර්ථකයි!', 
            'message' => 'Supplier දත්ත සාර්ථකව යාවත්කාලීන කරන ලදි!', 
            'icon' => 'success',
            'data' => [
                'supplier_id' => $supplier_id,
                'supplier_name' => $supplier_name
            ]
        ]);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { 
            $msg = '⚠️ දෝෂය: ඔබ ඇතුළත් කළ **Supplier නම** හෝ **Contact No** එක දැනටමත් පවතී.';
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත යාවත්කාලීන කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
}
// -----------------------------------------------------------
// 3. FETCH Logic (Get All Suppliers)
// -----------------------------------------------------------
elseif ($action === 'fetch') {
    // Fetch all supplier data including contact/address for quick edit loading
    $sql = "SELECT supplier_id, supplier_name, contact_no, address FROM suppliers ORDER BY supplier_name ASC";
    $stmt = $pdo->query($sql);
    $suppliers = $stmt->fetchAll();
    
    echo json_encode(['status' => 'success', 'data' => $suppliers]);
}
// -----------------------------------------------------------
// 4. DELETE Logic (Delete Supplier - Admin/User Check)
// -----------------------------------------------------------
elseif ($action === 'delete') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    
    if (!isset($_SESSION['user_type'])) {
        echo json_encode(['status' => 'error', 'title' => 'අවසර නැත!', 'message' => 'Supplier මැකීමට අවසර නැත.', 'icon' => 'error']);
        exit;
    }
    
    $sql = "DELETE FROM suppliers WHERE supplier_id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$supplier_id]);
        
        if ($stmt->rowCount()) {
             echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'Supplier සාර්ථකව මකා දමන ලදි!', 'icon' => 'success']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'Supplier සොයා ගැනීමට නොහැක.', 'icon' => 'warning']);
        }

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
             $msg = '⚠️ මකා දැමීම අසාර්ථකයි: මෙම Supplier ට **නිෂ්පාදන** සම්බන්ධ වී ඇත. එම නිෂ්පාදන වෙනත් Supplier කෙනෙකුට මාරු කරන්න.';
             echo json_encode(['status' => 'error', 'title' => 'FK දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'මැකීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
}
// -----------------------------------------------------------
// Invalid Request
// -----------------------------------------------------------
else {
     echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'අවලංගු ක්‍රියාකාරිත්වයක්.', 'icon' => 'error']);
}

?>