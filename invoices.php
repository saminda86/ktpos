<?php
// File Name: invoices.php
// Description: Invoice Manager - Modal Based Create/Edit, Uniform Cards & Auto Focus Search

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php'; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

$userType = $_SESSION['user_type'] ?? 'User';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// =========================================================
// 1. AJAX HANDLER (LIVE SEARCH)
// =========================================================
if (isset($_GET['action']) && $_GET['action'] == 'live_search') {
    ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    try {
        $term = $conn->real_escape_string($_GET['term']);
        $results = [];

        // 1. Clients
        $sql_c = "SELECT client_name FROM clients WHERE client_name LIKE '%$term%' LIMIT 5";
        $res_c = $conn->query($sql_c);
        if($res_c) { 
            while($row = $res_c->fetch_assoc()) { 
                $results[] = [
                    'type' => 'client', 
                    'text' => $row['client_name'], 
                    'icon' => 'fa-user', 
                    'sub' => 'Customer'
                ]; 
            } 
        }

        // 2. Invoices
        $sql_i = "SELECT invoice_number, grand_total FROM invoices WHERE invoice_number LIKE '%$term%' LIMIT 5";
        $res_i = $conn->query($sql_i);
        if($res_i) { 
            while($row = $res_i->fetch_assoc()) { 
                $results[] = [
                    'type' => 'invoice', 
                    'text' => $row['invoice_number'], 
                    'icon' => 'fa-file-invoice-dollar', 
                    'sub' => 'Amt: Rs. ' . number_format($row['grand_total'], 2)
                ]; 
            } 
        }

        echo json_encode($results);
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// =========================================================
// 2. DYNAMIC DATA STATISTICS (UNIFORM & VALUE BASED)
// =========================================================
$stats = [
    'total_count' => 0,
    'total_value' => 0,
    'paid_value' => 0,
    'due_value' => 0 
];

$sql_stats = "SELECT COUNT(*) as count, SUM(grand_total) as value, payment_status FROM invoices GROUP BY payment_status";
$res_stats = $conn->query($sql_stats);

if($res_stats) { 
    while ($row = $res_stats->fetch_assoc()) { 
        $stats['total_count'] += $row['count']; 
        $stats['total_value'] += $row['value']; 
        
        if ($row['payment_status'] === 'Paid') { 
            $stats['paid_value'] += $row['value'];
        } else { 
            $stats['due_value'] += $row['value'];
        }
    } 
}

// =========================================================
// 3. FETCH DATA
// =========================================================
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; 
$start = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "WHERE 1=1"; 

if (!empty($search)) { 
    $where .= " AND (
        i.invoice_number LIKE '%$search%' 
        OR c.client_name LIKE '%$search%'
        OR i.invoice_id IN (SELECT invoice_id FROM invoice_items WHERE item_name LIKE '%$search%')
    )"; 
}

$sql = "SELECT i.*, c.client_name,
        (SELECT SUM((ii.unit_price - ii.buy_price) * ii.quantity) FROM invoice_items ii WHERE ii.invoice_id = i.invoice_id) as total_profit
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.client_id 
        $where ORDER BY i.invoice_id DESC LIMIT $start, $limit";
$result = $conn->query($sql);

$total_records = $conn->query("SELECT COUNT(*) as count FROM invoices i LEFT JOIN clients c ON i.client_id = c.client_id $where")->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// =========================================================
// 4. FETCH DATA FOR MODAL
// =========================================================
$clients = [];
$res_clients = $conn->query("SELECT client_id, client_name, phone FROM clients ORDER BY client_name ASC");
while ($row = $res_clients->fetch_assoc()) $clients[] = $row;

$products = [];
$sql_p = "SELECT p.product_id, p.product_name, p.product_code, p.sell_price, p.buy_price, p.stock_quantity, p.image_path, s.supplier_name 
          FROM products p 
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
          ORDER BY p.product_name ASC";
$res_products = $conn->query($sql_p);
while ($row = $res_products->fetch_assoc()) $products[] = $row;

$default_terms = '';
$res_settings = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_warranty_terms'");
if($res_settings && $res_settings->num_rows > 0) $default_terms = $res_settings->fetch_assoc()['setting_value'];

$products_json = json_encode($products, JSON_UNESCAPED_UNICODE);
$clients_json = json_encode($clients, JSON_UNESCAPED_UNICODE);

$page_title = 'Invoice Manager';
require_once 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* MAIN STYLES */
    body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #344767; }
    
    /* UNIFORM CARD STYLE */
    .stat-card { 
        background: #fff; 
        border-radius: 12px; 
        padding: 20px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
        border: 1px solid rgba(0,0,0,0.05); 
        display: flex; 
        align-items: center; 
        transition: 0.2s; 
        height: 100%; /* Equal Height */
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
    .icon-shape { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.4rem; color: #fff; flex-shrink: 0; }
    
    .bg-gradient-primary { background: linear-gradient(310deg, #7928ca, #ff0080); }
    .bg-gradient-success { background: linear-gradient(310deg, #17ad37, #98ec2d); }
    .bg-gradient-info { background: linear-gradient(310deg, #2152ff, #21d4fd); }
    .bg-gradient-danger { background: linear-gradient(310deg, #ea0606, #ff667c); }

    .card-table { background: #fff; border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.03); overflow: hidden; }
    .table thead th { background-color: #f8f9fa; color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 12px 20px; border-bottom: 1px solid #e9ecef; }
    .table tbody td { padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; color: #495057; }
    
    .badge-status { padding: 5px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; }
    .status-paid { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .status-unpaid { background: #f8d7da; color: #721c24; border: 1px solid #f5c2c7; }
    .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffecb5; }

    /* SEARCH STYLES */
    .search-group { position: relative; width: 100%; max-width: 400px; }
    .form-control-search { border-radius: 6px 0 0 6px !important; border: 1px solid #dee2e6; border-right: none; padding: 10px 15px; font-size: 0.95rem; box-shadow: none !important; padding-right: 40px; }
    .form-control-search:focus { border-color: #dee2e6; background: #fdfdfd; }
    .btn-search { border-radius: 0 6px 6px 0 !important; border: 1px solid #dee2e6; background: #fff; color: #6c757d; padding: 0 15px; z-index: 10; }
    .btn-clear-search { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #adb5bd; font-size: 0.9rem; cursor: pointer; z-index: 20; display: none; }
    
    #searchSuggestions { display: none; position: absolute; top: 100%; left: 0; width: 100%; background: #fff; border: 1px solid #e9ecef; border-top: none; border-radius: 0 0 8px 8px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); z-index: 1000; margin-top: -2px; }
    .s-item { padding: 10px 15px; cursor: pointer; display: flex; align-items: center; border-bottom: 1px solid #f5f5f5; }
    .s-item:hover { background-color: #f8f9fa; }
    .s-icon { width: 30px; text-align: center; margin-right: 10px; color: #adb5bd; }
    .s-text { font-weight: 600; color: #333; }
    .s-sub { font-size: 0.8rem; color: #888; margin-left: auto; }

    /* MODAL STYLES (MATCHING QUOTATION) */
    .modal-dialog-responsive { max-width: 95%; margin: 1.75rem auto; }
    @media (min-width: 992px) { .modal-dialog-responsive { max-width: 1300px; } }
    .modal-content { border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .modal-header { padding: 15px 25px; background: #fff; border-bottom: 1px solid #eee; }
    
    .table-input { border: 1px solid transparent; background: transparent; text-align: right; width: 100%; font-weight: 600; color: #344767; padding: 8px 5px; border-radius: 5px; font-size: 0.9rem; }
    .table-input:hover { background: #f9f9f9; border-color: #eee; }
    .table-input:focus { background: #fff; border-color: #7928ca; outline: none; }
    .table-input-readonly { color: #666; font-weight: 500; pointer-events: none; }
    
    .item-thumb-box { width: 35px; height: 35px; border-radius: 6px; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; background: #f8f9fa; overflow: hidden; margin-top: 5px; }
    .item-thumb-box img { width: 100%; height: 100%; object-fit: cover; }
    
    /* PROFESSIONAL SELECT2 DROPDOWN STYLING */
    .select2-container .select2-selection--single { height: auto !important; min-height: 55px; padding: 5px; border: 1px solid #e0e6ed !important; border-radius: 8px; display: flex; align-items: center; }
    .select2-container--bootstrap-5 .select2-dropdown {
        z-index: 9999; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); border: 1px solid #e0e0e0;
    }
    .select2-results__option { padding: 8px 12px !important; border-bottom: 1px solid #fcfcfc; }
    .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
        background-color: #f3f4f6 !important; color: #212529 !important;
    }
    
    /* Auto Focus Search Item Styling */
    .s2-item-container { display: flex; align-items: center; width: 100%; }
    .s2-img { width: 45px !important; height: 45px !important; object-fit: cover !important; border-radius: 6px !important; margin-right: 12px !important; border: 1px solid #e9ecef; flex-shrink: 0; background: #fff; }
    .s2-details { flex-grow: 1; overflow: hidden; display: flex; flex-direction: column; justify-content: center; line-height: 1.3; }
    .s2-name { font-size: 0.95rem; font-weight: 700; color: #333; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 2px; }
    .s2-row { font-size: 0.75rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .s2-price-box { display: flex; align-items: center; margin-left: 10px; }
    .s2-sep { color: #ddd; margin-right: 8px; font-size: 1.1rem; font-weight: 300; }
    .s2-price { font-size: 0.85rem; font-weight: 600; color: #198754; white-space: nowrap; }
    
    .text-profit { font-size: 0.8rem; font-weight: 600; text-align: right; margin-top: 5px; display: block;}
    .profit-positive { color: #17ad37; }
    .profit-negative { color: #ea0606; }
    .profit-zero { color: #888; }

    /* SERIAL MODAL Z-INDEX FIX */
    #serialModal { z-index: 1070 !important; }
    .modal-backdrop.show:nth-of-type(2) { z-index: 1065 !important; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="mb-0 fw-bold">Invoice Manager</h4><p class="text-muted small mb-0">Manage customer invoices and payments.</p></div>
        <button class="btn bg-gradient-primary text-white shadow-sm px-4" onclick="openCreateInvoiceModal()"><i class="fas fa-plus me-2"></i> Create Invoice</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card">
                <div class="icon-shape bg-gradient-primary shadow"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="ms-3">
                    <h6>Total Invoices</h6>
                    <h4><?php echo number_format($stats['total_count']); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card">
                <div class="icon-shape bg-gradient-info shadow"><i class="fas fa-coins"></i></div>
                <div class="ms-3">
                    <h6>Total Value</h6>
                    <h4>Rs. <?php echo number_format($stats['total_value'], 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card">
                <div class="icon-shape bg-gradient-success shadow"><i class="fas fa-check-double"></i></div>
                <div class="ms-3">
                    <h6>Paid Value</h6>
                    <h4>Rs. <?php echo number_format($stats['paid_value'], 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card">
                <div class="icon-shape bg-gradient-danger shadow"><i class="fas fa-exclamation-circle"></i></div>
                <div class="ms-3">
                    <h6>Due Value</h6>
                    <h4>Rs. <?php echo number_format($stats['due_value'], 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-table">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Recent Invoices</h6>
                <form method="GET" id="searchForm" class="search-group">
                    <div class="input-group">
                        <input type="text" id="mainSearch" name="search" class="form-control form-control-search" placeholder="Search Invoice or Client..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        <button type="button" class="btn-clear-search" id="clearSearchBtn"><i class="fas fa-times"></i></button>
                        <button class="btn btn-search" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    <div id="searchSuggestions"></div>
                </form>
            </div>
        </div>
        
        <div class="card-body px-0 pt-3">
            <div class="table-responsive" style="min-height: 400px;">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Invoice No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th class="text-center">Status</th>
                            <?php if($userType === 'Admin'): ?><th class="text-end text-success">Profit</th><?php endif; ?>
                            <th class="text-end">Amount</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): 
                            $status = $row['payment_status'];
                            $status_cls = ($status == 'Paid') ? 'status-paid' : (($status == 'Unpaid') ? 'status-unpaid' : 'status-pending');
                            $clientName = !empty($row['client_name']) ? $row['client_name'] : 'Unknown';
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?php echo $row['invoice_number']; ?></td>
                            <td class="text-muted small"><?php echo date('d M Y', strtotime($row['invoice_date'])); ?></td>
                            <td><div class="fw-bold text-dark"><?php echo htmlspecialchars($clientName); ?></div></td>
                            <td class="text-center"><span class="badge-status <?php echo $status_cls; ?>"><?php echo $status; ?></span></td>
                            <?php if($userType === 'Admin'): ?><td class="text-end fw-bold text-success"><?php echo number_format($row['total_profit'] ?? 0, 2); ?></td><?php endif; ?>
                            <td class="text-end fw-bold text-dark pe-3"><?php echo number_format($row['grand_total'], 2); ?></td>
                            <td class="text-end pe-4"><button class="btn btn-sm btn-light border" onclick="openActionDrawer('<?php echo $row['invoice_id']; ?>', '<?php echo $row['invoice_number']; ?>')"><i class="fas fa-ellipsis-h text-muted"></i></button></td>
                        </tr>
                        <?php endwhile; else: ?><tr><td colspan="7" class="text-center py-5 text-muted">No invoices found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-end p-3"><nav><ul class="pagination pagination-sm mb-0"><?php for ($i=1; $i<=$total_pages; $i++): ?><li class="page-item <?php echo ($i==$page)?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="actionDrawer">
    <div class="offcanvas-header bg-light border-bottom">
        <div><h6 class="text-uppercase fw-bold text-muted small mb-0">Manage Invoice</h6><h5 class="fw-bold mb-0 text-primary" id="drawerInvoiceNo">...</h5></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <a href="#" id="act_view" target="_blank" class="list-group-item list-group-item-action py-3"><i class="fas fa-eye me-3 text-info"></i> View Details</a>
            <a href="#" id="act_edit" onclick="loadEditInvoice(this)" class="list-group-item list-group-item-action py-3"><i class="fas fa-pen me-3 text-warning"></i> Edit Invoice</a>
            <div class="mt-3 border-top"><a href="#" id="act_delete" class="list-group-item list-group-item-action text-danger py-3"><i class="fas fa-trash-alt me-3"></i> Delete Permanently</a></div>
        </div>
    </div>
</div>

<div class="modal fade" id="invoiceModal" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-responsive modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom"><h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i> Create New Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light">
                <form id="invoiceForm">
                    <input type="hidden" name="action" id="invoiceFormAction" value="create"><input type="hidden" name="invoice_id" id="invoiceId">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label small fw-bold text-muted">Client</label><select class="form-select" id="client_id" name="client_id" required style="width:100%"></select></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-muted">Date</label><input type="date" class="form-control" name="invoice_date" id="invoice_date" value="<?php echo date('Y-m-d'); ?>"></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-muted">Payment Status</label>
                                    <select class="form-select" id="payment_status" name="payment_status" required>
                                        <option value="Paid" class="text-success fw-bold">Paid (ගෙවා ඇත)</option>
                                        <option value="Pending" selected class="text-warning fw-bold">Pending (අර්ධව ගෙවා ඇත)</option>
                                        <option value="Unpaid" class="text-danger fw-bold">Unpaid (නොගෙවා ඇත)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm align-middle mb-0" id="iTable">
                                    <thead class="bg-white text-uppercase text-secondary text-xs">
                                        <tr>
                                            <th width="3%" class="text-center">#</th>
                                            <th width="5%" class="text-center">Img</th>
                                            <th width="25%" style="min-width: 180px;">Product / Service</th>
                                            <th width="15%" class="text-center">Serial No / Warranty</th>
                                            
                                            <?php if($userType === 'Admin'): ?>
                                            <th width="8%" class="text-end">Cost</th>
                                            <?php endif; ?>
                                            
                                            <th width="10%" class="text-end">Price</th>
                                            <th width="8%" class="text-center">Qty</th>
                                            
                                            <?php if($userType === 'Admin'): ?>
                                            <th width="10%" class="text-end text-success">Profit</th>
                                            <?php endif; ?>
                                            
                                            <th width="10%" class="text-end">Total</th>
                                            <th width="6%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="iTableBody" class="bg-white modal-table-row"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn w-100 btn-light text-primary fw-bold py-2" onclick="addNewInvoiceRow()"><i class="fas fa-plus me-1"></i> Add Another Item</button>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body"><label class="form-label small fw-bold text-muted">Terms & Conditions</label><textarea class="form-control" name="invoice_terms" id="invoice_terms" rows="10" <?php echo ($userType != 'Admin') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($default_terms); ?></textarea></div></div></div>
                        <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body bg-white"><div class="d-flex justify-content-between mb-2 text-sm"><span>Sub Total</span><span class="fw-bold" id="dispSub">0.00</span><input type="hidden" name="sub_total" id="sub_total"></div><div class="d-flex justify-content-between align-items-center mb-2 text-sm"><span>Tax</span><input type="number" class="form-control form-control-sm text-end" style="width: 80px;" name="tax_amount" id="tax_amount" value="0.00" oninput="calcInvoiceTotals()"></div>
                        
                        <?php if($userType === 'Admin'): ?>
                        <div class="d-flex justify-content-between text-sm text-success fw-bold mb-3" id="div_total_profit"><span>Total Profit</span><span id="dispProfit">0.00</span></div>
                        <?php endif; ?>
                        
                        <div class="border-top pt-2 d-flex justify-content-between"><span class="h5 mb-0">Grand Total</span><span class="h4 mb-0 text-primary" id="dispGrand">0.00</span><input type="hidden" name="grand_total" id="grand_total"></div></div></div></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" onclick="submitInvoiceForm()" id="saveInvoiceBtn">Save Invoice</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="serialModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold small text-uppercase"><i class="fas fa-barcode me-2"></i> Enter Serial Numbers & Warranty</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-white p-3">
                <p class="small text-muted mb-1">Enter one Serial Number per line. (Target Qty: <span id="serialModalQty" class="fw-bold text-dark">0</span>)</p>
                <textarea id="serialModalInput" class="form-control" rows="8" placeholder="Paste or type serials here..."></textarea>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted fw-bold">Lines: <span id="serialLineCount">0</span></small>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="$('#serialModalInput').val('').trigger('input')">Clear</button>
                </div>
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="saveSerialFromModal()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php 
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE);
$clients_json = json_encode($clients, JSON_UNESCAPED_UNICODE);
$conn->close(); 
require_once 'footer.php'; 
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// PASS PHP DATA TO JS
const userType = "<?php echo $userType; ?>";
const productsDB = <?php echo $products_json; ?>;
const clientsDB = <?php echo $clients_json; ?>;
const invoiceModal = new bootstrap.Modal(document.getElementById('invoiceModal'));
const serialModal = new bootstrap.Modal(document.getElementById('serialModal'));
const actionDrawer = new bootstrap.Offcanvas(document.getElementById('actionDrawer'));
let invoiceRowCount = 0;
let currentSerialRowId = null;

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

$(document).ready(function() {
    let searchInput = $('#mainSearch'); let suggestionsBox = $('#searchSuggestions'); let clearBtn = $('#clearSearchBtn');
    if (searchInput.val().length > 0) clearBtn.show();
    searchInput.on('keyup input', function() {
        let term = $(this).val().trim();
        if(term.length > 0) { clearBtn.show(); $.ajax({ url: 'invoices.php', data: { action: 'live_search', term: term }, dataType: 'json', success: function(data) { suggestionsBox.empty(); if(data.length > 0) { data.forEach(item => { let icon = item.type === 'client' ? 'fa-user' : 'fa-file-invoice-dollar'; suggestionsBox.append(`<div class="s-item" onclick="selectSearch('${item.text}')"><div class="s-icon"><i class="fas ${icon}"></i></div><div class="w-100"><div class="d-flex justify-content-between"><span class="s-text">${item.text}</span><span class="s-sub">${item.sub}</span></div></div></div>`); }); suggestionsBox.show(); } else { suggestionsBox.hide(); } } }); } else { clearBtn.hide(); suggestionsBox.hide(); }
    });
    clearBtn.on('click', function() { searchInput.val(''); $(this).hide(); suggestionsBox.hide(); window.location.href = 'invoices.php'; });
    $(document).on('click', function(e) { if (!$(e.target).closest('.search-group').length) suggestionsBox.hide(); });
    
    // Setup Client Dropdown
    let clientOpts = '<option value="">Select Client...</option>';
    clientsDB.forEach(c => clientOpts += `<option value="${c.client_id}">${c.client_name} (${c.phone})</option>`);
    $('#invoiceForm #client_id').html(clientOpts).select2({ 
        dropdownParent: $('#invoiceModal'), 
        theme: 'bootstrap-5' 
    });
    $(document).on('select2:open', () => { document.querySelector('.select2-search__field').focus(); });
    
    // Initial row for Add Modal
    if ($('#invoiceFormAction').val() === 'create' && $('#iTableBody tr').length === 0) {
        addNewInvoiceRow();
    }

    // Serial Modal Listener
    $('#serialModalInput').on('input', function() {
        let lines = $(this).val().split(/\r\n|\r|\n/).filter(line => line.trim() !== "").length;
        $('#serialLineCount').text(lines);
    });
});

function selectSearch(text) { $('#mainSearch').val(text); $('#searchSuggestions').hide(); $('#searchForm').submit(); }

// ==========================================================
// MODAL & FORM LOGIC (Auto Focus Search & Image Fix)
// ==========================================================

function formatProduct(state) {
    if (!state.id) return state.text;
    let p = productsDB.find(x => x.product_id == state.id);
    if (!p) return state.text;
    
    let img = p.image_path ? p.image_path : 'uploads/products/default.png';
    let sup = p.supplier_name ? p.supplier_name : 'No Supplier';
    let isService = (parseFloat(p.buy_price) <= 0 && parseInt(p.stock_quantity) <= 0);
    let stockInfo = isService ? "Service" : `Stock: ${p.stock_quantity}`;
    let stockClass = (!isService && p.stock_quantity <= 0) ? "text-danger" : "text-muted";

    return $(`
        <div class="s2-item-container">
            <img src="${img}" class="s2-img">
            <div class="s2-details">
                <div class="s2-name">${p.product_name}</div>
                <div class="s2-row ${stockClass}"><i class="fas fa-box-open" style="font-size:0.7em"></i> ${stockInfo} &nbsp;|&nbsp; <i class="fas fa-truck" style="font-size:0.7em"></i> ${sup}</div>
            </div>
            <div class="s2-price-box">
                <span class="s2-price">Rs. ${parseFloat(p.sell_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
            </div>
        </div>
    `);
}
function formatProductSelection(state) { 
    if (!state.id) return state.text; 
    let p = productsDB.find(x => x.product_id == state.id); 
    if (!p) return state.text; 
    return $(`<div style="line-height:1.1"><div>${p.product_name}</div><small class="text-muted">${p.supplier_name || 'N/A'}</small></div>`); 
}

window.openCreateInvoiceModal = function(id = null) { 
    invoiceRowCount = 0; 
    $('#invoiceForm')[0].reset(); 
    $('#invoiceFormAction').val('create'); 
    $('#invoiceId').val(''); 
    $('#invoiceForm #client_id').val('').trigger('change'); 
    $('#iTableBody').empty(); 
    $('#invoiceModal .modal-title').html('<i class="fas fa-file-invoice-dollar me-2 text-primary"></i> Create New Invoice');
    $('#saveInvoiceBtn').text('Save Invoice');
    $('#dispSub, #dispGrand, #dispProfit').text('0.00'); 
    addNewInvoiceRow(); 
    invoiceModal.show(); 
}

// Function to load data for edit (Called from Action Drawer)
window.loadEditInvoice = function(element) {
    actionDrawer.hide();
    const invoiceId = $(element).data('id');
    
    // Fetch current invoice data via AJAX
    $.ajax({
        url: 'invoice_load.php', 
        type: 'GET',
        data: { id: invoiceId },
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                const invoice = res.data.invoice;
                const items = res.data.items;
                
                openCreateInvoiceModal(); // Reset form
                $('#invoiceFormAction').val('update');
                $('#invoiceId').val(invoice.invoice_id);
                
                $('#invoiceModal .modal-title').html('<i class="fas fa-edit me-2 text-warning"></i> Edit Invoice: ' + invoice.invoice_number);
                $('#saveInvoiceBtn').text('Update Invoice');

                $('#invoiceForm #client_id').val(invoice.client_id).trigger('change');
                $('#invoice_date').val(invoice.invoice_date);
                $('#payment_status').val(invoice.payment_status);
                $('#invoice_terms').val(invoice.invoice_terms);
                $('#tax_amount').val(parseFloat(invoice.tax_amount).toFixed(2));
                
                $('#iTableBody').empty();
                items.forEach(item => {
                    let p = productsDB.find(x => x.product_id == item.product_id);
                    if (p) { item.buy_price = p.buy_price; item.image_path = p.image_path; }
                    addNewInvoiceRow(item);
                });
                calcInvoiceTotals();
                invoiceModal.show();
            } else {
                Swal.fire({ title: 'දෝෂයක්!', text: res.message, icon: 'error' });
            }
        },
        error: function() { Swal.fire({ title: 'තාක්ෂණික දෝෂයක්!', text: "දත්ත ලබාගැනීමේ දෝෂයක් සිදුවිය.", icon: 'error' }); }
    });
}


function addNewInvoiceRow(data = null) {
    invoiceRowCount++;
    let opts = '<option value="">Search Item...</option>';
    productsDB.forEach(p => { 
        let sel = (data && data.product_id == p.product_id) ? 'selected' : ''; 
        opts += `<option value="${p.product_id}" ${sel}>${p.product_name}</option>`; 
    });
    
    let qty = data ? parseFloat(data.quantity) : 1; 
    let price = data ? parseFloat(data.unit_price).toFixed(2) : '0.00'; 
    let buy = data ? parseFloat(data.buy_price).toFixed(2) : '0.00'; 
    let serial = data ? data.serial_number : '';
    let name = data ? data.item_name : '';
    let imgPath = data && data.image_path ? data.image_path : ''; 
    let imgHtml = imgPath ? `<img src="${imgPath}" class="w-100 h-100" style="object-fit:cover">` : `<i class="fas fa-box text-secondary"></i>`;
    
    let costField = (userType === 'Admin') 
        ? `<input type="number" class="table-input table-input-readonly" name="items[${invoiceRowCount}][buy_price]" id="buy-${invoiceRowCount}" value="${buy}" step="0.01" readonly tabindex="-1">`
        : `<input type="hidden" name="items[${invoiceRowCount}][buy_price]" id="buy-${invoiceRowCount}" value="${buy}">`;
    let costTd = (userType === 'Admin') ? `<td>${costField}</td>` : `${costField}`; // Hidden input if not admin

    let profitTd = (userType === 'Admin')
        ? `<td><input type="text" class="table-input table-input-readonly text-profit profit-zero" id="profit-${invoiceRowCount}" value="0.00" readonly tabindex="-1"></td>`
        : ``; 

    let html = `<tr id="row-${invoiceRowCount}">
        <td class="text-center row-number-cell"><span class="row-number-text">${invoiceRowCount}</span></td>
        <td class="text-center"><div class="item-thumb-box" id="thumb-box-${invoiceRowCount}">${imgHtml}</div></td>
        
        <td>
            <select class="form-select item-select" id="sel-${invoiceRowCount}" name="items[${invoiceRowCount}][product_id]" onchange="itemSelected(this, ${invoiceRowCount})" style="width:100%" required>
                ${opts}
            </select>
            <input type="hidden" name="items[${invoiceRowCount}][item_name]" id="name-${invoiceRowCount}" value="${name}">
        </td>
        
        <td>
            <div class="input-group input-group-sm">
                <textarea class="form-control" name="items[${invoiceRowCount}][serial_number]" id="serial-${invoiceRowCount}" rows="1" placeholder="Serial/Warranty" style="resize:none; font-size:0.85rem;">${serial}</textarea>
                <button type="button" class="btn btn-outline-secondary" onclick="openSerialPopup(${invoiceRowCount})" title="Open Serial Editor"><i class="fas fa-list"></i></button>
            </div>
        </td>
        
        ${costTd}
        
        <td><input type="number" class="table-input" name="items[${invoiceRowCount}][unit_price]" id="price-${invoiceRowCount}" value="${price}" step="0.01" oninput="calcInvoiceRow(${invoiceRowCount})" required></td>
        <td><input type="number" class="table-input text-center" name="items[${invoiceRowCount}][quantity]" id="qty-${invoiceRowCount}" value="${qty}" step="any" oninput="calcInvoiceRow(${invoiceRowCount})" required></td>
        
        ${profitTd}
        
        <td><input type="text" class="table-input table-input-readonly fw-bold text-dark" id="total-${invoiceRowCount}" value="0.00" readonly tabindex="-1"></td>
        <td class="text-center"><button type="button" class="btn btn-sm text-danger border-0 bg-transparent" onclick="removeInvoiceRow(${invoiceRowCount})" tabindex="-1"><i class="fas fa-times"></i></button></td>
    </tr>`;

    $('#iTableBody').append(html);
    $(`#sel-${invoiceRowCount}`).select2({ 
        dropdownParent: $('#invoiceModal'), 
        theme: 'bootstrap-5', 
        templateResult: formatProduct, 
        templateSelection: formatProductSelection, 
        width: '100%' 
    });
    if(data) calcInvoiceRow(invoiceRowCount);
    const newRow = document.getElementById(`row-${invoiceRowCount}`);
    if(newRow) { newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
}

function openSerialPopup(id) {
    currentSerialRowId = id;
    let val = $(`#serial-${id}`).val();
    $('#serialModalInput').val(val);
    $('#serialModalQty').text($(`#qty-${id}`).val());
    
    let lines = val.split(/\r\n|\r|\n/).filter(line => line.trim() !== "").length;
    $('#serialLineCount').text(lines);
    
    serialModal.show();
}

function saveSerialFromModal() {
    if(currentSerialRowId !== null) {
        $(`#serial-${currentSerialRowId}`).val($('#serialModalInput').val());
        serialModal.hide();
    }
}

function itemSelected(select, id) { 
    let pid = $(select).val(); 
    let p = productsDB.find(x => x.product_id == pid); 
    if (!p) return; 
    
    $(`#name-${id}`).val(p.product_name); 
    $(`#price-${id}`).val(parseFloat(p.sell_price).toFixed(2)); 
    $(`#buy-${id}`).val(parseFloat(p.buy_price).toFixed(2)); 
    
    let displayHtml = p.image_path ? `<img src="${p.image_path}" class="w-100 h-100" style="object-fit:cover">` : `<i class="fas fa-box text-secondary"></i>`; 
    $(`#thumb-box-${id}`).html(displayHtml); 
    
    calcInvoiceRow(id); 
}

function calcInvoiceRow(id) { 
    let qty = parseFloat($(`#qty-${id}`).val()) || 0; 
    let price = parseFloat($(`#price-${id}`).val()) || 0; 
    let buy = parseFloat($(`#buy-${id}`).val()) || 0; 
    
    let lineTotal = (qty * price).toFixed(2);
    let lineProfit = ((price - buy) * qty).toFixed(2);
    
    $(`#total-${id}`).val(lineTotal); 
    
    if (userType === 'Admin') {
        let profitEl = $(`#profit-${id}`);
        profitEl.val(lineProfit);
        profitEl.removeClass('profit-positive profit-negative profit-zero');
        if (lineProfit > 0) profitEl.addClass('profit-positive');
        else if (lineProfit < 0) profitEl.addClass('profit-negative');
        else profitEl.addClass('profit-zero');
    }
    
    calcInvoiceTotals(); 
}

function removeInvoiceRow(id) { 
    const tableBody = document.getElementById('iTableBody');
    if (tableBody.rows.length <= 1) {
        Swal.fire('Warning', 'අවම වශයෙන් එක් භාණ්ඩයක්වත් තිබිය යුතුයි.', 'warning');
        return;
    }
    $(`#row-${id}`).remove(); 
    calcInvoiceTotals(); 
}

function calcInvoiceTotals() { 
    let sub = 0;
    let totProfit = 0;
    
    $('input[id^="total-"]').each(function() { sub += parseFloat($(this).val()) || 0; }); 
    if (userType === 'Admin') {
        $('input[id^="profit-"]').each(function() { totProfit += parseFloat($(this).val()) || 0; });
    }
    
    let tax = parseFloat($('#invoiceForm #tax_amount').val()) || 0; 
    let grandTotal = sub + tax;
    
    $('#invoiceForm #dispSub').text(sub.toFixed(2)); 
    $('#invoiceForm #sub_total').val(sub.toFixed(2)); 
    $('#invoiceForm #dispGrand').text(grandTotal.toFixed(2)); 
    $('#invoiceForm #grand_total').val(grandTotal.toFixed(2)); 
    
    if (userType === 'Admin') {
        $('#invoiceForm #dispProfit').text(totProfit.toFixed(2));
    }
}

function submitInvoiceForm() { 
    if($('#invoiceForm #client_id').val() == '' || $('input[id^="total-"]').length == 0) { 
        Swal.fire({ title: 'දත්ත මදි!', text: 'කරුණාකර පාරිභෝගිකයෙකු තෝරා භාණ්ඩ ඇතුළත් කරන්න.', icon: 'warning', confirmButtonText: 'හරි' }); return; 
    } 
    
    const isEditing = $('#invoiceId').val() !== '';
    const actionUrl = isEditing ? 'invoice_process.php?action=update&id=' + $('#invoiceId').val() : 'invoice_process.php?action=create';
    
    Swal.fire({ title: 'ඉන්වොයිසිය සුරැකීමට අවශ්‍යද?', text: 'ඉන්වොයිසිය සුරැකීමට/යාවත්කාලීන කිරීමට තහවුරු කරන්න.', icon: 'question', showCancelButton: true, confirmButtonText: 'ඔව්, සුරකින්න', cancelButtonText: 'අවලංගු කරන්න' }).then((result) => {
        if (result.isConfirmed) {
             Swal.fire({ title: 'සකසමින් පවතී...', text: 'කරුණාකර රැඳී සිටින්න.', didOpen: () => { Swal.showLoading() }, allowOutsideClick: false });

            $.post(actionUrl, $('#invoiceForm').serialize(), function(res) { 
                if(res.status == 'success') { 
                    invoiceModal.hide(); 
                    Toast.fire({ icon: 'success', title: 'සාර්ථකයි!', text: res.message }).then(() => location.reload()); 
                } else { 
                    Swal.fire({ title: 'දෝෂයක්!', text: res.message, icon: 'error', confirmButtonText: 'හරි' }); 
                } 
            }, 'json').fail(function() {
                Swal.fire({ title: 'තාක්ෂණික දෝෂයක්!', text: "සර්වර් සම්බන්ධතා දෝෂයක් සිදුවිය.", icon: 'error' });
            });
        }
    });
}

// Action Drawer Logic (Update Edit Link)
function openActionDrawer(id, invoiceNo) {
    $('#drawerInvoiceNo').text(invoiceNo);
    $('#act_view').attr('href', 'view_invoice.php?id=' + id);
    // Updated Edit Action to call modal function
    $('#act_edit').attr('data-id', id); 
    $('#act_delete').attr('onclick', `deleteInvoice(${id})`);
    actionDrawer.show();
}

function deleteInvoice(id) {
    actionDrawer.hide();
    Swal.fire({
        title: 'Delete Invoice?',
        html: "<p>Are you sure you want to delete this invoice permanently? <br><strong class='text-danger'>Stock will be returned to inventory.</strong></p>",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
             // Redirect to delete_invoice.php which handles stock return
             window.location.href = 'delete_invoice.php?id=' + id;
        }
    });
}
</script>