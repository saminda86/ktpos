<?php
// File Name: quotation_actions.php
// Description: Handles AJAX requests for Quotations

header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

// ACTION: UPDATE STATUS
if ($action === 'update_status') {
    $status = $_POST['status'] ?? 'Pending';
    
    // Status can only be Pending, Accepted, Rejected
    if (!in_array($status, ['Pending', 'Accepted', 'Rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Status']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE quotations SET status = ? WHERE quotation_id = ?");
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => "Status updated to $status"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Error']);
    }
    $stmt->close();
}

// ACTION: DELETE
elseif ($action === 'delete') {
    if ($_SESSION['user_type'] !== 'Admin') {
        echo json_encode(['status' => 'error', 'message' => 'Permission Denied']);
        exit;
    }

    // ON DELETE CASCADE will handle items
    $stmt = $conn->prepare("DELETE FROM quotations WHERE quotation_id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Quotation Deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Deletion Failed']);
    }
    $stmt->close();
}

$conn->close();
?>