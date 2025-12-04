<?php
// File Name: ktpos/header.php (Final Version with Services & Settings Link)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$userName = $_SESSION['name'] ?? 'Guest'; 
$userType = $_SESSION['user_type'] ?? 'User'; 

$page_title = isset($page_title) ? $page_title : 'System'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KAWDU TECHNOLOGY | <?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700&family=Roboto:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="styles.css">
    
    <style>
        .top-navbar .user-info {
            font-size: 1.1em;
            color: #333;
            font-weight: 600;
        }
        .top-navbar .user-info i {
            font-size: 1.4em; 
            margin-right: 8px;
            color: var(--primary-color); 
        }
        .sidebar a.active {
            background-color: var(--accent-color); 
            color: white;
        }
        .sidebar a {
            padding: 15px 10px 15px 20px;
            text-decoration: none;
            font-size: 16px;
            color: #ddd;
            display: block;
            transition: background-color 0.3s;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        KAWDU TECH
    </div>
    
    <a href="dashboard.php" class="<?php echo ($page_title == 'Dashboard' ? 'active' : ''); ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    
    <a href="clients.php" class="<?php echo ($page_title == 'Clients' ? 'active' : ''); ?>">
        <i class="fas fa-users"></i> Clients
    </a>
    
    <a href="products.php" class="<?php echo ($page_title == 'Products & Inventory' ? 'active' : ''); ?>">
        <i class="fas fa-boxes"></i> Products & Inventory
    </a>
    
    <a href="services.php" class="<?php echo ($page_title == 'Service Management' ? 'active' : ''); ?>">
        <i class="fas fa-tools"></i> Services
    </a>
    
    <a href="quotations.php" class="<?php echo ($page_title == 'Quotations' ? 'active' : ''); ?>">
        <i class="fas fa-file-invoice"></i> Quotations
    </a>
    
    <a href="invoices.php" class="<?php echo ($page_title == 'Invoices' ? 'active' : ''); ?>">
        <i class="fas fa-receipt"></i> Invoices
    </a>

    <a href="service_note.php" class="<?php echo ($page_title == 'Service Notes' ? 'active' : ''); ?>">
        <i class="fas fa-clipboard-list"></i> Service Notes
    </a>
    
    <a href="reports.php" class="<?php echo ($page_title == 'Reports' ? 'active' : ''); ?>">
        <i class="fas fa-chart-line"></i> Reports
    </a>
    
    <?php if ($userType == 'Admin'): ?>
        <a href="users.php" class="<?php echo ($page_title == 'User Management' ? 'active' : ''); ?>">
            <i class="fas fa-user-shield"></i> User Management
        </a>
        
        <a href="settings.php" class="<?php echo ($page_title == 'Settings' ? 'active' : ''); ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
    <?php endif; ?>
</div>

<div class="main-content">
    
    <div class="top-navbar mb-4">
        <span class="user-info">
            <i class="fas fa-user-circle"></i> 
            <?php echo htmlspecialchars($userName); ?> 
        </span>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>