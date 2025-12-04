<?php
// File Name: ktpos/service_process.php (Final Version: Handles Service Insert, Update, Delete)

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
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     echo json_encode(['status' => 'error', 'title' => 'සම්බන්ධතා දෝෂය!', 'message' => 'දත්ත ගබඩාවට සම්බන්ධ වීමේ ගැටලුවකි.', 'icon' => 'error']);
     exit;
}

// -----------------------------------------------------------
// Helper Function: Generate Unique Service Code
// -----------------------------------------------------------
function generateUniqueServiceCode($pdo) {
    $prefix = "KWS-";
    $code = '';
    
    do {
        $random_num = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $code = $prefix . $random_num;
        
        $stmt = $pdo->prepare("SELECT service_code FROM services WHERE service_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->rowCount() > 0);
    
    return $code;
}

// -----------------------------------------------------------
// Data Retrieval and Validation
// -----------------------------------------------------------
$action = $_POST['action'] ?? '';
$service_id = isset($_POST['service_id']) && $_POST['service_id'] != '' ? intval($_POST['service_id']) : null;
$service_name = trim($_POST['service_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$sell_price = floatval($_POST['sell_price'] ?? 0);
$category_id = intval($_POST['category_id'] ?? 0);

if (empty($service_name) || $category_id === 0) {
    echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'Service Name සහ Category අත්‍යවශ්‍ය වේ.', 'icon' => 'warning']);
    exit;
}

if ($sell_price < 0) {
     echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'මුදල් අගයන් සෘණ විය නොහැක.', 'icon' => 'warning']);
    exit;
}

// -----------------------------------------------------------
// 1. INSERT Logic (Adding a New Service)
// -----------------------------------------------------------
if ($action === 'insert') {
    
    $service_code = generateUniqueServiceCode($pdo);

    $sql = "INSERT INTO services (service_code, service_name, description, sell_price, category_id) 
            VALUES (:code, :name, :desc, :sell_price, :cat_id)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':code' => $service_code,
            ':name' => $service_name,
            ':desc' => $description,
            ':sell_price' => $sell_price,
            ':cat_id' => $category_id
        ]);
        
        echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'නව සේවාව සාර්ථකව ඇතුළත් කරන ලදි!', 'icon' => 'success']);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { 
            $msg = '⚠️ දත්ත දෝෂය: ඔබ ඇතුළත් කළ **Service Name** හෝ **Code** එක දැනටමත් පවතී.';
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත ඇතුළත් කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
} 
// -----------------------------------------------------------
// 2. UPDATE Logic (Editing Existing Service)
// -----------------------------------------------------------
elseif ($action === 'update' && $service_id !== null) {
    
    $sql = "UPDATE services SET 
            service_name = :name, 
            description = :desc, 
            sell_price = :sell_price, 
            category_id = :cat_id
            WHERE service_id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $service_name,
            ':desc' => $description,
            ':sell_price' => $sell_price,
            ':cat_id' => $category_id,
            ':id' => $service_id
        ]);
        
        echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'සේවා දත්ත සාර්ථකව යාවත්කාලීන කරන ලදි!', 'icon' => 'success']);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $msg = '⚠️ දත්ත දෝෂය: ඔබ ඇතුළත් කළ **Service Name** දැනටමත් පවතී.';
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත යාවත්කාලීන කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
}
// -----------------------------------------------------------
// 3. DELETE Logic (Deleting Existing Service - Admin Only)
// -----------------------------------------------------------
elseif ($action === 'delete') {
    if ($_SESSION['user_type'] !== 'Admin') {
        echo json_encode(['status' => 'error', 'title' => 'අවසර නැත!', 'message' => 'සේවා මැකීමට අවසර ඇත්තේ පරිපාලක (Admin) හට පමණි.', 'icon' => 'error']);
        exit;
    }
    
    if ($service_id === null) {
        echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'මැකීමට Service ID අත්‍යවශ්‍ය වේ.', 'icon' => 'error']);
        exit;
    }

    $sql = "DELETE FROM services WHERE service_id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$service_id]);
        
        if ($stmt->rowCount()) {
             echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'සේවාව සාර්ථකව මකා දමන ලදි!', 'icon' => 'success']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'සේවාව සොයා ගැනීමට නොහැක, නැතහොත් එය දැනටමත් මකා දමා ඇත.', 'icon' => 'warning']);
        }

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
             $msg = '⚠️ මකා දැමීම අසාර්ථකයි: මෙම සේවාව **Invoices** හෝ **Quotation** සඳහා දැනටමත් භාවිත කර ඇත.';
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