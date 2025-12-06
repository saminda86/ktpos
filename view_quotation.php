<?php
// File Name: view_quotation.php
// Description: Modern, Professional A4 Printable Quotation

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ආරක්ෂක පරීක්ෂාව
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db_connect.php';

// 2. ID ලබා ගැනීම සහ පරීක්ෂා කිරීම
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Quotation ID.");
}
$quotation_id = intval($_GET['id']);

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. දත්ත ලබා ගැනීම (Quotation + Client + User Details)
$sql_quote = "SELECT q.*, c.client_name, c.address AS client_address, c.phone AS client_phone, c.email AS client_email, u.name AS user_name 
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

// 4. Quotation Items ලබා ගැනීම
$items = [];
$sql_items = "SELECT * FROM quotation_items WHERE quotation_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $quotation_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

// 5. System Settings (Footer Credit සඳහා)
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

    <style>
        :root {
            --primary-color: #27b19d; /* Your Brand Color */
            --primary-dark: #1e8779;
            --text-color: #333;
            --secondary-text: #666;
            --border-color: #e0e0e0;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Inter', 'Noto Sans Sinhala', sans-serif;
            color: var(--text-color);
            -webkit-print-color-adjust: exact; /* Print Colors */
        }

        /* Paper Layout for A4 */
        .page-container {
            max-width: 210mm;
            margin: 30px auto;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 8px;
            padding: 40px; /* Reduced padding slightly for content fit */
            position: relative;
            min-height: 297mm;
        }

        /* HEADER DESIGN */
        .header-section {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .company-logo img {
            max-height: 70px;
            margin-bottom: 10px;
        }
        
        .company-info h2 {
            font-weight: 800;
            color: #144f6a;
            margin: 0;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
        }
        
        .company-info p {
            margin: 2px 0;
            font-size: 0.9rem;
            color: var(--secondary-text);
        }

        .document-title {
            text-align: right;
        }
        
        .document-title h1 {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary-color);
            margin: 0;
            opacity: 0.15; /* Watermark style */
            text-transform: uppercase;
            line-height: 0.8;
        }
        
        .document-title .real-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: -15px; /* Overlap effect */
            display: block;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* INFO GRID */
        .info-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            gap: 20px;
        }
        
        .info-box h5 {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #999;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .info-box .client-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 5px;
        }
        
        .info-box address {
            font-style: normal;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--secondary-text);
        }

        .meta-table td {
            padding: 4px 0;
            font-size: 0.95rem;
        }
        
        .meta-table td:first-child {
            color: var(--secondary-text);
            padding-right: 15px;
            font-weight: 500;
        }
        
        .meta-table td:last-child {
            font-weight: 600;
            color: #000;
            text-align: right;
        }

        /* TABLE DESIGN */
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .modern-table thead th {
            background-color: var(--primary-color) !important;
            color: #fff !important;
            padding: 12px 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .modern-table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .modern-table tbody tr:nth-child(even) {
            background-color: #fcfcfc;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            display: block;
        }
        
        .item-desc {
            font-size: 0.85rem;
            color: #777;
            margin-top: 2px;
        }

        /* TOTALS SECTION */
        .totals-container {
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 8px 0;
            text-align: right;
        }
        
        .totals-table .label {
            color: var(--secondary-text);
            font-size: 0.95rem;
        }
        
        .totals-table .value {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }
        
        .totals-table .grand-total {
            border-top: 2px solid var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding: 15px 0;
            margin-top: 10px;
        }
        
        .totals-table .grand-total .label {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .totals-table .grand-total .value {
            color: var(--primary-color);
            font-weight: 800;
            font-size: 1.4rem;
        }

        /* FOOTER */
        .footer-section {
            margin-top: 60px;
            border-top: 1px solid #eee;
            padding-top: 30px;
        }
        
        .terms-box h6 {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #333;
        }
        
        .terms-box p {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.6;
            white-space: pre-line; /* Preserves line breaks */
        }
        
        .signature-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px dashed #ccc;
            text-align: center;
            padding-top: 10px;
            font-size: 0.85rem;
            color: #999;
        }

        /* BUTTONS (Hidden in Print) */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            background: #fff;
            padding: 10px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        @media print {
            body { background: #fff; }
            .page-container {
                box-shadow: none;
                margin: 0;
                padding: 20px 0;
                width: 100%;
                border: none;
            }
            .action-bar { display: none; }
            .modern-table thead th {
                background-color: var(--primary-color) !important; /* Force print color */
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="action-bar no-print">
        <a href="quotations.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-print me-2"></i> Print Quotation
        </button>
        <a href="edit_quotation.php?id=<?php echo $quotation_id; ?>" class="btn btn-info text-white rounded-pill px-4">
            <i class="fas fa-edit me-2"></i> Edit
        </a>
    </div>

    <div class="page-container">
        
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
            <div class="info-box" style="flex: 1.5;">
                <h5>Quotation For:</h5>
                <div class="client-name"><?php echo htmlspecialchars($quote['client_name']); ?></div>
                <address>
                    <?php echo nl2br(htmlspecialchars($quote['client_address'])); ?><br>
                    <?php if($quote['client_phone']): ?><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($quote['client_phone']); ?><br><?php endif; ?>
                    <?php if($quote['client_email']): ?><i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($quote['client_email']); ?><?php endif; ?>
                </address>
            </div>
            
            <div class="info-box" style="flex: 1;">
                <h5>Reference Info:</h5>
                <table class="meta-table" style="width: 100%;">
                    <tr>
                        <td>Quotation #:</td>
                        <td><?php echo htmlspecialchars($quote['quotation_number']); ?></td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td><?php echo date('M d, Y', strtotime($quote['quotation_date'])); ?></td>
                    </tr>
                    <tr>
                        <td>Valid Until:</td>
                        <td>
                            <?php if($quote['valid_until']): ?>
                                <span class="text-danger"><?php echo date('M d, Y', strtotime($quote['valid_until'])); ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Prepared By:</td>
                        <td><?php echo htmlspecialchars($quote['user_name']); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="modern-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">#</th>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 10%; text-align: center;">Qty</th>
                    <th style="width: 15%; text-align: right;">Unit Price</th>
                    <th style="width: 20%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td style="text-align: center; color: #999;"><?php echo $index + 1; ?></td>
                    <td>
                        <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                        </td>
                    <td style="text-align: center;"><?php echo $item['quantity'] + 0; ?></td> <td style="text-align: right;"><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td style="text-align: right; font-weight: 600;"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php for($i=0; $i< max(0, 5 - count($items)); $i++): ?>
                <tr style="height: 45px;">
                    <td></td><td></td><td></td><td></td><td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="row">
            <div class="col-7">
                <div class="terms-box pe-4">
                    <?php if (!empty($quote['quotation_terms'])): ?>
                        <h6>Terms & Conditions:</h6>
                        <p><?php echo htmlspecialchars($quote['quotation_terms']); ?></p>
                    <?php endif; ?>
                    
                    <div class="alert alert-light border mt-3 p-3 text-muted small">
                        <i class="fas fa-info-circle me-1"></i> 
                        This quotation is valid until the date specified above. Prices are subject to change after the validity period.
                    </div>
                </div>
            </div>
            
            <div class="col-5">
                <div class="totals-container">
                    <table class="totals-table">
                        <tr>
                            <td class="label">Sub Total:</td>
                            <td class="value"><?php echo number_format($quote['sub_total'], 2); ?></td>
                        </tr>
                        <?php if ($quote['tax_amount'] > 0): ?>
                        <tr>
                            <td class="label">Tax / VAT:</td>
                            <td class="value"><?php echo number_format($quote['tax_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="grand-total">
                            <td class="label">GRAND TOTAL:</td>
                            <td class="value">LKR <?php echo number_format($quote['grand_total'], 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <footer class="footer-section">
            <div class="signature-area">
                <div class="signature-line">
                    Prepared By
                </div>
                <div class="signature-line">
                    Authorized Signature
                </div>
                <div class="signature-line">
                    Customer Acceptance
                </div>
            </div>
            
            <div class="text-center mt-5 text-muted small">
                <?php echo isset($settings['invoice_footer_credit']) ? htmlspecialchars($settings['invoice_footer_credit']) : 'Thank you for your business!'; ?>
            </div>
        </footer>

    </div>

</body>
</html>