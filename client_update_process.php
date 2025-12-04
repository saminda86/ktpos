<?php
// File Name: client_update_process.php (Rating Update Final Fix)

header('Content-Type: application/json; charset=utf-8');

// Session ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection (PDO)
try {
    $host = 'localhost'; 
    $db   = 'kawdu_bill_system'; 
    $user = 'root';              
    $pass = 'admin';               
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'"); 
} catch (\PDOException $e) {
     echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'DB සම්බන්ධතා ගැටලුවකි.', 'icon' => 'error']);
     exit;
}

// Data Retrieval
$client_id = isset($_POST['client_id']) ? $_POST['client_id'] : 0; 
$client_name = isset($_POST['client_name']) ? $_POST['client_name'] : null;
$phone = isset($_POST['phone']) ? $_POST['phone'] : null;
$email = isset($_POST['email']) ? $_POST['email'] : null;
$address = isset($_POST['address']) ? $_POST['address'] : null;
$whatsapp = isset($_POST['whatsapp']) ? $_POST['whatsapp'] : null;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 5; // 🛑 Rating Value Retrieve කිරීම

if (empty($client_id) || !is_numeric($client_id) || intval($client_id) === 0) {
    echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'Client ID එක නිවැරදි නැත. යාවත්කාලීන කිරීමට Client ID එකක් අවශ්‍යයි.', 'icon' => 'error']);
    exit;
}

$client_id = intval($client_id); 

// 🛑 PHONE & WHATSAPP VALIDATION AND CLEANING 🛑
$cleaned_phone = preg_replace('/\s+/', '', $phone); 
$cleaned_phone = preg_replace('/[^0-9+]/', '', $cleaned_phone); 

$cleaned_whatsapp = NULL;
if (!empty($whatsapp)) {
    $cleaned_whatsapp = preg_replace('/\s+/', '', $whatsapp);
    $cleaned_whatsapp = preg_replace('/[^0-9+]/', '', $cleaned_whatsapp);
    if (!preg_match('/^[0-9+]{8,15}$/', $cleaned_whatsapp)) {
        echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'WhatsApp අංකය නිවැරදි නැත.', 'icon' => 'warning']);
        exit;
    }
}

// -----------------------------------------------------------
// UNIQUE CHECK BEFORE UPDATE 
// ... (Unique check logic remains the same: Phone, WhatsApp, Name) ...
// -----------------------------------------------------------

// 1. WhatsApp Number Unique Check
if (!empty($cleaned_whatsapp)) {
    $sql_check_whatsapp = "SELECT client_id FROM clients WHERE whatsapp = :whatsapp AND client_id != :id LIMIT 1";
    $stmt_check_whatsapp = $pdo->prepare($sql_check_whatsapp);
    $stmt_check_whatsapp->execute([':whatsapp' => $cleaned_whatsapp, ':id' => $client_id]);
    
    if ($stmt_check_whatsapp->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => '⚠️ දෝෂය: ඔබ ඇතුළත් කළ **WhatsApp අංකය** දැනටමත් වෙනත් Client කෙනෙකුට අයත් වේ.', 'icon' => 'error']);
        exit;
    }
}

// 2. Phone Number Unique Check
$sql_check_phone = "SELECT client_id FROM clients WHERE phone = :phone AND client_id != :id LIMIT 1";
$stmt_check_phone = $pdo->prepare($sql_check_phone);
$stmt_check_phone->execute([':phone' => $cleaned_phone, ':id' => $client_id]);

if ($stmt_check_phone->rowCount() > 0) {
    echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => '⚠️ දෝෂය: ඔබ ඇතුළත් කළ **දුරකථන අංකය** දැනටමත් වෙනත් Client කෙනෙකුට අයත් වේ.', 'icon' => 'error']);
    exit;
}

// 3. Client Name Unique Check
$sql_check_name = "SELECT client_id FROM clients WHERE client_name = :name AND client_id != :id LIMIT 1";
$stmt_check_name = $pdo->prepare($sql_check_name);
$stmt_check_name->execute([':name' => $client_name, ':id' => $client_id]);

if ($stmt_check_name->rowCount() > 0) {
    echo json_encode(['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => '⚠️ දෝෂය: ඔබ ඇතුළත් කළ **Client නාමය** දැනටමත් පවතී.', 'icon' => 'error']);
    exit;
}
// -----------------------------------------------------------

// 🛑🛑🛑 UPDATE SQL Query (Rating ඇතුළත් කර ඇත) 🛑🛑🛑
$sql = "UPDATE clients 
        SET client_name = :name, 
            phone = :phone, 
            email = :email, 
            address = :address,
            whatsapp = :whatsapp,
            rating = :rating  
        WHERE client_id = :id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $client_name,
        ':phone' => $cleaned_phone,
        ':email' => $email,
        ':address' => $address,
        ':whatsapp' => $cleaned_whatsapp, 
        ':rating' => $rating, // 🛑 Rating execute කිරීම
        ':id' => $client_id
    ]);
    
    // Success Response (Sweet Alert will show this)
    $response = [
        'status' => 'success', 
        'title' => 'සාර්ථකයි!', 
        'message' => 'Client දත්ත සාර්ථකව යාවත්කාලීන කරන ලදි!', 
        'icon' => 'success'
    ];

} catch (PDOException $e) {
    // Fallback for general errors 
    $response = [
        'status' => 'error', 
        'title' => 'දෝෂයක්!', 
        'message' => 'දත්ත යාවත්කාලීන කිරීමේ නොදන්නා දෝෂයක් සිදුවිය.', 
        'icon' => 'error'
    ];
}

echo json_encode($response);
exit;
?>