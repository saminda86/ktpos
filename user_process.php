<?php
// File Name: user_process.php (Handles User Insert and Update via AJAX - FINAL FIX)

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🛑🛑🛑 ආරක්ෂක පරීක්ෂාව: Admin පමණක් මෙම පිටුවට ඇතුළු විය හැක 🛑🛑🛑
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
     echo json_encode(['status' => 'error', 'title' => 'අවසර නැත!', 'message' => 'මෙම ක්‍රියාවලියට අවසර ඇත්තේ පරිපාලක හට පමණි.', 'icon' => 'error']);
     exit;
}

require_once 'db_connect.php'; 

try {
    // 🛑 Database Connection (ඔබේ සැබෑ තොරතුරු)
    $host = 'localhost'; 
    $db   = 'kawdu_bill_system'; 
    $user = 'root';              
    $pass = 'admin';  // ඔබගේ මුරපදය 'admin'
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Database සම්බන්ධතාවය අසාර්ථක නම්
     echo json_encode(['status' => 'error', 'title' => 'සම්බන්ධතා දෝෂය!', 'message' => 'දත්ත ගබඩාවට සම්බන්ධ වීමේ ගැටලුවකි.', 'icon' => 'error']);
     exit;
}

// Data Retrieval
$action = isset($_POST['action']) ? $_POST['action'] : '';
$user_id = isset($_POST['user_id']) && $_POST['user_id'] != '' ? intval($_POST['user_id']) : null;
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$user_type = trim($_POST['user_type'] ?? 'User');
$status = trim($_POST['status'] ?? 'Active');

// -----------------------------------------------------------
// INSERT Logic (Adding a New User)
// -----------------------------------------------------------
if ($action === 'insert') {
    if (empty($password)) {
        echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'මුරපදය අවශ්‍යයි.', 'icon' => 'warning']);
        exit;
    }
    
    // 🛑 Password Hashing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (name, username, password, user_type, status) 
            VALUES (:name, :username, :password, :user_type, :status)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':username' => $username,
            ':password' => $hashed_password,
            ':user_type' => $user_type,
            ':status' => $status
        ]);
        
        echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'නව පරිශීලකයා සාර්ථකව ඇතුළත් කරන ලදි!', 'icon' => 'success']);

    } catch (PDOException $e) {
        // Duplicate username check (SQLSTATE 23000)
        if ($e->getCode() === '23000') {
            $msg = '⚠️ දෝෂය: මෙම **Username** එක දැනටමත් පද්ධතියේ පවතී.';
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             // 🛑 වෙනත් නොදන්නා SQL දෝෂ
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත ඇතුළත් කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය. (' . $e->getCode() . ')', 'icon' => 'error']);
        }
    }
} 
// -----------------------------------------------------------
// UPDATE Logic (Editing Existing User)
// -----------------------------------------------------------
elseif ($action === 'update' && $user_id !== null) {
    
    $update_fields = "name = :name, username = :username, user_type = :user_type, status = :status";
    $params = [
        ':name' => $name,
        ':username' => $username,
        ':user_type' => $user_type,
        ':status' => $status,
        ':id' => $user_id
    ];
    
    // Check if password field was filled (meaning password needs changing)
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_fields .= ", password = :password";
        $params[':password'] = $hashed_password;
    }

    $sql = "UPDATE users SET {$update_fields} WHERE user_id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Self-Update Check: If current user updated their own user_type/status, update session too
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['user_type'] = $user_type; 
        }
        
        echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'පරිශීලක දත්ත සාර්ථකව යාවත්කාලීන කරන ලදි!', 'icon' => 'success']);

    } catch (PDOException $e) {
        // Duplicate username check
        if ($e->getCode() === '23000') {
            $msg = '⚠️ දෝෂය: මෙම **Username** එක දැනටමත් වෙනත් පරිශීලකයෙකු සතුය.';
            echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂය!', 'message' => $msg, 'icon' => 'error']);
        } else {
             // 🛑 වෙනත් නොදන්නා SQL දෝෂ
             echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'දත්ත යාවත්කාලීන කිරීමේදී නොදන්නා දෝෂයක් සිදුවිය. (' . $e->getCode() . ')', 'icon' => 'error']);
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