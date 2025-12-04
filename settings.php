<?php
// File Name: settings.php (Invoice & Print Settings)

$page_title = 'Settings';
require_once 'header.php';
require_once 'db_connect.php'; 

// 🛑🛑🛑 Admin-Only Security Check 🛑🛑🛑
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    $_SESSION['error_message'] = "මෙම පිටුවට ප්‍රවේශ වීමට අවසර ඇත්තේ පරිපාලක (Admin) හට පමණි.";
    // We include header.php, so we must use JavaScript for redirect
    echo '<script>window.location.href = "dashboard.php";</script>';
    exit();
}

// Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("SQL Prepare failed: " . $conn->error);
        }

        // 1. Update Warranty Terms
        $warranty_terms = $_POST['default_warranty_terms'] ?? '';
        $key1 = 'default_warranty_terms';
        $stmt->bind_param("ss", $warranty_terms, $key1);
        $stmt->execute();

        // 2. Update Footer Credit
        $footer_credit = $_POST['invoice_footer_credit'] ?? '';
        $key2 = 'invoice_footer_credit';
        $stmt->bind_param("ss", $footer_credit, $key2);
        $stmt->execute();

        // 3. Update Show Warranty Toggle
        $show_warranty = isset($_POST['show_warranty_on_invoice']) ? '1' : '0';
        $key3 = 'show_warranty_on_invoice';
        $stmt->bind_param("ss", $show_warranty, $key3);
        $stmt->execute();

        // 4. Update Show Footer Credit Toggle
        $show_footer = isset($_POST['show_footer_credit']) ? '1' : '0';
        $key4 = 'show_footer_credit';
        $stmt->bind_param("ss", $show_footer, $key4);
        $stmt->execute();
        
        $stmt->close();
        $conn->commit();
        $_SESSION['success_message'] = "Settings successfully updated!";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating settings: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header('Location: settings.php');
    exit();
}

// Fetch Current Settings (GET)
$settings = [];
$result = $conn->query("SELECT * FROM system_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$conn->close();
?>

<style>
    .form-switch .form-check-input {
        width: 3.5em;
        height: 1.75em;
    }
    .form-switch .form-check-label {
        padding-top: 0.25em;
    }
</style>

<h1 class="mb-4 text-primary"><i class="fas fa-cog"></i> System Settings</h1>
<hr>

<?php if (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])): ?>
    <div class="alert alert-<?php echo isset($_SESSION['success_message']) ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php 
        if (isset($_SESSION['success_message'])) {
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
        } elseif (isset($_SESSION['error_message'])) {
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-print"></i> Invoice & Print Settings</h6>
    </div>
    <div class="card-body">
        
        <form action="settings.php" method="POST">
            
            <div class="row">
                <div class="col-lg-8 border-end pe-4">
                    <div class="mb-4">
                        <label for="default_warranty_terms" class="form-label fw-bold">Default Warranty Terms & Conditions</label>
                        <textarea class="form-control" id="default_warranty_terms" name="default_warranty_terms" rows="10"><?php echo htmlspecialchars($settings['default_warranty_terms'] ?? ''); ?></textarea>
                        <div class="form-text">මෙම නියමයන් නව ඉන්වොයිස්පත් සෑදීමේදී ස්වයංක්‍රීයව ඇතුළත් වේ. (Each line will be shown as a new line on the print).</div>
                    </div>

                    <div class="mb-4">
                        <label for="invoice_footer_credit" class="form-label fw-bold">Invoice Footer Credit Line</label>
                        <input type="text" class="form-control" id="invoice_footer_credit" name="invoice_footer_credit" value="<?php echo htmlspecialchars($settings['invoice_footer_credit'] ?? ''); ?>">
                        <div class="form-text">This text will appear centered at the very bottom of the invoice print page.</div>
                    </div>
                </div>
                
                <div class="col-lg-4 ps-4">
                    <h6 class="text-secondary mb-3">Display Options</h6>
                    
                    <div class="form-check form-switch p-0 mb-3">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="show_warranty_on_invoice" name="show_warranty_on_invoice" value="1" <?php echo (isset($settings['show_warranty_on_invoice']) && $settings['show_warranty_on_invoice'] == '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label ps-3" for="show_warranty_on_invoice">
                            <span class="fw-bold d-block">Show Warranty Terms</span>
                            <small class="text-muted">Show the "Terms & Conditions" block on the invoice print page.</small>
                        </label>
                    </div>

                    <hr>
                    
                    <div class="form-check form-switch p-0 mb-3">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="show_footer_credit" name="show_footer_credit" value="1" <?php echo (isset($settings['show_footer_credit']) && $settings['show_footer_credit'] == '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label ps-3" for="show_footer_credit">
                            <span class="fw-bold d-block">Show Footer Credit</span>
                            <small class="text-muted">Show the "Software developed by..." credit line at the bottom of the print page.</small>
                        </label>
                    </div>

                </div>
            </div>
            
            <hr class="mt-4">
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
        
    </div>
</div>

<?php 
require_once 'footer.php';
?>