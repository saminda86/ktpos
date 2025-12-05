<?php
// File Name: ktpos/product_process.php (Service Cost Added)

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'title' => 'අවසර නැත!', 'message' => 'කරුණාකර පළමුව ලොග් වන්න.', 'icon' => 'error']);
    exit;
}

require_once 'db_connect.php'; 

try {
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'title' => 'සම්බන්ධතා දෝෂය!', 'message' => 'Database සම්බන්ධතාවය අසාර්ථකයි.', 'icon' => 'error']);
    exit;
}

$action = $_POST['action'] ?? '';

// -----------------------------------------------------------
// INSERT & UPDATE
// -----------------------------------------------------------
if ($action === 'insert' || $action === 'update') {
    
    $product_id   = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id  = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $description  = trim($_POST['description'] ?? '');
    $sell_price   = floatval($_POST['sell_price'] ?? 0);
    
    $item_type      = $_POST['item_type'] ?? 'product'; 
    $product_code   = trim($_POST['product_code'] ?? '');
    
    // 🛑 UPDATED LOGIC: Allow Buy Price for Services 🛑
    if ($item_type === 'service') {
        $buy_price      = floatval($_POST['buy_price'] ?? 0); // Service Cost Enabled
        $stock_quantity = 0;    // Stock is still 0 for services
        $supplier_id    = null; // Supplier not needed for services
    } else {
        $buy_price      = floatval($_POST['buy_price'] ?? 0);
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $supplier_id    = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    }

    if (empty($product_name) || empty($category_id)) {
        echo json_encode(['status' => 'error', 'title' => 'දත්ත දෝෂයක්!', 'message' => 'Item Name සහ Category අත්‍යවශ්‍ය වේ.', 'icon' => 'warning']);
        exit;
    }

    // Duplicate Check
    $checkSql = "SELECT COUNT(*) FROM products WHERE product_name = :name AND (supplier_id <=> :sup)";
    if ($action === 'update') {
        $checkSql .= " AND product_id != :id";
    }
    $checkStmt = $pdo->prepare($checkSql);
    $params = [':name' => $product_name, ':sup' => $supplier_id];
    if ($action === 'update') $params[':id'] = $product_id;
    
    $checkStmt->execute($params);
    if ($checkStmt->fetchColumn() > 0) {
        $msg = ($item_type === 'service') 
            ? 'මෙම නම සහිත Service එකක් දැනටමත් ඇත.' 
            : 'මෙම නම සහ Supplier යටතේ Product එකක් දැනටමත් ඇත.';
        echo json_encode(['status' => 'error', 'title' => 'Duplicate!', 'message' => $msg, 'icon' => 'warning']);
        exit;
    }

    // Auto Code
    if (empty($product_code)) {
        $prefix = ($item_type === 'service') ? 'KWS-' : 'KWP-';
        $product_code = $prefix . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Image Upload
    $image_path = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = 'prod_' . uniqid() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $fileName)) {
            $image_path = $uploadDir . $fileName;
        }
    }

    try {
        if ($action === 'insert') {
            $sql = "INSERT INTO products (product_code, product_name, category_id, supplier_id, buy_price, sell_price, stock_quantity, description, image_path) 
                    VALUES (:code, :name, :cat, :sup, :buy, :sell, :stock, :desc, :img)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':code' => $product_code, ':name' => $product_name, ':cat' => $category_id, ':sup' => $supplier_id,
                ':buy' => $buy_price, ':sell' => $sell_price, ':stock' => $stock_quantity, ':desc' => $description, ':img' => $image_path
            ]);
            echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'ඇතුළත් කිරීම සාර්ථකයි!', 'icon' => 'success']);
        } else {
            $sql = "UPDATE products SET product_name=:name, product_code=:code, category_id=:cat, supplier_id=:sup, 
                    buy_price=:buy, sell_price=:sell, stock_quantity=:stock, description=:desc";
            if ($image_path) $sql .= ", image_path=:img";
            $sql .= " WHERE product_id=:id";
            
            $upParams = [
                ':name' => $product_name, ':code' => $product_code, ':cat' => $category_id, ':sup' => $supplier_id,
                ':buy' => $buy_price, ':sell' => $sell_price, ':stock' => $stock_quantity, ':desc' => $description, ':id' => $product_id
            ];
            if ($image_path) $upParams[':img'] = $image_path;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($upParams);
            echo json_encode(['status' => 'success', 'title' => 'සාර්ථකයි!', 'message' => 'යාවත්කාලීන කිරීම සාර්ථකයි!', 'icon' => 'success']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['status' => 'error', 'title' => 'Duplicate Code!', 'message' => 'Product Code එක දැනටමත් පවතී.', 'icon' => 'warning']);
        } else {
            echo json_encode(['status' => 'error', 'title' => 'Error', 'message' => $e->getMessage(), 'icon' => 'error']);
        }
    }
}

// -----------------------------------------------------------
// DELETE Logic
// -----------------------------------------------------------
elseif ($action === 'delete') {
    if ($_SESSION['user_type'] !== 'Admin') {
        echo json_encode(['status' => 'error', 'title' => 'අවසර නැත', 'message' => 'Admin පමණි.', 'icon' => 'error']);
        exit;
    }
    try {
        $pdo->prepare("DELETE FROM products WHERE product_id = ?")->execute([$_POST['product_id']]);
        echo json_encode(['status' => 'success', 'title' => 'ඉවත් කළා!', 'message' => 'සාර්ථකව ඉවත් කරන ලදි.', 'icon' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'title' => 'අසාර්ථකයි', 'message' => 'භාවිතයේ පවතින බැවින් මැකිය නොහැක.', 'icon' => 'error']);
    }
}
?>