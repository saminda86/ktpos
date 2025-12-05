<?php
// File Name: ktpos/supplier_process.php

require_once 'db_connect.php'; 
header('Content-Type: application/json');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die(json_encode(['status' => 'error', 'message' => 'DB Connection Failed'])); }

// Auto-fix table (Ensure contact_no exists)
$tableCheck = "CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact_no VARCHAR(50) UNIQUE DEFAULT NULL
)";
$conn->query($tableCheck);

// If contact_no column is missing in existing table, add it
$checkCol = $conn->query("SHOW COLUMNS FROM suppliers LIKE 'contact_no'");
if ($checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE suppliers ADD COLUMN contact_no VARCHAR(50) UNIQUE DEFAULT NULL");
}

$action = $_POST['action'] ?? '';

// 1. FETCH
if ($action == 'fetch') {
    $sql = "SELECT * FROM suppliers ORDER BY supplier_id DESC";
    $result = $conn->query($sql);
    $data = [];
    if ($result) { while ($row = $result->fetch_assoc()) { $data[] = $row; } }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// 2. INSERT
if ($action == 'insert') {
    $name = trim($conn->real_escape_string($_POST['supplier_name']));
    $contact = trim($conn->real_escape_string($_POST['contact_no']));
    
    if (empty($name)) { echo json_encode(['status' => 'error', 'message' => 'Name required']); exit; }

    // Check duplicate Name or Contact
    $check = $conn->query("SELECT supplier_id FROM suppliers WHERE supplier_name = '$name' OR contact_no = '$contact'");
    if ($check && $check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Supplier Name or Contact Number already exists!']);
        exit;
    }

    $sql = "INSERT INTO suppliers (supplier_name, contact_no) VALUES ('$name', '$contact')";
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Supplier Added']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $conn->error]);
    }
    exit;
}

// 3. UPDATE
if ($action == 'update') {
    $id = (int)$_POST['supplier_id'];
    $name = trim($conn->real_escape_string($_POST['supplier_name']));
    $contact = trim($conn->real_escape_string($_POST['contact_no']));

    if (empty($id) || empty($name)) { echo json_encode(['status' => 'error', 'message' => 'Invalid Data']); exit; }

    $sql = "UPDATE suppliers SET supplier_name = '$name', contact_no = '$contact' WHERE supplier_id = $id";
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Supplier Updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Update Failed: ' . $conn->error]);
    }
    exit;
}

// 4. DELETE
if ($action == 'delete') {
    $id = (int)$_POST['supplier_id'];
    $check = $conn->query("SELECT product_id FROM products WHERE supplier_id = $id LIMIT 1");
    if ($check && $check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Used in products']);
        exit;
    }
    if ($conn->query("DELETE FROM suppliers WHERE supplier_id = $id")) {
        echo json_encode(['status' => 'success', 'message' => 'Deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error']);
    }
    exit;
}

$conn->close();
?>