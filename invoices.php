<?php
// File Name: invoices.php
// Description: Invoice Manager with Live Search & Admin Profit

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
    ob_clean(); // අනවශ්‍ය Output ඉවත් කිරීම
    header('Content-Type: application/json; charset=utf-8');
    try {
        $term = $conn->real_escape_string($_GET['term']);
        $results = [];

        // 1. Clients ලාගෙන් සොයන්න
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

        // 2. Invoices වල අංකයෙන් සොයන්න
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
    exit; // මෙතනින් නවතින්න ඕනේ නැත්නම් මුළු පිටුවම ලෝඩ් වෙනවා
}

// =========================================================
// 2. DATA STATISTICS
// =========================================================
$stats = ['total' => 0, 'total_val' => 0, 'paid' => 0, 'unpaid' => 0];
$res_stats = $conn->query("SELECT COUNT(*) as count, SUM(grand_total) as value, payment_status FROM invoices GROUP BY payment_status");
if($res_stats) { 
    while ($row = $res_stats->fetch_assoc()) { 
        $stats['total'] += $row['count']; 
        $stats['total_val'] += $row['value']; 
        if (stripos($row['payment_status'], 'Paid') !== false) { $stats['paid'] += $row['count']; } 
        else { $stats['unpaid'] += $row['count']; }
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

$page_title = 'Invoice Manager';
require_once 'header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #344767; }
    .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); display: flex; align-items: center; transition: 0.2s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
    .icon-shape { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.4rem; color: #fff; }
    
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

    /* SEARCH STYLES (From Quotations) */
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
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="mb-0 fw-bold">Invoice Manager</h4><p class="text-muted small mb-0">Manage customer invoices and payments.</p></div>
        <a href="create_invoice.php" class="btn bg-gradient-primary text-white shadow-sm px-4"><i class="fas fa-plus me-2"></i> Create Invoice</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-primary shadow"><i class="fas fa-file-invoice-dollar"></i></div><div class="ms-3"><h6>Total Invoices</h6><h4><?php echo number_format($stats['total']); ?></h4></div></div></div>
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-info shadow"><i class="fas fa-coins"></i></div><div class="ms-3"><h6>Total Value</h6><h4>Rs. <?php echo number_format($stats['total_val'], 2); ?></h4></div></div></div>
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-success shadow"><i class="fas fa-check-double"></i></div><div class="ms-3"><h6>Paid Invoices</h6><h4><?php echo number_format($stats['paid']); ?></h4></div></div></div>
        <div class="col-xl-3 col-sm-6"><div class="stat-card"><div class="icon-shape bg-gradient-danger shadow"><i class="fas fa-exclamation-circle"></i></div><div class="ms-3"><h6>Pending/Unpaid</h6><h4><?php echo number_format($stats['unpaid']); ?></h4></div></div></div>
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
            <a href="#" id="act_print" target="_blank" class="list-group-item list-group-item-action py-3"><i class="fas fa-print me-3 text-info"></i> Print Invoice</a>
            <a href="#" id="act_edit" class="list-group-item list-group-item-action py-3"><i class="fas fa-pen me-3 text-warning"></i> Edit Invoice</a>
            <div class="mt-3 border-top"><a href="#" id="act_delete" class="list-group-item list-group-item-action text-danger py-3"><i class="fas fa-trash-alt me-3"></i> Delete Permanently</a></div>
        </div>
    </div>
</div>

<?php $conn->close(); require_once 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Live Search Logic (Same as Quotations)
    $(document).ready(function() {
        let searchInput = $('#mainSearch');
        let suggestionsBox = $('#searchSuggestions');
        let clearBtn = $('#clearSearchBtn');

        if (searchInput.val().length > 0) clearBtn.show();

        searchInput.on('keyup input', function() {
            let term = $(this).val().trim();
            if(term.length > 0) {
                clearBtn.show();
                $.ajax({
                    url: 'invoices.php',
                    data: { action: 'live_search', term: term },
                    dataType: 'json',
                    success: function(data) {
                        suggestionsBox.empty();
                        if(data.length > 0) {
                            data.forEach(item => {
                                suggestionsBox.append(`
                                    <div class="s-item" onclick="selectSearch('${item.text}')">
                                        <div class="s-icon"><i class="fas ${item.icon}"></i></div>
                                        <div class="w-100"><div class="d-flex justify-content-between">
                                            <span class="s-text">${item.text}</span>
                                            <span class="s-sub">${item.sub}</span>
                                        </div></div>
                                    </div>
                                `);
                            });
                            suggestionsBox.show();
                        } else {
                            suggestionsBox.hide();
                        }
                    }
                });
            } else {
                clearBtn.hide();
                suggestionsBox.hide();
            }
        });

        clearBtn.on('click', function() {
            searchInput.val('');
            $(this).hide();
            suggestionsBox.hide();
            window.location.href = 'invoices.php';
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-group').length) {
                suggestionsBox.hide();
            }
        });
    });

    function selectSearch(text) {
        $('#mainSearch').val(text);
        $('#searchSuggestions').hide();
        $('#searchForm').submit();
    }

    // Action Drawer Logic
    const actionDrawer = new bootstrap.Offcanvas(document.getElementById('actionDrawer'));

    function openActionDrawer(id, invoiceNo) {
        $('#drawerInvoiceNo').text(invoiceNo);
        $('#act_print').attr('href', 'invoice_view.php?id=' + id);
        $('#act_edit').attr('href', 'create_invoice.php?edit_id=' + id);
        $('#act_delete').attr('onclick', `deleteInvoice(${id})`);
        actionDrawer.show();
    }

    function deleteInvoice(id) {
        actionDrawer.hide();
        Swal.fire({
            title: 'Delete Invoice?',
            text: "Are you sure you want to delete this invoice permanently?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, Delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                 window.location.href = 'invoice_actions.php?action=delete&id=' + id;
            }
        });
    }
</script>