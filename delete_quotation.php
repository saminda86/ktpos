<?php
// File Name: delete_quotation.php
// Description: Deletes a quotation (Admin only)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 1. Admin Check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    $_SESSION['error_message'] = "Permission Denied. Only Admins can delete quotations.";
    header('Location: quotations.php');
    exit();
}

// 2. ID Check
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid ID.";
    header('Location: quotations.php');
    exit();
}

$quotation_id = intval($_GET['id']);
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Delete Logic
// ON DELETE CASCADE නිසා quotation_items ඉබේම මැකෙයි.
$sql = "DELETE FROM quotations WHERE quotation_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $quotation_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Quotation successfully deleted.";
} else {
    $_SESSION['error_message'] = "Error deleting quotation: " . $conn->error;
}

$stmt->close();
$conn->close();

header('Location: quotations.php');
exit();
?>