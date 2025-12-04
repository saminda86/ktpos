<?php
// File Name: client_insert_process.php (Final Fix: Insert Error and DB Safety)

// Set headers for JSON response and proper UTF-8 handling
header('Content-Type: application/json; charset=utf-8');

// --- 1. Database Connection ---
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
     $response = [
        'status' => 'error', 
        'title' => 'සම්බන්ධතා දෝෂය!', 
        'message' => 'දත්ත ගබඩාවට සම්බන්ධ වීමට නොහැක.', 
        'icon' => 'error'
    ];
    echo json_encode($response);
    exit;
}

// --- 2. Data Retrieval and Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    $response = ['status' => 'error', 'title' => 'දෝෂයක්!', 'message' => 'අවලංගු දත්ත යැවීමක්.', 'icon' => 'error'];
    echo json_encode($response);
    exit;
}

$client_name = isset($_POST['client_name']) ? $_POST['client_name'] : null;
$phone = isset($_POST['phone']) ? $_POST['phone'] : null;
$email = isset($_POST['email']) ? $_POST['email'] : null;
$address = isset($_POST['address']) ? $_POST['address'] : null;
$whatsapp = isset($_POST['whatsapp']) ? $_POST['whatsapp'] : null;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 5; // Rating is mandatory input, default to 5


// 🛑 PHONE & WHATSAPP VALIDATION (Backend) 🛑
$cleaned_phone = preg_replace('/\s+/', '', $phone); 
$cleaned_phone = preg_replace('/[^0-9+]/', '', $cleaned_phone); 
if (empty($phone) || !preg_match('/^[0-9+]{8,15}$/', $cleaned_phone)) { 
    echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'දුරකථන අංකය නිවැරදි නැත.', 'icon' => 'warning']);
    exit;
}

$cleaned_whatsapp = NULL;
if (!empty($whatsapp)) {
    $cleaned_whatsapp = preg_replace('/\s+/', '', $whatsapp);
    $cleaned_whatsapp = preg_replace('/[^0-9+]/', '', $cleaned_whatsapp);
    if (!preg_match('/^[0-9+]{8,15}$/', $cleaned_whatsapp)) {
        echo json_encode(['status' => 'error', 'title' => 'ආදාන දෝෂය!', 'message' => 'WhatsApp අංකය නිවැරදි නැත.', 'icon' => 'warning']);
        exit;
    }
} else {
    $cleaned_whatsapp = NULL;
}

// 🛑🛑 FINAL FIX: Ensure empty optional fields are explicitly NULL (for DB safety) 🛑🛑
$final_address = (empty($address) || trim($address) === '') ? NULL : $address;
$final_email = (empty($email) || trim($email) === '') ? NULL : $email;


// --- 3. Database Insertion Logic (Final Attempt) ---
$sql = "INSERT INTO clients (client_name, phone, email, address, whatsapp, rating) VALUES (:name, :phone, :email, :address, :whatsapp, :rating)";

try {
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':name' => $client_name,
        ':phone' => $cleaned_phone,
        ':email' => $final_email,
        ':address' => $final_address,
        ':whatsapp' => $cleaned_whatsapp,
        ':rating' => $rating
    ]);
    
    // SUCCESS RESPONSE
    $response = [
        'status' => 'success', 
        'title' => 'සාර්ථකයි!', 
        'message' => 'නව පාරිභෝගිකයා සාර්ථකව ඇතුළත් කරන ලදි!', 
        'icon' => 'success'
    ];

} catch (PDOException $e) {
    // ERROR HANDLING - Database Duplicate Check and Final Catch
    $error_message = $e->getMessage();
    $error_code = $e->errorInfo[1]; 
    $custom_msg = 'කිසියම් දෝශයක් ඇත.';

    if ($error_code === 1062) {
        
        if (strpos($error_message, 'client_name') !== false || strpos($error_message, 'uq_client_name') !== false || strpos($error_message, 'name') !== false) { 
             $custom_msg = '⚠️ දත්ත දෝෂය: ඔබ ඇතුළත් කළ **Client නාමය** දැනටමත් පවතී. කරුණාකර වෙනස් කරන්න.';
             
        } elseif (strpos($error_message, 'phone') !== false) { 
             $custom_msg = '⚠️ දත්ත දෝෂය: ඔබ ඇතුළත් කළ **දුරකථන අංකය** දැනටමත් පවතී.';
             
        } elseif (strpos($error_message, 'whatsapp') !== false) {
             $custom_msg = '⚠️ දත්ත දෝෂය: ඔබ ඇතුළත් කළ **WhatsApp අංකය** දැනටමත් පවතී.';
        }
    } elseif (strpos($error_message, 'Unknown column') !== false) {
        $custom_msg = '⚠️ දෝෂය: Database වගුවේ **තීරුවක්** අතුරුදහන්ව ඇත. (Rating, Whatsapp, හෝ Address)';
    } elseif (strpos($error_message, 'cannot be null') !== false) {
         $custom_msg = '⚠️ දෝෂය: **email**, **address**, හෝ **whatsapp** වැනි විකල්ප ක්ෂේත්‍රයක් අත්‍යවශ්‍ය (NOT NULL) ලෙස සකසා තිබේ.';
    }

    $response = [
        'status' => 'error', 
        'title' => 'දෝෂයක් සිදුවිය!', 
        'message' => $custom_msg, 
        'icon' => 'error'
    ];
}

echo json_encode($response);
exit;
?>