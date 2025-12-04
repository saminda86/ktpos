<?php
// File Name: view_invoice.php (Printable View - REVISED LAYOUT)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// No header.php or footer.php on a print page

// 1. Security & DB Connect
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'db_connect.php';

// 2. Get ID and Validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Invoice ID.");
}
$invoice_id = intval($_GET['id']);

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 3. Fetch Data
// A. System Settings
$settings = [];
$settings_result = $conn->query("SELECT * FROM system_settings");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// B. Main Invoice Data (with Client and User)
$sql_invoice = "SELECT i.*, c.client_name, c.address AS client_address, c.phone AS client_phone, u.name AS user_name 
                FROM invoices i
                JOIN clients c ON i.client_id = c.client_id
                JOIN users u ON i.user_id = u.user_id
                WHERE i.invoice_id = ?";
$stmt_invoice = $conn->prepare($sql_invoice);
$stmt_invoice->bind_param("i", $invoice_id);
$stmt_invoice->execute();
$invoice_result = $stmt_invoice->get_result();
if ($invoice_result->num_rows === 0) {
    die("Invoice not found.");
}
$invoice = $invoice_result->fetch_assoc();
$stmt_invoice->close();

// C. Invoice Items
$items = [];
$stmt_items = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt_items->bind_param("i", $invoice_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();
$conn->close();

// Helper function for status badges
function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'Paid': return 'status-paid';
        case 'Pending': return 'status-pending';
        case 'Unpaid': return 'status-unpaid';
        default: return 'status-unknown';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-color: #f0f0f0;
            font-family: 'Roboto', 'Noto Sans Sinhala', sans-serif;
            font-size: 14px;
            color: #333;
        }
        .invoice-container {
            max-width: 900px;
            margin: 20px auto;
            background-color: #ffffff;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }
        
        /* 🛑 REVISED HEADER LAYOUT 🛑 */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #27b19d;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header-company-details {
            flex: 1;
        }
        .header-company-details .logo {
            font-size: 2.5rem;
            font-weight: 700;
            color: #144f6a;
            display: flex;
            align-items: center;
        }
        .header-company-details .logo img {
            max-height: 60px;
            margin-right: 10px;
        }
        .header-company-details .address {
            font-size: 0.9rem;
            color: #555;
            padding-left: 5px; /* Align with logo text */
            margin-top: 10px;
            line-height: 1.5;
        }
        .header-invoice-title {
            flex-basis: 30%;
            text-align: right;
        }
        .header-invoice-title .invoice-title {
            font-size: 2.8rem;
            font-weight: 700;
            color: #333;
            line-height: 1;
        }
        /* 🛑 END REVISED HEADER 🛑 */

        /* 🛑 REVISED META LAYOUT 🛑 */
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .meta-to {
            flex-basis: 50%;
        }
        .meta-to strong, .meta-details strong {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
            color: #144f6a;
            margin-bottom: 5px;
        }
        .meta-details {
            flex-basis: 45%;
            text-align: right;
            font-size: 1rem;
            line-height: 1.6;
        }
        /* 🛑 END REVISED META 🛑 */
        
        .status-badge {
            display: inline-block;
            padding: 0.4em 0.8em;
            font-size: 0.9em;
            font-weight: 700;
            border-radius: 5px;
            color: #fff;
        }
        .status-paid { background-color: #198754; }
        .status-pending { background-color: #ffc107; color: #333 !important; }
        .status-unpaid { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; }
        
        .invoice-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-items-table thead {
            background-color: #f0f0f0;
            border-bottom: 2px solid #ddd;
        }
        .invoice-items-table th {
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: #333;
        }
        .invoice-items-table tbody tr {
            border-bottom: 1px solid #eee;
        }
        .invoice-items-table tbody td {
            padding: 12px;
            vertical-align: top;
        }
        .invoice-items-table .item-description {
            font-weight: 500;
        }
        .invoice-items-table .item-serial {
            font-size: 0.85em;
            color: #555;
            padding-left: 10px;
        }
        .invoice-items-table th.text-end, .invoice-items-table td.text-end {
            text-align: right;
        }
        
        .invoice-totals {
            width: 50%;
            margin-left: auto;
            font-size: 1.1rem;
        }
        .invoice-totals th, .invoice-totals td {
            padding: 10px 15px;
            text-align: right;
        }
        .invoice-totals .grand-total {
            font-size: 1.4rem;
            font-weight: 700;
            color: #27b19d;
            border-top: 2px solid #27b19d;
            border-bottom: 2px solid #27b19d;
        }
        
        .invoice-footer-container {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .footer-terms {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 20px;
        }
        .footer-terms strong {
            display: block;
            font-size: 1rem;
            color: #333;
            margin-bottom: 5px;
        }
        .footer-credit {
            text-align: center;
            font-size: 0.8rem;
            color: #999;
            padding: 10px 0;
        }

        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
        }

        @media print {
            body {
                background-color: #ffffff;
                font-size: 12px;
            }
            .no-print {
                display: none;
            }
            .invoice-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
            }
            .status-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .invoice-items-table thead {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .invoice-footer-container {
                position: fixed;
                bottom: 10px;
                left: 40px; 
                right: 40px; 
                width: calc(100% - 80px);
                margin-top: 0;
                padding-top: 10px;
                border-top: 1px solid #eee;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <a href="invoices.php" class="btn btn-secondary me-2"><i class="fas fa-arrow-left"></i> Back to List</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
    </div>

    <div class="invoice-container">
        
        <header class="invoice-header">
            <div class="header-company-details">
                <div class="logo">
                    <img src="uploads/products/KAWDU technology FB LOGO.png" alt="KAWDU TECHNOLOGY Logo">
                    <span>KAWDU TECHNOLOGY</span>
                </div>
                <div class="address">
                    323'Waduwelivitiya(North), Kahaduwa<br>
                    0776 228 943 | 0786 228 943
                </div>
            </div>
            <div class="header-invoice-title">
                <div class="invoice-title">INVOICE</div>
            </div>
        </header>

        <section class="invoice-meta">
            <div class="meta-to">
                <strong>INVOICE TO:</strong>
                <address class="mt-2">
                    <strong><?php echo htmlspecialchars($invoice['client_name']); ?></strong><br>
                    <?php echo nl2br(htmlspecialchars($invoice['client_address'])); ?><br>
                    <?php echo htmlspecialchars($invoice['client_phone']); ?>
                </address>
            </div>
            <div class="meta-details">
                <strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?><br>
                <strong>Invoice Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?><br>
                <strong>Issued By:</strong> <?php echo htmlspecialchars($invoice['user_name']); ?><br>
                <span class="status-badge <?php echo getPaymentStatusBadgeClass($invoice['payment_status']); ?>">
                    <?php echo htmlspecialchars($invoice['payment_status']); ?>
                </span>
            </div>
        </section>

        <section class="invoice-items">
            <table class="invoice-items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th> <th>Item Description</th>
                        <th style="width: 10%;" class="text-end">Qty</th>
                        <th style="width: 15%;" class="text-end">Unit Price</th>
                        <th style="width: 15%;" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td> <td>
                            <div class="item-description"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <?php if (!empty($item['serial_number'])): ?>
                                <div class="item-serial">Serial/Note: <?php echo htmlspecialchars($item['serial_number']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="invoice-totals">
            <table class="table table-borderless">
                <tbody>
                    <tr>
                        <th class="text-secondary">Sub Total (රු.)</th>
                        <td class="text-secondary"><?php echo number_format($invoice['sub_total'], 2); ?></td>
                    </tr>
                    <tr>
                        <th class="text-secondary">Tax (රු.)</th>
                        <td class="text-secondary"><?php echo number_format($invoice['tax_amount'], 2); ?></td>
                    </tr>
                    <tr class="grand-total">
                        <th>Grand Total (රු.)</th>
                        <td><?php echo number_format($invoice['grand_total'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <footer class="invoice-footer-container">
            <?php if (isset($settings['show_warranty_on_invoice']) && $settings['show_warranty_on_invoice'] == '1' && !empty($invoice['invoice_terms'])): ?>
            <div class="footer-terms">
                <strong>Terms & Conditions:</strong>
                <div><?php echo nl2br(htmlspecialchars($invoice['invoice_terms'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if (isset($settings['show_footer_credit']) && $settings['show_footer_credit'] == '1' && !empty($settings['invoice_footer_credit'])): ?>
            <div class="footer-credit">
                <?php echo htmlspecialchars($settings['invoice_footer_credit']); ?>
            </div>
            <?php endif; ?>
        </footer>

    </div>

</body>
</html>