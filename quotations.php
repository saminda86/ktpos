<?php
// File Name: quotations.php
// Description: Professional Dashboard + Robust Duplicate Logic (Error Handling Added)

// =========================================================
// 1. SESSION & DB CONNECTION
// =========================================================
// Ensure no whitespace before this php tag!
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php'; 

// Disable error display to prevent breaking JSON (Log them instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
    // Send JSON error if connection fails during AJAX
    if(isset($_GET['action']) || isset($_POST['action'])) {
        echo json_encode(['status'=>'error', 'message'=>'DB Connection Failed: '.$conn->connect_error]); exit;
    }
    die("Connection failed: " . $conn->connect_error); 
}

// =========================================================
// 2. AJAX HANDLERS
// =========================================================

// --- LIVE SEARCH ---
if (isset($_GET['action']) && $_GET['action'] == 'live_search') {
    ob_clean(); // Clear buffer
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $term = $conn->real_escape_string($_GET['term']);
        $results = [];

        $sql_c = "SELECT client_name FROM clients WHERE client_name LIKE '%$term%' LIMIT 5";
        $res_c = $conn->query($sql_c);
        if($res_c) { while($row = $res_c->fetch_assoc()) { $results[] = ['type' => 'client', 'text' => $row['client_name'], 'icon' => 'fa-user', 'sub' => 'Customer']; } }

        $sql_q = "SELECT quotation_number, grand_total FROM quotations WHERE quotation_number LIKE '%$term%' LIMIT 5";
        $res_q = $conn->query($sql_q);
        if($res_q) { while($row = $res_q->fetch_assoc()) { $results[] = ['type' => 'quote', 'text' => $row['quotation_number'], 'icon' => 'fa-file-invoice-dollar', 'sub' => 'Amt: ' . number_format($row['grand_total'], 2)]; } }
        
        echo json_encode($results);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// --- DUPLICATE QUOTATION (ROBUST LOGIC) ---
if (isset($_POST['action']) && $_POST['action'] == 'duplicate') {
    ob_clean(); // Clear buffer
    header('Content-Type: application/json');
    
    try {
        $old_id = (int)$_POST['id'];
        $q_old = $conn->query("SELECT * FROM quotations WHERE quotation_id = $old_id")->fetch_assoc();
        
        if($q_old) {
            $date = date('Y-m-d');
            $valid = date('Y-m-d', strtotime('+14 days'));
            
            // --- FIX: Get Valid User ID ---
            $current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            if($current_user_id == 0) {
                // If no session, try to get the first user from DB to avoid FK constraint fail
                $u_res = $conn->query("SELECT user_id FROM users LIMIT 1");
                if($u_res && $u_res->num_rows > 0) {
                    $current_user_id = $u_res->fetch_assoc()['user_id'];
                } else {
                    // If no users exist, use the old one
                    $current_user_id = $q_old['user_id'];
                }
            }

            // Insert New Quotation
            $sql_ins = "INSERT INTO quotations (user_id, client_id, quotation_date, valid_until, sub_total, tax_amount, grand_total, quotation_terms, status) 
                        VALUES ('$current_user_id', '{$q_old['client_id']}', '$date', '$valid', '{$q_old['sub_total']}', '{$q_old['tax_amount']}', '{$q_old['grand_total']}', '{$conn->real_escape_string($q_old['quotation_terms'])}', 'Pending')";
            
            if($conn->query($sql_ins)) {
                $new_id = $conn->insert_id;
                
                // Generate New Number
                $new_num = 'QUO-' . date('Y') . '-' . str_pad($new_id, 4, '0', STR_PAD_LEFT);
                $conn->query("UPDATE quotations SET quotation_number = '$new_num' WHERE quotation_id = $new_id");
                
                // Duplicate Items
                $items = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $old_id");
                while($item = $items->fetch_assoc()) {
                    $sql_item = "INSERT INTO quotation_items (quotation_id, product_id, item_name, buy_price, unit_price, quantity, total) 
                                 VALUES ($new_id, '{$item['product_id']}', '{$conn->real_escape_string($item['item_name'])}', '{$item['buy_price']}', '{$item['unit_price']}', '{$item['quantity']}', '{$item['total']}')";
                    if(!$conn->query($sql_item)) {
                        throw new Exception("Item Insert Error: " . $conn->error);
                    }
                }
                echo json_encode(['status' => 'success', 'message' => 'Duplicate successful!', 'raw_id' => $new_id]);
            } else {
                throw new Exception("Quotation Insert Error: " . $conn->error);
            }
        } else {
            throw new Exception("Original quotation not found.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =========================================================
// 3. PAGE CONTENT
// =========================================================
$page_title = 'Quotations';
require_once 'header.php';

// Stats Logic
$stats = ['total' => 0, 'total_val' => 0, 'pending' => 0, 'accepted' => 0];
$res_stats = $conn->query("SELECT COUNT(*) as count, SUM(grand_total) as value, status FROM quotations GROUP BY status");
if($res_stats) { while ($row = $res_stats->fetch_assoc()) { $stats['total'] += $row['count']; $stats['total_val'] += $row['value']; if ($row['status'] == 'Pending') $stats['pending'] = $row['count']; elseif ($row['status'] == 'Accepted') $stats['accepted'] = $row['count']; } }
$grand_total_value = $conn->query("SELECT SUM(grand_total) as val FROM quotations")->fetch_assoc()['val'] ?? 0;

// List Logic
$limit = 10; $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; $start = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = "WHERE 1=1"; if (!empty($search)) { $where .= " AND (q.quotation_number LIKE '%$search%' OR c.client_name LIKE '%$search%')"; }

$sql = "SELECT q.*, c.client_name, (SELECT SUM((qi.unit_price - qi.buy_price) * qi.quantity) FROM quotation_items qi WHERE qi.quotation_id = q.quotation_id) as total_profit FROM quotations q LEFT JOIN clients c ON q.client_id = c.client_id $where ORDER BY q.quotation_id DESC LIMIT $start, $limit";
$result = $conn->query($sql);
$total_records = $conn->query("SELECT COUNT(*) as count FROM quotations q LEFT JOIN clients c ON q.client_id = c.client_id $where")->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Modal Logic
$clients = []; $res_c = $conn->query("SELECT client_id, client_name FROM clients ORDER BY client_name ASC"); if($res_c) { while($row = $res_c->fetch_assoc()) $clients[] = $row; }
$products = []; $sql_p = "SELECT p.product_id, p.product_name, p.product_code, p.sell_price, p.buy_price, p.stock_quantity, p.image_path, s.supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id ORDER BY p.product_name ASC";
$res_p = $conn->query($sql_p); if($res_p) { while($row = $res_p->fetch_assoc()) $products[] = $row; }
$res_s = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_warranty_terms'");
$default_terms = ($res_s && $res_s->num_rows > 0) ? $res_s->fetch_assoc()['setting_value'] : "";
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* ... (KEEP ALL YOUR EXISTING CSS HERE - SAME AS PREVIOUS) ... */
    body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #344767; }
    .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); display: flex; align-items: center; transition: 0.2s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
    .icon-shape { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.4rem; color: #fff; }
    .bg-gradient-primary { background: linear-gradient(310deg, #7928ca, #ff0080); }
    .bg-gradient-success { background: linear-gradient(310deg, #17ad37, #98ec2d); }
    .bg-gradient-info { background: linear-gradient(310deg, #2152ff, #21d4fd); }
    .bg-gradient-warning { background: linear-gradient(310deg, #f53939, #fbcf33); }
    .card-table { background: #fff; border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.03); overflow: hidden; }
    .table thead th { background-color: #f8f9fa; color: #8898aa; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 12px 20px; border-bottom: 1px solid #e9ecef; }
    .table tbody td { padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; color: #495057; }
    .badge-status { padding: 5px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; }
    .status-accepted { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffecb5; }
    .status-rejected { background: #f8d7da; color: #721c24; border: 1px solid #f5c2c7; }
    .search-group { position: relative; width: 100%; max-width: 400px; }
    .form-control-search { border-radius: 6px 0 0 6px !important; border: 1px solid #dee2e6; border-right: none; padding: 10px 15px; font-size: 0.95rem; box-shadow: none !important; padding-right: 40px; }
    .form-control-search:focus { border-color: #dee2e6; background: #fdfdfd; }
    .btn-search { border-radius: 0 6px 6px 0 !important; border: 1px solid #dee2e6; background: #fff; color: #6c757d; padding: 0 15px; z-index: 10; }
    .btn-clear-search { position: absolute; right: 50px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #adb5bd; font-size: 0.9rem; cursor: pointer; z-index: 20; display: none; }
    #searchSuggestions { display: none; position: absolute; top: 100%; left: 0; width: 100%; background: #fff; border: 1px solid #e9ecef; border-top: none; border-radius: 0 0 8px 8px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); z-index: 1000; margin-top: -2px; }
    .s-item { padding: 10px 15px; cursor: pointer; display: flex; align-items: center; border-bottom: 1px solid #f5f5f5; }
    .s-item:hover { background-color: #f8f9fa; }
    .s-icon { width: 30px; text-align: center; margin-right: 10px; color: #adb5bd; }
    .s-text { font-weight: 600; color: #344767; font-size: 0.9rem; }
    .s-sub { font-size: 0.75rem; color: #8392ab; margin-left: auto; }
    .modal-dialog-responsive { max-width: 95%; margin: 1.75rem auto; }
    @media (min-width: 992px) { .modal-dialog-responsive { max-width: 1300px; } }
    .modal-content { border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .modal-header { padding: 15px 25px; background: #fff; border-bottom: 1px solid #eee; }
    .form-label-modern { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #8898aa; margin-bottom: 5px; display: block; }
    .form-control-modern { border: 1px solid #e0e6ed; border-radius: 8px; padding: 10px 15px; font-size: 0.9rem; transition: 0.2s; background-color: #fff; }
    .form-control-modern:focus { border-color: #7928ca; box-shadow: 0 0 0 3px rgba(121, 40, 202, 0.1); outline: none; }
    .modal-table-header { background-color: #f8f9fa; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #6c757d; }
    .modal-table-row td { vertical-align: top !important; padding: 12px 10px; border-bottom: 1px solid #f0f0f0; }
    .row-number-text { display: block; padding-top: 15px; color: #adb5bd; font-weight: 600; }
    .table-input { border: 1px solid transparent; background: transparent; text-align: right; width: 100%; font-weight: 600; color: #344767; padding: 8px 5px; border-radius: 5px; font-size: 0.9rem; }
    .table-input:hover { background: #f9f9f9; border-color: #eee; }
    .table-input:focus { background: #fff; border-color: #7928ca; outline: none; }
    .table-input-readonly { color: #666; font-weight: 500; pointer-events: none; }
    .item-thumb-box { width: 35px; height: 35px; border-radius: 6px; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; background: #f8f9fa; overflow: hidden; margin-top: 5px; }
    .item-thumb-box img { width: 100%; height: 100%; object-fit: cover; }
    .select2-container .select2-selection--single { height: auto !important; min-height: 55px; padding: 5px; border: 1px solid #e0e6ed !important; border-radius: 8px; display: flex; align-items: center; }
    .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option { padding: 4px 8px; font-size: 0.85rem; }
    .s2-item-container { display: flex; align-items: flex-start; padding: 2px 0; }
    .s2-img { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; margin-right: 8px; border: 1px solid #eee; flex-shrink: 0; margin-top: 2px; }
    .s2-details { flex-grow: 1; overflow: hidden; line-height: 1.1; }
    .s2-name { font-weight: 600; color: #333; font-size: 0.8rem; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 1px; }
    .s2-supplier { font-size: 0.7rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 200px; }
    .s2-stock-row { margin-top: 2px; }
    .s2-price { font-weight: 700; color: #27b19d; font-size: 0.85rem; margin-left: 5px; white-space: nowrap; align-self: flex-start; }
    .stock-badge { font-size: 0.65rem; padding: 0px 5px; border-radius: 3px; font-weight: 600; display: inline-block; }
    .stock-in { background-color: #e6fffa; color: #00a383; border: 1px solid #b2f5ea; }
    .stock-out { background-color: #fff5f5; color: #e53e3e; border: 1px solid #fed7d7; }
    .summary-card { background: #f8f9fa; border-radius: 12px; padding: 20px; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; color: #6c757d; }
    .summary-total { border-top: 1px dashed #dee2e6; margin-top: 15px; padding-top: 15px; display: flex; justify-content: space-between; align-items: center; }
    .summary-total span { font-size: 1.1rem; font-weight: 700; color: #344767; }
    .summary-total .amount { font-size: 1.5rem; color: #7928ca; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="mb-0 fw-bold">Quotation Manager</h4><p class="text-muted small mb-0">Manage and track your quotations efficiently.</p></div>
        <button class="btn bg-gradient-primary text-white shadow-sm px-4" onclick="openCreateModal()"><i class="fas fa-plus me-2"></i> Create New</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-primary shadow"><i class="fas fa-file-invoice"></i></div><div class="ms-3"><h6>Total Quotes</h6><h4><?php echo number_format($stats['total']); ?></h4></div></div></div>
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-info shadow"><i class="fas fa-wallet"></i></div><div class="ms-3"><h6>Total Value</h6><h4><?php echo number_format($grand_total_value, 2); ?></h4></div></div></div>
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-warning shadow"><i class="fas fa-hourglass-half"></i></div><div class="ms-3"><h6>Pending</h6><h4><?php echo number_format($stats['pending']); ?></h4></div></div></div>
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-success shadow"><i class="fas fa-check-circle"></i></div><div class="ms-3"><h6>Accepted</h6><h4><?php echo number_format($stats['accepted']); ?></h4></div></div></div>
    </div>

    <div class="card card-table">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">All Quotations</h6>
                <form method="GET" id="searchForm" class="search-group">
                    <div class="input-group">
                        <input type="text" id="mainSearch" name="search" class="form-control form-control-search" placeholder="Search Client or ID..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
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
                    <thead><tr><th class="ps-4">ID</th><th>Client</th><th>Date</th><th class="text-center">Status</th><th class="text-end">Profit</th><th class="text-end">Amount</th><th class="text-end pe-4">Action</th></tr></thead>
                    <tbody><?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): ?><tr><td class="ps-4 fw-bold text-primary"><?php echo $row['quotation_number']; ?></td><td><div class="fw-bold text-dark"><?php echo htmlspecialchars($row['client_name'] ?? 'Unknown'); ?></div></td><td class="text-muted small"><?php echo date('d M Y', strtotime($row['quotation_date'])); ?></td><td class="text-center"><?php $s = $row['status']; $cls = ($s=='Accepted')?'status-accepted':(($s=='Rejected')?'status-rejected':'status-pending'); ?><span class="badge-status <?php echo $cls; ?>"><?php echo $s; ?></span></td><td class="text-end fw-bold text-success"><?php echo number_format($row['total_profit'] ?? 0, 2); ?></td><td class="text-end fw-bold text-dark pe-3"><?php echo number_format($row['grand_total'], 2); ?></td><td class="text-end pe-4"><button class="btn btn-sm btn-light border" onclick="openActionDrawer('<?php echo $row['quotation_id']; ?>', '<?php echo $row['quotation_number']; ?>', '<?php echo $row['status']; ?>')"><i class="fas fa-ellipsis-h text-muted"></i></button></td></tr><?php endwhile; else: ?><tr><td colspan="7" class="text-center py-5 text-muted">No records found.</td></tr><?php endif; ?></tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?><div class="d-flex justify-content-end p-3"><nav><ul class="pagination pagination-sm mb-0"><?php for ($i=1; $i<=$total_pages; $i++): ?><li class="page-item <?php echo ($i==$page)?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="actionDrawer">
    <div class="offcanvas-header bg-light border-bottom"><div><h6 class="text-uppercase fw-bold text-muted small mb-0">Manage</h6><h5 class="fw-bold mb-0 text-primary" id="drawerQuoteNo">...</h5></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body p-0"><div class="list-group list-group-flush"><a href="#" id="act_view" class="list-group-item list-group-item-action py-3"><i class="fas fa-eye me-3 text-info"></i> View Details</a><a href="#" id="act_edit" class="list-group-item list-group-item-action py-3"><i class="fas fa-pen me-3 text-warning"></i> Edit Quotation</a><a href="#" id="act_duplicate" class="list-group-item list-group-item-action py-3"><i class="fas fa-copy me-3 text-secondary"></i> Duplicate Quotation</a><div id="status_actions" class="bg-light p-2 small fw-bold text-muted text-uppercase mt-2">Change Status</div><a href="#" id="act_accept" class="list-group-item list-group-item-action"><i class="fas fa-check-circle me-3 text-success"></i> Mark Accepted</a><a href="#" id="act_reject" class="list-group-item list-group-item-action"><i class="fas fa-times-circle me-3 text-danger"></i> Mark Rejected</a><div id="convert_actions" class="bg-light p-2 small fw-bold text-muted text-uppercase mt-2" style="display:none">Processing</div><a href="#" id="act_invoice" class="list-group-item list-group-item-action"><i class="fas fa-file-invoice me-3 text-primary"></i> Convert to Invoice</a><div class="mt-3 border-top"><a href="#" id="act_delete" class="list-group-item list-group-item-action text-danger py-3"><i class="fas fa-trash-alt me-3"></i> Delete Permanently</a></div></div></div>
</div>

<div class="modal fade" id="quotationModal" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-responsive modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom"><h5 class="modal-title fw-bold text-dark"><i class="fas fa-pen-nib me-2 text-primary"></i> Create Quotation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light">
                <form id="quotationForm">
                    <input type="hidden" name="action" id="formAction" value="create"><input type="hidden" name="quotation_id" id="quotationId">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label small fw-bold text-muted">Client</label><select class="form-select" id="client_id" name="client_id" required style="width:100%"></select></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-muted">Date</label><input type="date" class="form-control" name="quotation_date" id="quotation_date" value="<?php echo date('Y-m-d'); ?>"></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-muted">Valid Until</label><input type="date" class="form-control" name="valid_until" id="valid_until"></div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm align-middle mb-0" id="qTable">
                                    <thead class="bg-white text-uppercase text-secondary text-xs">
                                        <tr>
                                            <th width="3%" class="text-center">#</th>
                                            <th width="5%" class="text-center">Img</th>
                                            <th width="25%" style="min-width: 180px;">Product / Service</th>
                                            <th width="12%" class="text-end" style="min-width: 90px;">Buy Price</th>
                                            <th width="12%" class="text-end" style="min-width: 90px;">Unit Price</th>
                                            <th width="8%" class="text-center" style="min-width: 60px;">Qty</th>
                                            <th width="10%" class="text-end text-success" style="min-width: 90px;">Profit</th>
                                            <th width="13%" class="text-end" style="min-width: 100px;">Total</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="qTableBody" class="bg-white modal-table-row"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn w-100 btn-light text-primary fw-bold py-2" onclick="addNewRow()"><i class="fas fa-plus me-1"></i> Add Another Item</button>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body"><label class="form-label small fw-bold text-muted">Terms & Conditions</label><textarea class="form-control" name="quotation_terms" id="quotation_terms" rows="4"><?php echo htmlspecialchars($default_terms); ?></textarea></div></div></div>
                        <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body bg-white"><div class="d-flex justify-content-between mb-2 text-sm"><span>Sub Total</span><span class="fw-bold" id="dispSub">0.00</span><input type="hidden" name="sub_total" id="sub_total"></div><div class="d-flex justify-content-between align-items-center mb-2 text-sm"><span>Tax</span><input type="number" class="form-control form-control-sm text-end" style="width: 80px;" name="tax_amount" id="tax_amount" value="0.00" oninput="calcTotals()"></div><div class="d-flex justify-content-between text-sm text-success fw-bold mb-3"><span>Total Profit</span><span id="dispProfit">0.00</span></div><div class="border-top pt-2 d-flex justify-content-between"><span class="h5 mb-0">Grand Total</span><span class="h4 mb-0 text-primary" id="dispGrand">0.00</span><input type="hidden" name="grand_total" id="grand_total"></div></div></div></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" onclick="submitForm()">Save Quotation</button></div>
        </div>
    </div>
</div>

<?php $conn->close(); require_once 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Live Search
$(document).ready(function() {
    let searchInput = $('#mainSearch'); let suggestionsBox = $('#searchSuggestions'); let clearBtn = $('#clearSearchBtn');
    if (searchInput.val().length > 0) clearBtn.show();
    searchInput.on('keyup input', function() {
        let term = $(this).val().trim();
        if(term.length > 0) { clearBtn.show(); $.ajax({ url: 'quotations.php', data: { action: 'live_search', term: term }, dataType: 'json', success: function(data) { suggestionsBox.empty(); if(data.length > 0) { data.forEach(item => { let icon = item.type === 'client' ? 'fa-user' : 'fa-file-invoice'; suggestionsBox.append(`<div class="s-item" onclick="selectSearch('${item.text}')"><div class="s-icon"><i class="fas ${icon}"></i></div><div class="w-100"><div class="d-flex justify-content-between"><span class="s-text">${item.text}</span><span class="s-sub">${item.sub}</span></div></div></div>`); }); suggestionsBox.show(); } else { suggestionsBox.hide(); } } }); } else { clearBtn.hide(); suggestionsBox.hide(); }
    });
    clearBtn.on('click', function() { searchInput.val(''); $(this).hide(); suggestionsBox.hide(); window.location.href = 'quotations.php'; });
    $(document).on('click', function(e) { if (!$(e.target).closest('.search-group').length) suggestionsBox.hide(); });
});
function selectSearch(text) { $('#mainSearch').val(text); $('#searchSuggestions').hide(); $('#searchForm').submit(); }

// Modal Logic
const productsDB = <?php echo $products_json; ?>;
const clientsDB = <?php echo json_encode($clients); ?>;
const modal = new bootstrap.Modal(document.getElementById('quotationModal'));
const actionDrawer = new bootstrap.Offcanvas(document.getElementById('actionDrawer'));
let rowCount = 0;

$(document).ready(function() {
    let clientOpts = '<option value="">Select Client...</option>';
    clientsDB.forEach(c => clientOpts += `<option value="${c.client_id}">${c.client_name}</option>`);
    $('#client_id').html(clientOpts).select2({ dropdownParent: $('#quotationModal'), theme: 'bootstrap-5' });
    $(document).on('select2:open', () => { document.querySelector('.select2-search__field').focus(); });
});

function openActionDrawer(id, quoteNo, status) {
    $('#drawerQuoteNo').text(quoteNo); $('#act_view').attr('href', 'view_quotation.php?id=' + id); $('#act_edit').attr('onclick', `loadEdit(${id})`);
    
    // Duplicate
    $('#act_duplicate').attr('onclick', `duplicateQuote(${id})`);

    if(status === 'Pending') { $('#status_actions').show(); $('#convert_actions').hide(); $('#act_accept').attr('onclick', `updateStatus(${id}, 'Accepted')`); $('#act_reject').attr('onclick', `updateStatus(${id}, 'Rejected')`); } 
    else if(status === 'Accepted') { $('#status_actions').hide(); $('#convert_actions').show(); $('#act_invoice').attr('href', 'create_invoice.php?from_quote=' + id); } 
    else { $('#status_actions').hide(); $('#convert_actions').hide(); }
    $('#act_delete').attr('onclick', `deleteQuote(${id})`); actionDrawer.show();
}

function formatProduct(state) {
    if (!state.id) return state.text; let p = productsDB.find(x => x.product_id == state.id); if (!p) return state.text;
    let img = p.image_path ? p.image_path : 'uploads/products/default.png';
    let sup = (p.supplier_name && p.supplier_name.length > 25) ? p.supplier_name.substring(0, 25)+'...' : p.supplier_name;
    let stockDisplay = (!((p.product_code && p.product_code.startsWith('KWS')) || (parseFloat(p.buy_price) == 0 && p.stock_quantity == 0))) ? 
        ((p.stock_quantity > 0) ? `<span class="stock-badge stock-in"><i class="fas fa-check"></i> ${p.stock_quantity}</span>` : `<span class="stock-badge stock-out">No Stock</span>`) : '';
    return $(`<div class="s2-item-container"><img src="${img}" class="s2-img"><div class="s2-details"><div class="s2-name">${p.product_name}</div><div class="s2-supplier">${sup}</div><div class="s2-stock-row">${stockDisplay}</div></div><div class="s2-price">${parseFloat(p.sell_price).toFixed(2)}</div></div>`);
}
function formatProductSelection(state) { if (!state.id) return state.text; let p = productsDB.find(x => x.product_id == state.id); if (!p) return state.text; return $(`<div style="line-height:1.1"><div>${p.product_name}</div><small class="text-muted">${p.supplier_name}</small></div>`); }

function openCreateModal() { $('#quotationForm')[0].reset(); $('#formAction').val('create'); $('#quotationId').val(''); $('#client_id').val('').trigger('change'); $('#qTableBody').empty(); $('#dispSub, #dispGrand, #dispProfit').text('0.00'); addNewRow(); modal.show(); }

function addNewRow(data = null) {
    rowCount++;
    let opts = '<option value="">Search Item...</option>';
    productsDB.forEach(p => { let sel = (data && data.product_id == p.product_id) ? 'selected' : ''; opts += `<option value="${p.product_id}" ${sel}>${p.product_name}</option>`; });
    let qty = data ? data.quantity : 1; let price = data ? data.unit_price : '0.00'; let buy = data ? data.buy_price : '0.00'; let imgPath = data && data.image_path ? data.image_path : ''; let imgHtml = imgPath ? `<img src="${imgPath}" class="w-100 h-100" style="object-fit:cover">` : `<i class="fas fa-box text-secondary"></i>`;
    let html = `<tr id="row-${rowCount}"><td class="text-center row-number-cell"><span class="row-number-text">${rowCount}</span></td><td class="text-center"><div class="item-thumb-box" id="thumb-box-${rowCount}">${imgHtml}</div></td><td><select class="form-select item-select" id="sel-${rowCount}" name="items[${rowCount}][product_id]" onchange="itemSelected(this, ${rowCount})" style="width:100%" required>${opts}</select><input type="hidden" name="items[${rowCount}][item_name]" id="name-${rowCount}" value="${data?data.item_name:''}"></td><td><input type="number" class="table-input table-input-readonly" name="items[${rowCount}][buy_price]" id="buy-${rowCount}" value="${buy}" step="0.01" readonly tabindex="-1"></td><td><input type="number" class="table-input" name="items[${rowCount}][unit_price]" id="price-${rowCount}" value="${price}" step="0.01" oninput="calcRow(${rowCount})" required></td><td><input type="number" class="table-input text-center" name="items[${rowCount}][quantity]" id="qty-${rowCount}" value="${qty}" step="any" oninput="calcRow(${rowCount})" required></td><td><input type="text" class="table-input table-input-readonly text-success fw-bold" id="profit-${rowCount}" value="0.00" readonly tabindex="-1"></td><td><input type="text" class="table-input table-input-readonly fw-bold text-dark" id="total-${rowCount}" value="0.00" readonly tabindex="-1"></td><td class="text-center"><button type="button" class="btn btn-sm text-danger border-0 bg-transparent" onclick="removeRow(${rowCount})" tabindex="-1"><i class="fas fa-times"></i></button></td></tr>`;
    $('#qTableBody').append(html);
    $(`#sel-${rowCount}`).select2({ dropdownParent: $('#quotationModal'), theme: 'bootstrap-5', templateResult: formatProduct, templateSelection: formatProductSelection, width: '100%' });
    if(data) calcRow(rowCount);
}

function itemSelected(select, id) { let pid = $(select).val(); let p = productsDB.find(x => x.product_id == pid); if (!p) return; $(`#name-${id}`).val(p.product_name); $(`#price-${id}`).val(p.sell_price); $(`#buy-${id}`).val(p.buy_price); let displayHtml = p.image_path ? `<img src="${p.image_path}" class="w-100 h-100" style="object-fit:cover">` : `<i class="fas fa-box text-secondary"></i>`; $(`#thumb-box-${id}`).html(displayHtml); calcRow(id); }
function calcRow(id) { let qty = parseFloat($(`#qty-${id}`).val()) || 0; let price = parseFloat($(`#price-${id}`).val()) || 0; let buy = parseFloat($(`#buy-${id}`).val()) || 0; $(`#total-${id}`).val((qty * price).toFixed(2)); $(`#profit-${id}`).val(((price - buy) * qty).toFixed(2)); calcTotals(); }
function removeRow(id) { $(`#row-${id}`).remove(); calcTotals(); }
function calcTotals() { let sub = 0, totProfit = 0; $('input[id^="total-"]').each(function() { sub += parseFloat($(this).val()) || 0; }); $('input[id^="profit-"]').each(function() { totProfit += parseFloat($(this).val()) || 0; }); let tax = parseFloat($('#tax_amount').val()) || 0; $('#dispSub').text(sub.toFixed(2)); $('#sub_total').val(sub.toFixed(2)); $('#dispProfit').text(totProfit.toFixed(2)); $('#dispGrand').text((sub + tax).toFixed(2)); $('#grand_total').val((sub + tax).toFixed(2)); }
function submitForm() { if($('#client_id').val() == '' || $('input[id^="total-"]').length == 0) { Swal.fire('Error', 'Select client & add items.', 'warning'); return; } $.post('quotation_process.php', $('#quotationForm').serialize(), function(res) { if(res.status == 'success') { modal.hide(); Swal.fire({ title: 'Saved!', text: res.message, icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload()); } else { Swal.fire('Error', res.message, 'error'); } }, 'json'); }
function loadEdit(id) { actionDrawer.hide(); $.post('quotation_process.php', { action: 'fetch_single', id: id }, function(res) { if(res.status === 'success') { openCreateModal(); $('#formAction').val('update'); $('#quotationId').val(res.data.quotation_id); $('#client_id').val(res.data.client_id).trigger('change'); $('#quotation_date').val(res.data.quotation_date); $('#valid_until').val(res.data.valid_until); $('#quotation_terms').val(res.data.quotation_terms); $('#tax_amount').val(res.data.tax_amount); $('#qTableBody').empty(); res.items.forEach(item => { let p = productsDB.find(x => x.product_id == item.product_id); if(p) item.image_path = p.image_path; addNewRow(item); }); calcTotals(); } }, 'json'); }
function deleteQuote(id) { actionDrawer.hide(); Swal.fire({ title: 'Delete?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then((r) => { if(r.isConfirmed) $.post('quotation_process.php', { action: 'delete', id: id }, function(res) { if(res.status === 'success') location.reload(); }, 'json'); }); }
function updateStatus(id, status) { actionDrawer.hide(); Swal.fire({ title: `Mark as ${status}?`, icon: 'question', showCancelButton: true, confirmButtonColor: status === 'Accepted' ? '#198754' : '#dc3545', confirmButtonText: 'Yes' }).then((r) => { if(r.isConfirmed) { $.post('quotation_process.php', { action: 'update_status', id: id, status: status }, function(res) { if(res.status === 'success') { Swal.fire('Updated!', res.message, 'success').then(() => location.reload()); } else { Swal.fire('Error', res.message, 'error'); } }, 'json'); } }); }

// --- IMPROVED DUPLICATE ERROR HANDLING ---
function duplicateQuote(id) {
    actionDrawer.hide();
    Swal.fire({ title: 'Duplicate this Quotation?', text: "Create a copy with a new ID.", icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Duplicate it!' }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing...', text: 'Creating new quotation...', didOpen: () => { Swal.showLoading() }, allowOutsideClick: false });
            
            $.ajax({
                url: 'quotations.php', type: 'POST', data: { action: 'duplicate', id: id }, dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        // Ask to edit
                        Swal.fire({ title: 'Success!', text: "Quotation duplicated. Do you want to edit the new one?", icon: 'success', showCancelButton: true, confirmButtonText: 'Yes, Edit Now', cancelButtonText: 'No, Stay Here' }).then((editResult) => {
                            if (editResult.isConfirmed) { loadEdit(res.raw_id); } else { location.reload(); }
                        });
                    } else { Swal.fire('Error', res.message, 'error'); }
                },
                error: function(xhr, status, error) {
                    // Show raw response for debugging
                    console.log("Raw Response:", xhr.responseText);
                    let errorMsg = "Status: " + status + "\nError: " + error;
                    if(xhr.responseText) errorMsg += "\nServer says: " + xhr.responseText.substring(0, 100);
                    Swal.fire({ title: 'System Error', text: "See console for details. Raw: " + xhr.statusText, footer: '<pre style="text-align:left; font-size:0.8em; color:red">' + xhr.responseText.replace(/<[^>]*>?/gm, '').substring(0, 200) + '...</pre>', icon: 'error' });
                }
            });
        }
    });
}
</script>