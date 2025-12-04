<?php
// File Name: ktpos/category_process.php (Final Version: Handles Category CRUD)

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
// 1. INSERT Logic (Add New Category)
// -----------------------------------------------------------
if ($action === 'insert') {
    $category_name = trim($_POST['category_name'] ?? '');

    if (empty($category_name)) {
        echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'Category නම අත්‍යවශ්‍ය වේ.', 'icon' => 'warning']);
        exit;
    }

    $sql = "INSERT INTO categories (category_name) VALUES (:name)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':name' => $category_name]);
        
        $new_id = $pdo->lastInsertId();
        
        echo json_encode([
            'status' => 'success', 
            'title' => 'සාර්ථකයි!', 
            'message' => 'නව Category එක සාර්ථකව එකතු කරන ලදි!', 
            'icon' => 'success',
            'data' => [
                'category_id' => $new_id,
                'category_name' => $category_name
            ]
        ]);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { 
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => '⚠️ දෝෂය: මෙම **Category නම** දැනටමත් පවතී.', 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත ඇතුළත් කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
} 
// -----------------------------------------------------------
// 2. UPDATE Logic (Edit Existing Category) 
// -----------------------------------------------------------
elseif ($action === 'update') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');

    if ($category_id === 0 || empty($category_name)) {
        echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'Category ID සහ නම අත්‍යවශ්‍ය වේ.', 'icon' => 'warning']);
        exit;
    }

    $sql = "UPDATE categories SET category_name = :name WHERE category_id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $category_name,
            ':id' => $category_id
        ]);
        
        echo json_encode([
            'status' => 'success', 
            'title' => 'සාර්ථකයි!', 
            'message' => 'Category එක සාර්ථකව යාවත්කාලීන කරන ලදි!', 
            'icon' => 'success',
            'data' => [
                'category_id' => $category_id,
                'category_name' => $category_name
            ]
        ]);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { 
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => '⚠️ දෝෂය: මෙම **Category නම** දැනටමත් පවතී.', 'icon' => 'error']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත යාවත්කාලීන කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය.', 'icon' => 'error']);
        }
    }
}
// -----------------------------------------------------------
// 3. FETCH Logic (Get All Categories)
// -----------------------------------------------------------
elseif ($action === 'fetch') {
    $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();
    
    echo json_encode(['status' => 'success', 'data' => $categories]);
}
// -----------------------------------------------------------
// 4. DELETE Logic (Delete Category - Admin/User Check)
// -----------------------------------------------------------
elseif ($action === 'delete') {
    $category_id = intval($_POST['category_id'] ?? 0);
    
    if (!isset($_SESSION['user_type'])) {
        echo json_encode(['status' => 'error', 'title' => 'අවසර නැත!', 'message' => 'Category මැකීමට අවසර නැත.', 'icon' => 'error']);
        exit;
    }
    
    $sql = "DELETE FROM categories WHERE category_id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id]);
        
        if ($stmt->rowCount()) {
             echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'Category එක සාර්ථකව මකා දමන ලදි!', 'icon' => 'success']);
        } else {
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'Category එක සොයා ගැනීමට නොහැක.', 'icon' => 'warning']);
        }

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
             $msg = '⚠️ මකා දැමීම අසාර්ථකයි: මෙම Category එකට **නිෂ්පාදන** සම්බන්ධ වී ඇත. එම නිෂ්පාදන වෙනත් Category එකකට මාරු කරන්න.';
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