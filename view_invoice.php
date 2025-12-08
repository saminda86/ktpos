<?php
// File Name: view_invoice.php
// Description: Professional Invoice View - Matches Quotation Layout with Colored Status Text

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

require_once 'db_connect.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { die("Invalid Invoice ID."); }
$invoice_id = intval($_GET['id']);

$orientation = isset($_GET['orientation']) && $_GET['orientation'] == 'landscape' ? 'landscape' : 'portrait';
$is_landscape = ($orientation == 'landscape');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 1. Fetch Invoice
$sql_invoice = "SELECT i.*, c.client_name, c.address AS client_address, c.phone AS client_phone, c.email AS client_email, c.whatsapp AS client_whatsapp, u.name AS user_name 
                FROM invoices i
                JOIN clients c ON i.client_id = c.client_id
                JOIN users u ON i.user_id = u.user_id
                WHERE i.invoice_id = ?";
$stmt = $conn->prepare($sql_invoice);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { die("Invoice not found."); }
$invoice = $result->fetch_assoc();
$stmt->close();

// 2. Fetch Items
$items = [];
$sql_items = "SELECT ii.*, p.description AS product_desc, p.image_path 
              FROM invoice_items ii
              LEFT JOIN products p ON ii.product_id = p.product_id 
              WHERE ii.invoice_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $invoice_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) { $items[] = $row; }
$stmt_items->close();

$settings = [];
$set_res = $conn->query("SELECT * FROM system_settings WHERE setting_key = 'invoice_footer_credit'");
if($set_res) { while($row = $set_res->fetch_assoc()) { $settings[$row['setting_key']] = $row['setting_value']; } }
$conn->close();

// Helper: Returns CSS style for status text color (No Badge)
function getStatusStyle($status) {
    switch($status) {
        case 'Paid': return 'color: #198754; font-weight: 700;'; // Professional Green
        case 'Unpaid': return 'color: #dc3545; font-weight: 700;'; // Professional Red
        case 'Pending': return 'color: #e67e22; font-weight: 700;'; // Professional Orange
        default: return 'color: #6c757d; font-weight: 700;'; // Gray
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Sinhala:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root { --primary-color: #27b19d; --text-color: #333; --secondary-text: #555; }
        body { background-color: #f3f5f9; font-family: 'Inter', 'Noto Sans Sinhala', sans-serif; color: var(--text-color); -webkit-print-color-adjust: exact; margin: 0; }

        /* Floating Action Bar */
        .action-bar { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-200%); z-index: 3000; display: flex; gap: 12px; background: rgba(35, 35, 35, 0.85); backdrop-filter: blur(12px); padding: 10px 25px; border-radius: 50px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.1); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .action-bar.visible { transform: translateX(-50%) translateY(0); }
        .hover-trigger { position: fixed; top: 0; left: 0; width: 100%; height: 20px; z-index: 2999; }
        .action-bar .btn { border-radius: 50px; padding: 6px 15px; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; border: none; cursor: pointer; }
        .btn-glass-light { background: rgba(255, 255, 255, 0.15); color: #eee; } .btn-glass-light:hover { background: rgba(255, 255, 255, 0.3); color: #fff; }
        .btn-glass-primary { background: var(--primary-color); color: #fff; box-shadow: 0 4px 15px rgba(39, 177, 157, 0.4); } .btn-glass-primary:hover { background: #209c8a; transform: scale(1.05); }
        .btn-glass-warning { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); } .btn-glass-warning:hover { background: rgba(255, 193, 7, 0.4); color: #fff; }
        .btn-glass-success { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.3); } .btn-glass-success:hover { background: #28a745; color: #fff; }

        /* A4 Page Layout */
        .page-container {
            max-width: <?php echo $is_landscape ? '297mm' : '210mm'; ?>;
            min-height: <?php echo $is_landscape ? '210mm' : '297mm'; ?>;
            margin: 40px auto; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 8px; padding: 40px; position: relative; display: flex; flex-direction: column;
            padding-bottom: 70px; box-sizing: border-box;
        }

        /* Header */
        .header-section { border-bottom: 2px solid var(--primary-color); padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .company-logo img { max-height: 70px; margin-bottom: 10px; }
        .company-info h2 { font-weight: 800; color: #144f6a; margin: 0; font-size: 1.8rem; }
        .company-info p { margin: 2px 0; font-size: 0.9rem; color: var(--secondary-text); }
        .document-title h1 { font-size: 3rem; font-weight: 900; color: var(--primary-color); margin: 0; opacity: 0.15; text-transform: uppercase; line-height: 0.8; }
        .document-title .real-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-top: -15px; display: block; text-transform: uppercase; letter-spacing: 2px; }

        /* --- INFO GRID (MATCHING QUOTATION STYLE) --- */
        .info-grid { display: grid; grid-template-columns: 55% 40%; gap: 5%; margin-bottom: 30px; page-break-inside: avoid; }
        
        /* Box Headers (Matched with Quotation) */
        .info-box h5 { 
            font-size: 0.85rem; text-transform: uppercase; color: #999; font-weight: 700; 
            margin-bottom: 12px; letter-spacing: 1px; border-bottom: 1px solid #eee; padding-bottom: 5px; 
        }
        
        /* Client Details (Matched with Quotation) */
        .info-box .client-name { font-size: 1.2rem; font-weight: 700; color: #000; margin-bottom: 5px; }
        .client-detail-row { margin-bottom: 4px; color: var(--secondary-text); font-size: 0.95rem; display: flex; align-items: center; }
        .client-detail-row i { width: 20px; text-align: center; margin-right: 8px; }

        /* Reference Table (Matched with Quotation) */
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 3px 0; font-size: 0.85rem; vertical-align: top; }
        .meta-table td:first-child { color: var(--secondary-text); padding-right: 15px; font-weight: 600; width: 140px; white-space: nowrap; }
        .meta-table td:last-child { font-weight: 700; color: #333; text-align: right; white-space: nowrap; }

        /* Items Table */
        .modern-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; table-layout: fixed; }
        .modern-table thead th { background-color: var(--primary-color) !important; color: #fff !important; padding: 12px 10px; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; border: none; }
        .modern-table tbody td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; font-size: 0.9rem; }
        .modern-table tbody tr:nth-child(even) { background-color: #fcfcfc; }
        
        /* Row Styles */
        .row-item td { border-bottom: 1px solid #f0f0f0; } 
        .row-item.has-serial td { border-bottom: none !important; padding-bottom: 0px !important; } 
        .row-serial td { padding-top: 0px !important; padding-bottom: 10px !important; border-bottom: 1px solid #f0f0f0; color: #555; font-size: 0.8rem; }
        .row-group-container:nth-child(even) .row-item, .row-group-container:nth-child(even) .row-serial { background-color: #fcfcfc; }

        .item-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; }
        .item-name { font-weight: 700; color: #333; display: block; margin-bottom: 2px; }
        .item-desc { font-size: 0.8rem; color: #666; line-height: 1.4; margin-bottom: 0; }
        .sn-text { font-family: 'Inter', 'Noto Sans Sinhala', sans-serif; font-size: 0.8rem; color: #444; line-height: 1.4; margin-top: 0; white-space: pre-wrap; word-wrap: break-word; word-break: break-all; display: block; }
        .warranty-highlight { color: #d35400; font-weight: 700; background-color: #fff3e0; padding: 0 4px; border-radius: 3px; display: inline-block; margin-left: 5px; }

        /* Column Widths */
        .col-no { width: 5%; text-align: center; }
        .col-img { width: 8%; text-align: center; }
        .col-desc { width: 42%; } 
        .col-qty { width: 10%; text-align: center; }
        .col-price { width: 15%; text-align: right; }
        .col-total { width: 20%; text-align: right; }

        /* Totals */
        .totals-wrapper { display: flex; justify-content: flex-end; margin-bottom: 30px; page-break-inside: avoid; }
        .totals-table { width: 350px; border-collapse: collapse; }
        .totals-table td { padding: 8px 0; vertical-align: middle; }
        .totals-table td:first-child { text-align: right; padding-right: 20px; color: #6c757d; font-weight: 600; font-size: 0.95rem; width: 50%; }
        .totals-table td:last-child { text-align: right; color: #333; font-weight: 700; font-size: 1rem; width: 50%; }
        .grand-total-row td { border-top: 2px solid var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-top: 15px; padding-bottom: 15px; margin-top: 10px; }
        .grand-total-row .label { color: var(--primary-color) !important; font-size: 1.1rem; font-weight: 800; text-transform: uppercase; }
        .grand-total-row .value { color: var(--primary-color) !important; font-size: 1.3rem; font-weight: 800; }
        
        /* Terms */
        .terms-wrapper { margin-bottom: 40px; page-break-inside: avoid; }
        .terms-box h6 { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; color: #333; margin-bottom: 10px; }
        .terms-text { font-family: 'Inter', 'Noto Sans Sinhala', sans-serif; font-size: 0.85rem; color: #666; line-height: 1.6; }

        /* Footer */
        .footer-bottom-container { margin-top: 50px; page-break-inside: avoid; }
        .signature-area { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .signature-line { width: 180px; border-top: 1px dashed #ccc; text-align: center; padding-top: 5px; font-size: 0.75rem; color: #888; }
        .footer-credit { text-align: center; font-size: 0.7rem; color: #999; padding-top: 10px; border-top: 1px solid #eee; width: 100%; }

        @media print {
            @page { size: <?php echo $orientation; ?>; margin: 10mm; }
            body { background: #fff; margin: 0; }
            .action-bar, .hover-trigger { display: none !important; }
            .page-container { box-shadow: none; margin: 0; padding: 0; padding-bottom: 60px; width: 100%; max-width: none; border: none; min-height: 100vh; display: flex; flex-direction: column; }
            .modern-table thead { display: table-header-group; }
            .modern-table tbody { page-break-inside: avoid; }
            .row-group-container { page-break-inside: avoid; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .footer-credit { position: fixed; bottom: 10mm; left: 0; width: 100%; }
            .footer-bottom-container { margin-top: 50px; }
        }
    </style>
</head>
<body>

    <div class="hover-trigger" id="topTrigger"></div>

    <div class="action-bar no-print" id="floatingBar">
        <a href="invoices.php" class="btn btn-glass-light" title="Back to List"><i class="fas fa-arrow-left"></i> <span>Back</span></a>
        <a href="view_invoice.php?id=<?php echo $invoice_id; ?>&orientation=<?php echo $is_landscape ? 'portrait' : 'landscape'; ?>" class="btn btn-glass-light" title="Rotate Page"><i class="fas fa-sync-alt"></i> <span><?php echo $is_landscape ? 'Portrait' : 'Landscape'; ?></span></a>
        <a href="edit_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-glass-warning" title="Edit Invoice"><i class="fas fa-edit"></i> <span>Edit</span></a>
        <button onclick="saveAsImage(this)" class="btn btn-glass-success" title="Save as HD JPG"><i class="fas fa-file-image"></i> <span>Save JPG</span></button>
        <button onclick="window.print()" class="btn btn-glass-primary" title="Print PDF"><i class="fas fa-print"></i> <span>Print PDF</span></button>
    </div>

    <div class="page-container" id="captureArea">
        
        <div> <header class="header-section">
                <div class="d-flex align-items-center">
                    <div class="company-logo me-4">
                        <img src="uploads/products/KAWDU technology FB LOGO.png" alt="Logo">
                    </div>
                    <div class="company-info">
                        <h2>KAWDU TECHNOLOGY</h2>
                        <p><i class="fas fa-map-marker-alt me-2"></i> 323, Waduwelivitiya (North), Kahaduwa</p>
                        <p><i class="fas fa-phone-alt me-2"></i> 0776 228 943 | 0786 228 943</p>
                        <p><i class="fas fa-envelope me-2"></i> info@kawdutech.com</p>
                    </div>
                </div>
                <div class="document-title">
                    <h1>INVOICE</h1>
                    <span class="real-title">INVOICE</span>
                </div>
            </header>

            <div class="info-grid">
                <div class="info-box">
                    <h5>INVOICE TO:</h5>
                    <div class="client-name"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                    <div class="client-details">
                        <div class="client-detail-row">
                            <?php echo nl2br(htmlspecialchars($invoice['client_address'])); ?>
                        </div>
                        <?php if($invoice['client_phone']): ?>
                        <div class="client-detail-row">
                            <?php echo htmlspecialchars($invoice['client_phone']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if($invoice['client_email']): ?>
                        <div class="client-detail-row">
                            <?php echo htmlspecialchars($invoice['client_email']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-box">
                    <h5>REFERENCE INFO:</h5>
                    <table class="meta-table">
                        <tr>
                            <td>Invoice #:</td>
                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                        </tr>
                        <tr>
                            <td>Date Issued:</td>
                            <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td style="<?php echo getStatusStyle($invoice['payment_status']); ?> text-transform: uppercase;">
                                <?php echo htmlspecialchars($invoice['payment_status']); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Issued By:</td>
                            <td style="text-transform: uppercase; white-space: nowrap;"><?php echo htmlspecialchars($invoice['user_name']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="modern-table">
                <thead>
                    <tr>
                        <th class="col-no">#</th>
                        <th class="col-img">IMG</th>
                        <th class="col-desc">DESCRIPTION</th>
                        <th class="col-qty">QTY</th>
                        <th class="col-price">UNIT PRICE</th>
                        <th class="col-total">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): 
                        $has_serial = !empty($item['serial_number']);
                        $row_class = $has_serial ? 'has-serial' : '';
                        
                        $sn_full = $item['serial_number'];
                        // Auto-wrap logic
                        $sn_full = str_replace('/', '/ ', $sn_full);
                        
                        $sn_display = '';
                        $warranty_display = '';

                        if (strpos($sn_full, '|') !== false) {
                            $parts = explode('|', $sn_full, 2);
                            $sn_display = nl2br(htmlspecialchars(trim($parts[0])));
                            $warranty_display = '<span class="warranty-highlight">' . htmlspecialchars(trim($parts[1])) . '</span>';
                        } else {
                            $sn_display = nl2br(htmlspecialchars($sn_full));
                        }
                    ?>
                    
                    <tbody class="row-group-container">
                        <tr class="row-item <?php echo $row_class; ?>">
                            <td class="col-no"><?php echo $index + 1; ?></td>
                            <td class="col-img">
                                <?php $img_src = !empty($item['image_path']) ? $item['image_path'] : 'uploads/products/default.png'; ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Item" class="item-img">
                            </td>
                            <td class="col-desc">
                                <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                <?php 
                                    if(!empty($item['product_desc'])): 
                                        $desc = htmlspecialchars($item['product_desc']);
                                        $short_desc = (mb_strlen($desc) > 120) ? mb_substr($desc, 0, 117) . '...' : $desc;
                                ?>
                                    <div class="item-desc"><?php echo nl2br($short_desc); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-qty"><?php echo $item['quantity'] + 0; ?></td> 
                            <td class="col-price"><?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="col-total"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                        </tr>

                        <?php if ($has_serial): ?>
                        <tr class="row-serial">
                            <td></td> <td></td> <td colspan="4"> 
                                <span class="sn-text"><b>S/N:</b> <?php echo $sn_display . ' ' . $warranty_display; ?></span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>

                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-wrapper">
                <table class="totals-table">
                    <tr><td>Sub Total:</td><td><?php echo number_format($invoice['sub_total'], 2); ?></td></tr>
                    <?php if ($invoice['tax_amount'] > 0): ?>
                    <tr><td>Tax / VAT:</td><td><?php echo number_format($invoice['tax_amount'], 2); ?></td></tr>
                    <?php endif; ?>
                    <tr class="grand-total-row"><td>Grand Total:</td><td class="value">LKR <?php echo number_format($invoice['grand_total'], 2); ?></td></tr>
                </table>
            </div>

            <?php if (!empty($invoice['invoice_terms'])): ?>
            <div class="terms-wrapper">
                <div class="terms-box">
                    <h6>Terms & Conditions:</h6>
                    <div class="terms-text">
                        <?php echo nl2br(htmlspecialchars($invoice['invoice_terms'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer-bottom-container">
            <div class="signature-area">
                <div class="signature-line">Prepared By</div>
                <div class="signature-line">Authorized Signature</div>
                <div class="signature-line">Customer Acceptance</div>
            </div>

            <div class="footer-credit">
                <?php echo isset($settings['invoice_footer_credit']) ? htmlspecialchars($settings['invoice_footer_credit']) : 'Thank you for your business!'; ?>
            </div>
        </div>

    </div>

    <script>
        const navbar = document.getElementById('floatingBar');
        const trigger = document.getElementById('topTrigger');
        window.addEventListener('scroll', () => {
            if (window.scrollY < 50) { navbar.classList.add('visible'); } else { navbar.classList.remove('visible'); }
        });
        trigger.addEventListener('mouseenter', () => { navbar.classList.add('visible'); });
        navbar.classList.add('visible');
    </script>

</body>
</html>