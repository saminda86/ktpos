<?php
// File Name: ktpos/category_process.php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once 'db_connect.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'fetch') {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} 
elseif ($action === 'insert') {
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->execute([$_POST['category_name']]);
        // 🛑 Return the New ID
        echo json_encode([
            'status' => 'success', 
            'message' => 'Category Added!', 
            'id' => $pdo->lastInsertId() 
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Category already exists or error.']);
    }
} 
elseif ($action === 'update') {
    try {
        $stmt = $pdo->prepare("UPDATE categories SET category_name=? WHERE category_id=?");
        $stmt->execute([$_POST['category_name'], $_POST['category_id']]);
        echo json_encode(['status' => 'success', 'message' => 'Category Updated!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Update Failed.']);
    }
} 
elseif ($action === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id=?");
        $stmt->execute([$_POST['category_id']]);
        echo json_encode(['status' => 'success', 'message' => 'Category Deleted!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete used category.']);
    }
}
?>