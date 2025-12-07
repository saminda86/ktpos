<?php
// File Name: view_quotation.php
// Description: Professional Quotation (Edit Links to Main List Modal)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ආරක්ෂක පරීක්ෂාව
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db_connect.php';

// 2. ID ලබා ගැනීම
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Quotation ID.");
}
$quotation_id = intval($_GET['id']);

// 3. Orientation Logic
$orientation = isset($_GET['orientation']) && $_GET['orientation'] == 'landscape' ? 'landscape' : 'portrait';
$is_landscape = ($orientation == 'landscape');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 4. දත්ත ලබා ගැනීම
$sql_quote = "SELECT q.*, c.client_name, c.address AS client_address, c.phone AS client_phone, c.email AS client_email, c.whatsapp AS client_whatsapp, u.name AS user_name 
              FROM quotations q
              JOIN clients c ON q.client_id = c.client_id
              JOIN users u ON q.user_id = u.user_id
              WHERE q.quotation_id = ?";
$stmt = $conn->prepare($sql_quote);
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Quotation not found.");
}
$quote = $result->fetch_assoc();
$stmt->close();

// 5. Items සහ Description ලබා ගැනීම
$items = [];
$sql_items = "SELECT qi.*, p.description AS product_desc, p.image_path 
              FROM quotation_items qi
              LEFT JOIN products p ON qi.product_id = p.product_id 
              WHERE qi.quotation_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $quotation_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

// 6. Settings ලබා ගැනීම
$settings = [];
$set_res = $conn->query("SELECT * FROM system_settings WHERE setting_key = 'invoice_footer_credit'");
if($set_res) {
    while($row = $set_res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #<?php echo htmlspecialchars($quote['quotation_number']); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Sinhala:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --primary-color: #27b19d;
            --text-color: #333;
            --secondary-text: #666;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Inter', 'Noto Sans Sinhala', sans-serif;
            color: var(--text-color);
            -webkit-print-color-adjust: exact;
            margin: 0;
        }

        /* --- SMART ACTION BAR --- */
        .action-bar {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-200%);
            z-index: 3000;
            display: flex;
            gap: 12px;
            background: rgba(35, 35, 35, 0.85);
            backdrop-filter: blur(12px);
            padding: 10px 25px;
            border-radius: 50px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .action-bar.visible { transform: translateX(-50%) translateY(0); }
        .hover-trigger { position: fixed; top: 0; left: 0; width: 100%; height: 20px; z-index: 2999; }
        
        .action-bar .btn { border-radius: 50px; padding: 8px 18px; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; border: none; cursor: pointer; }
        .btn-glass-light { background: rgba(255, 255, 255, 0.15); color: #eee; }
        .btn-glass-light:hover { background: rgba(255, 255, 255, 0.3); color: #fff; }
        .btn-glass-primary { background: var(--primary-color); color: #fff; box-shadow: 0 4px 15px rgba(39, 177, 157, 0.4); }
        .btn-glass-primary:hover { background: #209c8a; transform: scale(1.05); }
        .btn-glass-warning { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
        .btn-glass-warning:hover { background: rgba(255, 193, 7, 0.4); color: #fff; }
        .btn-glass-success { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.3); }
        .btn-glass-success:hover { background: #28a745; color: #fff; }

        /* --- PAGE LAYOUT --- */
        .page-container {
            max-width: <?php echo $is_landscape ? '297mm' : '210mm'; ?>;
            min-height: <?php echo $is_landscape ? '210mm' : '297mm'; ?>;
            margin: 40px auto;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 8px;
            padding: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            padding-bottom: 70px;
            box-sizing: border-box;
        }

        /* Header */
        .header-section {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .company-logo img { max-height: 70px; margin-bottom: 10px; }
        .company-info h2 { font-weight: 800; color: #144f6a; margin: 0; font-size: 1.8rem; }
        .company-info p { margin: 2px 0; font-size: 0.9rem; color: var(--secondary-text); }
        .document-title h1 { font-size: 3rem; font-weight: 900; color: var(--primary-color); margin: 0; opacity: 0.15; text-transform: uppercase; line-height: 0.8; }
        .document-title .real-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-top: -15px; display: block; text-transform: uppercase; letter-spacing: 2px; }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
            align-items: start;
        }
        .info-box h5 { font-size: 0.85rem; text-transform: uppercase; color: #999; font-weight: 700; margin-bottom: 12px; letter-spacing: 1px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .info-box .client-name { font-size: 1.2rem; font-weight: 700; color: #000; margin-bottom: 5px; }
        .info-box address { font-style: normal; font-size: 0.95rem; line-height: 1.7; color: var(--secondary-text); }
        .client-detail-row { margin-bottom: 4px; color: var(--secondary-text); font-size: 0.95rem; display: flex; align-items: center; }
        .client-detail-row i { width: 20px; text-align: center; margin-right: 8px; }

        /* Reference Info Alignment */
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 3px 0; font-size: 0.85rem; vertical-align: top; }
        .meta-table td:first-child { color: var(--secondary-text); padding-right: 15px; font-weight: 600; width: 140px; white-space: nowrap; }
        .meta-table td:last-child { font-weight: 700; color: #333; text-align: right; white-space: nowrap; }

        /* Items Table */
        .modern-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .modern-table thead th { background-color: var(--primary-color) !important; color: #fff !important; padding: 12px 10px; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; border: none; }
        .modern-table tbody td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; font-size: 0.9rem; }
        .modern-table tbody tr:nth-child(even) { background-color: #fcfcfc; }
        .item-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; }
        .item-name { font-weight: 700; color: #333; display: block; margin-bottom: 2px; }
        .item-desc { font-size: 0.8rem; color: #777; line-height: 1.4; font-style: italic; }

        /* Totals */
        .totals-wrapper { display: flex; justify-content: flex-end; margin-bottom: 30px; page-break-inside: avoid; }
        .totals-table { width: 350px; border-collapse: collapse; }
        .totals-table td { padding: 8px 0; vertical-align: middle; }
        .totals-table td:first-child { text-align: right; padding-right: 20px; color: #6c757d; font-weight: 600; font-size: 0.95rem; width: 50%; }
        .totals-table td:last-child { text-align: right; color: #333; font-weight: 700; font-size: 1rem; width: 50%; }
        .grand-total-row td { border-top: 2px solid var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-top: 15px; padding-bottom: 15px; margin-top: 10px; }
        .grand-total-row .label { color: var(--primary-color) !important; font-size: 1.1rem; font-weight: 800; text-transform: uppercase; }
        .grand-total-row .value { color: var(--primary-color) !important; font-size: 1.3rem; font-weight: 800; }
        .currency-code { font-size: 0.8em; margin-right: 5px; opacity: 0.8; }

        /* Terms & Signatures */
        .terms-wrapper { margin-bottom: 40px; page-break-inside: avoid; }
        .terms-box h6 { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; color: #333; margin-bottom: 10px; }
        .terms-box p { font-family: 'Noto Sans Sinhala', sans-serif; font-size: 0.85rem; color: #666; line-height: 1.6; white-space: pre-line; }
        
        .signature-area { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; padding-top: 40px; page-break-inside: avoid; }
        .signature-line { width: 200px; border-top: 1px dashed #ccc; text-align: center; padding-top: 10px; font-size: 0.85rem; color: #999; }

        /* Footer Credit - Absolute Bottom */
        .footer-credit {
            display: none; 
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 0.75rem;
            color: #aaa;
            padding-top: 10px;
            border-top: 1px solid #eee;
            background-color: #fff;
        }

        /* --- PRINT SETTINGS --- */
        @media print {
            @page { size: <?php echo $orientation; ?>; margin: 10mm; }
            body { background: #fff; margin: 0; }
            .action-bar, .hover-trigger { display: none !important; }
            
            .page-container { 
                box-shadow: none; 
                margin: 0; padding: 0; 
                padding-bottom: 60px; /* Space for footer */
                width: 100%; max-width: none; border: none; 
                min-height: 100vh;
                display: block; 
            }
            .modern-table thead { display: table-header-group; }
            .modern-table tbody tr { page-break-inside: avoid; }
            
            .footer-credit { 
                display: block !important;
                position: absolute;
                bottom: 0;
                left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .action-bar { top: auto; bottom: 20px; transform: translateX(-50%) translateY(0); width: 90%; justify-content: space-around; }
            .hover-trigger { display: none; }
            .btn span { display: none; }
        }
    </style>
</head>
<body>

    <div class="hover-trigger" id="topTrigger"></div>

    <div class="action-bar no-print" id="floatingBar">
        <a href="quotations.php" class="btn btn-glass-light" title="Back to List"><i class="fas fa-arrow-left"></i> <span>Back</span></a>
        <a href="view_quotation.php?id=<?php echo $quotation_id; ?>&orientation=<?php echo $is_landscape ? 'portrait' : 'landscape'; ?>" class="btn btn-glass-light" title="Rotate Page"><i class="fas fa-sync-alt"></i> <span><?php echo $is_landscape ? 'Portrait' : 'Landscape'; ?></span></a>
        
        <a href="quotations.php?edit_id=<?php echo $quotation_id; ?>" class="btn btn-glass-warning" title="Edit Quotation"><i class="fas fa-edit"></i> <span>Edit</span></a>
        
        <button onclick="saveAsImage(this)" class="btn btn-glass-success" title="Save as HD JPG"><i class="fas fa-file-image"></i> <span>Save JPG</span></button>
        <button onclick="window.print()" class="btn btn-glass-primary" title="Print PDF"><i class="fas fa-print"></i> <span>Print PDF</span></button>
    </div>

    <div class="page-container" id="captureArea">
        
        <header class="header-section">
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
                <h1>QUOTE</h1>
                <span class="real-title">QUOTATION</span>
            </div>
        </header>

        <div class="info-grid">
            <div class="info-box">
                <h5>Quotation For:</h5>
                <div class="client-name"><?php echo htmlspecialchars($quote['client_name']); ?></div>
                <div class="client-details">
                    <div class="client-detail-row"><?php echo nl2br(htmlspecialchars($quote['client_address'])); ?></div>
                    <?php if($quote['client_phone']): ?>
                        <div class="client-detail-row"><i class="fas fa-phone-alt text-muted"></i> <?php echo htmlspecialchars($quote['client_phone']); ?></div>
                    <?php endif; ?>
                    <?php if($quote['client_email']): ?>
                        <div class="client-detail-row"><i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($quote['client_email']); ?></div>
                    <?php elseif(!empty($quote['client_whatsapp'])): ?>
                        <div class="client-detail-row"><i class="fab fa-whatsapp text-success"></i> <?php echo htmlspecialchars($quote['client_whatsapp']); ?></div>
                    <?php else: ?>
                        <div class="client-detail-row"><i class="fas fa-envelope text-muted"></i> -</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-box">
                <h5>Reference Info:</h5>
                <table class="meta-table">
                    <tr><td>Quotation #:</td><td><?php echo htmlspecialchars($quote['quotation_number']); ?></td></tr>
                    <tr><td>Date Issued:</td><td><?php echo date('M d, Y', strtotime($quote['quotation_date'])); ?></td></tr>
                    <tr><td>Valid Until:</td><td><?php if($quote['valid_until']): ?><span class="text-danger fw-bold"><?php echo date('M d, Y', strtotime($quote['valid_until'])); ?></span><?php else: ?>-<?php endif; ?></td></tr>
                    <tr><td>Prepared By:</td><td><?php echo htmlspecialchars($quote['user_name']); ?></td></tr>
                </table>
            </div>
        </div>

        <table class="modern-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">#</th>
                    <th style="width: 10%; text-align: center;">Image</th>
                    <th style="width: 40%;">Description</th>
                    <th style="width: 10%; text-align: center;">Qty</th>
                    <th style="width: 15%; text-align: right;">Unit Price</th>
                    <th style="width: 20%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td style="text-align: center; color: #999;"><?php echo $index + 1; ?></td>
                    <td style="text-align: center;">
                        <?php $img_src = !empty($item['image_path']) ? $item['image_path'] : 'uploads/products/default.png'; ?>
                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Item" class="item-img">
                    </td>
                    <td>
                        <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                        <?php if(!empty($item['product_desc'])): ?>
                            <div class="item-desc"><?php echo nl2br(htmlspecialchars($item['product_desc'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?php echo $item['quantity'] + 0; ?></td> 
                    <td style="text-align: right;"><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td style="text-align: right; font-weight: 600;"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-wrapper">
            <table class="totals-table">
                <tr><td>Sub Total:</td><td><?php echo number_format($quote['sub_total'], 2); ?></td></tr>
                <?php if ($quote['tax_amount'] > 0): ?>
                <tr><td>Tax / VAT:</td><td><?php echo number_format($quote['tax_amount'], 2); ?></td></tr>
                <?php endif; ?>
                <tr><td colspan="2" style="padding: 5px;"></td></tr>
                <tr class="grand-total-row"><td class="label">Grand Total:</td><td class="value"><span class="currency-code">LKR</span><?php echo number_format($quote['grand_total'], 2); ?></td></tr>
            </table>
        </div>

        <?php if (!empty($quote['quotation_terms'])): ?>
        <div class="terms-wrapper">
            <div class="terms-box">
                <h6>Terms & Conditions:</h6>
                <p><?php echo htmlspecialchars($quote['quotation_terms']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="signature-area">
            <div class="signature-line">Prepared By</div>
            <div class="signature-line">Authorized Signature</div>
            <div class="signature-line">Customer Acceptance</div>
        </div>

        <div class="footer-credit">
            <?php echo isset($settings['invoice_footer_credit']) ? htmlspecialchars($settings['invoice_footer_credit']) : 'Thank you for your business!'; ?>
        </div>

    </div>

    <script>
        let lastScroll = 0;
        const navbar = document.getElementById('floatingBar');
        const trigger = document.getElementById('topTrigger');

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            if (currentScroll <= 0) { navbar.classList.add('visible'); } 
            else if (currentScroll < lastScroll) { navbar.classList.add('visible'); } 
            else { navbar.classList.remove('visible'); }
            lastScroll = currentScroll;
        });

        trigger.addEventListener('mouseenter', () => { navbar.classList.add('visible'); });
        if(window.pageYOffset <= 0) navbar.classList.add('visible');

        // --- HD IMAGE SAVE ---
        function saveAsImage(btn) {
            const element = document.getElementById("captureArea");
            const footer = document.querySelector('.footer-credit');
            const originalText = btn.innerHTML;
            
            const originalFooterDisplay = footer.style.display;
            footer.style.display = 'block';

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Generating HD...</span>';
            btn.style.pointerEvents = 'none';

            html2canvas(element, {
                scale: 3, 
                useCORS: true,
                backgroundColor: "#ffffff",
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Quotation_<?php echo $quote['quotation_number']; ?>.jpg';
                link.href = canvas.toDataURL('image/jpeg', 1.0);
                link.click();
                
                btn.innerHTML = originalText;
                btn.style.pointerEvents = 'auto';
                footer.style.display = originalFooterDisplay; 
            }).catch(err => {
                console.error("Error:", err);
                alert("Image save failed.");
                btn.innerHTML = originalText;
                btn.style.pointerEvents = 'auto';
                footer.style.display = originalFooterDisplay;
            });
        }
    </script>

</body>
</html>