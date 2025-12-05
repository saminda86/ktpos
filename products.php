<?php
// File Name: ktpos/products.php (FINAL FIX: Search Suggestion Functionality)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$page_title = 'Products & Inventory';
require_once 'header.php';
require_once 'db_connect.php'; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("DB Connection Failed: " . $conn->connect_error); }

// Pagination & Search
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$search_condition = '';
if (!empty($search_query)) {
    $search_condition = " WHERE p.product_name LIKE '%{$search_query}%' 
                          OR p.product_code LIKE '%{$search_query}%'
                          OR c.category_name LIKE '%{$search_query}%'
                          OR s.supplier_name LIKE '%{$search_query}%'";
}

// Total Count
$total_result = $conn->query("SELECT COUNT(p.product_id) AS count 
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.category_id
                              LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id" . $search_condition);
$total_records = $total_result->fetch_assoc()['count'] ?? 0; 
$total_pages = ceil($total_records / $limit);

// Fetch Table Data
$product_data = [];
$sql = "SELECT p.*, c.category_name, s.supplier_name, s.contact_no 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id"
        . $search_condition . " ORDER BY p.product_id DESC LIMIT {$start}, {$limit}";
$result = $conn->query($sql);
if ($result) { while($row = $result->fetch_assoc()) { $product_data[] = $row; } }

// Suggestions Data (Ensure all needed columns are fetched for client-side search)
$suggestions = [];
$sugg_sql = "SELECT p.product_id, p.product_name, p.product_code, p.image_path, p.sell_price, p.stock_quantity, p.buy_price, p.supplier_id, p.description, c.category_name, s.supplier_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.category_id
             LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id";
$sugg_result = $conn->query($sugg_sql);
if ($sugg_result) { while($row = $sugg_result->fetch_assoc()) { $suggestions[] = $row; } }

// Dropdowns
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
while($row = $cat_result->fetch_assoc()) $categories[] = $row;

$suppliers = [];
$sup_result = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
while($row = $sup_result->fetch_assoc()) $suppliers[] = $row;

$conn->close();
$product_data_json = json_encode($product_data); 
$suggestions_json = json_encode($suggestions);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* Suggestions Box */
    #productSuggestions {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1050;
        background: #fff; border: 1px solid #ced4da; border-radius: 0 0 0.25rem 0.25rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15); max-height: 350px; overflow-y: auto; display: none;
    }
    .suggestion-item { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; cursor: pointer; display: flex; align-items: center; transition: background 0.2s; }
    .suggestion-item:hover { background-color: #f8f9fa; }
    .suggestion-img-box { width: 40px; height: 40px; border-radius: 6px; overflow: hidden; margin-right: 15px; border: 1px solid #dee2e6; display: flex; align-items: center; justify-content: center; background: #fff; }
    .suggestion-img-box img { width: 100%; height: 100%; object-fit: cover; }
    
    /* Colors & Styles */
    .text-price { color: #198754 !important; font-weight: 700 !important; font-size: 0.9rem; }
    .text-profit { color: #d4ac0d !important; font-weight: 700 !important; font-size: 0.9rem; }
    
    /* Table Base Styles */
    .table-sm td, .table-sm th { padding: 0.5rem 0.4rem; vertical-align: middle; font-size: 0.85rem; }
    .col-img { width: 60px; text-align: center; }
    .col-img img { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .col-code { width: 85px; }
    .badge-code { font-size: 0.75rem; font-weight: 600; background-color: #f1f3f5; color: #495057; border: 1px solid #dee2e6; letter-spacing: 0.5px; }
    
    /* Supplier Badge */
    .badge-supplier {
        background-color: #f8f9fa; color: #495057; border: 1px solid #dee2e6;
        padding: 2px 8px; border-radius: 12px; font-weight: 600; font-size: 0.7rem;
        display: inline-block; white-space: nowrap; max-width: 100px; overflow: hidden; text-overflow: ellipsis;
    }
    
    /* Description Truncation */
    .desc-text { display: block; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6c757d; font-size: 0.8rem; }
    
    /* Profit Box */
    .profit-box { background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }

    /* Select2 Custom Styles */
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da !important; border-radius: 0.375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; padding-left: 12px !important; color: #212529 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; right: 5px !important; }
    .input-group .select2-container { flex-grow: 1; width: auto !important; min-width: 0; }

    /* Modal */
    .modal-backdrop:nth-of-type(2) { z-index: 1055 !important; }
    #categoryQuickAddModal, #supplierQuickAddModal { z-index: 1060 !important; }
    
    /* MAIN MODAL HEADER STYLING */
    #modalHeader {
        border-bottom: 1px solid #dee2e6 !important; 
        background-color: #f8f9fa !important; 
        border-radius: 0.5rem 0.5rem 0 0 !important; 
        padding: 1rem 1.5rem; 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    #modalHeader .modal-title {
        color: #343a40 !important; 
        font-weight: 700;
        font-size: 1.25rem;
    }
    #modalHeader .btn-close {
        color: #212529 !important; 
        filter: invert(0) !important;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    }
    
    /* ADD/SERVICE Button CSS */
    .card-header .btn {
        font-weight: 600; 
        border: none !important; 
        transition: transform 0.1s ease, box-shadow 0.1s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); 
    }
    .card-header .btn:hover {
        transform: translateY(-1px); 
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    /* QUICK ADD BUTTON HIGHLIGHT */
    .input-group .btn-outline-secondary {
        border-color: var(--bs-primary) !important; 
        color: var(--bs-primary) !important; 
        font-weight: 700;
        z-index: 5; 
        transition: all 0.3s ease-in-out;
        box-shadow: 0 0 5px rgba(13, 110, 253, 0.3); 
    }
    .input-group .btn-outline-secondary:hover,
    .input-group .btn-outline-secondary:focus {
        background-color: var(--bs-primary) !important; 
        color: white !important;
        box-shadow: 0 0 10px rgba(13, 110, 253, 0.8); 
    }

    /* FLOATING TOOLTIP INJECTION */
    .input-group.quick-add-group {
        position: relative; 
    }
    .input-group.quick-add-group:hover::after {
        content: attr(data-tooltip); 
        position: absolute;
        bottom: -30px; 
        left: 0;
        z-index: 1000;
        padding: 4px 8px;
        background: #fff;
        border: 1px solid var(--bs-primary); 
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        white-space: nowrap;
        font-size: 0.75rem;
        font-weight: 600;
        color: #343a40;
    }
    
    /* Responsive (Laptop) */
    @media (max-width: 1400px) {
        .table-sm td, .table-sm th { font-size: 0.75rem !important; padding: 0.3rem 0.2rem !important; }
        .col-img { width: 50px; } .col-img img { width: 35px; height: 35px; }
        .col-code { width: 70px; } .desc-text { max-width: 120px; font-size: 0.7rem; } 
        .badge-supplier { font-size: 0.65rem; padding: 1px 6px; max-width: 80px; }
    }
</style>

<h1 class="mb-3 text-primary h4"><i class="fas fa-boxes"></i> Product & Inventory</h1>
<hr class="mt-0 mb-3">

<div class="card shadow mb-4">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Item List (<?php echo $total_records; ?>)</h6>
        
        <div class="btn-group-sm"> 
            <button class="btn btn-primary me-2" onclick="openModal('product')"><i class="fas fa-box"></i> Add Product</button>
            <button class="btn btn-primary" onclick="openModal('service')"><i class="fas fa-tools"></i> Add Service</button>
        </div>
    </div>
    
    <div class="card-body p-2">
        <div class="row mb-2">
            <div class="col-md-6 position-relative">
                <form action="products.php" method="GET" class="input-group input-group-sm">
                    <input type="text" class="form-control" id="liveSearchInput" name="search" 
                           placeholder="Search Name, Code, Category or Supplier..." 
                           value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if(!empty($search_query)): ?><a href="products.php" class="btn btn-secondary">Clear</a><?php endif; ?>
                </form>
                <div id="productSuggestions"></div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="3%" class="text-center">#</th>
                        <th class="col-img">Img</th>
                        <th class="col-code">Code</th>
                        <th width="15%">Name</th>
                        <th width="10%">Supplier</th> 
                        <th width="12%">Description</th>
                        <th width="10%" class="col-cat">Category</th>
                        <th width="8%" class="text-end">Cost</th>
                        <th width="8%" class="text-end">Price</th>
                        <th width="8%" class="text-end">Profit</th>
                        <th width="5%" class="text-center">Stk</th>
                        <th width="8%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($product_data)): ?>
                        <?php foreach ($product_data as $index => $prod): 
                            $img = !empty($prod['image_path']) ? $prod['image_path'] : 'uploads/products/default.png';
                            $desc = $prod['description'] ?? '';
                            $profit = $prod['sell_price'] - $prod['buy_price'];
                            $is_service = ($prod['product_code'] && strpos($prod['product_code'], 'KWS') === 0) || ($prod['supplier_id'] === null && $prod['stock_quantity'] == 0);
                            $stock_display = $is_service ? '<span class="badge bg-secondary" style="font-size:0.65rem;">Svc</span>' : "<span class='badge bg-".($prod['stock_quantity']<5?'danger':'success')."' style='font-size:0.7rem;'>{$prod['stock_quantity']}</span>";
                        ?>
                        <tr>
                            <td class="text-muted text-center"><?php echo $start + $index + 1; ?></td>
                            <td class="col-img"><img src="<?php echo htmlspecialchars($img); ?>"></td>
                            <td><span class="badge-code"><?php echo htmlspecialchars($prod['product_code']); ?></span></td>
                            <td class="fw-bold text-dark" style="line-height:1.2;"><?php echo htmlspecialchars($prod['product_name']); ?></td>
                            
                            <td>
                                <?php if(!empty($prod['supplier_name'])): ?>
                                    <span class="badge-supplier" title="<?php echo htmlspecialchars($prod['supplier_name']); ?>">
                                        <?php echo htmlspecialchars($prod['supplier_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>

                            <td><span class="desc-text" title="<?php echo htmlspecialchars($desc); ?>"><?php echo htmlspecialchars($desc); ?></span></td>
                            <td class="col-cat"><small><?php echo htmlspecialchars($prod['category_name'] ?? '-'); ?></small></td>
                            
                            <td class="text-end text-muted"><small><?php echo number_format($prod['buy_price'], 2); ?></small></td>
                            <td class="text-end text-price"><?php echo number_format($prod['sell_price'], 2); ?></td>
                            <td class="text-end text-profit"><?php echo number_format($profit, 2); ?></td>
                            <td class="text-center"><?php echo $stock_display; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info border-0 px-1" onclick="loadForEdit(<?php echo $prod['product_id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                <?php if($_SESSION['user_type'] === 'Admin'): ?>
                                <button class="btn btn-sm btn-outline-danger border-0 px-1" onclick="confirmDelete(<?php echo $prod['product_id']; ?>, '<?php echo $prod['product_name']; ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="12" class="text-center py-4 text-muted">No items found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav class="mt-2">
            <ul class="pagination pagination-sm justify-content-end mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_query); ?>">Prev</a></li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_query); ?>">Next</a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="mainModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" id="product_id_modal">
                    <input type="hidden" name="action" id="form_action" value="insert">
                    <input type="hidden" name="item_type" id="item_type" value="product">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_name" id="product_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Code (Auto)</label>
                            <input type="text" class="form-control" name="product_code" id="product_code" placeholder="Auto-generated">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                            <div class="input-group quick-add-group" data-tooltip="නව කැටගරියක් එක් කිරීමට [+] බොත්තම භාවිතා කරන්න.">
                                <select class="form-select select2-box" name="category_id" id="category_id" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach($categories as $cat) echo "<option value='{$cat['category_id']}'>{$cat['category_name']}</option>"; ?>
                                </select>
                                <button class="btn btn-outline-secondary" type="button" onclick="openQuickAdd('category')"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>

                        <div class="col-md-6" id="div_supplier">
                            <label class="form-label small fw-bold">Supplier</label>
                            <div class="input-group quick-add-group" data-tooltip="නව සැපයුම්කරුවෙක් එක් කිරීමට [+] බොත්තම භාවිතා කරන්න.">
                                <select class="form-select select2-box" name="supplier_id" id="supplier_id">
                                    <option value="">-- Select --</option>
                                    <?php foreach($suppliers as $sup) echo "<option value='{$sup['supplier_id']}'>{$sup['supplier_name']}</option>"; ?>
                                </select>
                                <button class="btn btn-outline-secondary" type="button" onclick="openQuickAdd('supplier')"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>

                        <div class="col-md-4" id="div_buy_price">
                            <label class="form-label small fw-bold">Cost (Rs.)</label>
                            <input type="number" class="form-control" name="buy_price" id="buy_price" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Price (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="sell_price" id="sell_price" step="0.01" required>
                        </div>
                        <div class="col-md-4" id="div_stock">
                            <label class="form-label small fw-bold">Stock Qty</label>
                            <input type="number" class="form-control" name="stock_quantity" id="stock_quantity" value="0">
                        </div>

                        <div class="col-12" id="div_profit_display">
                            <div class="profit-box">
                                <div>
                                    <div style="font-size:0.75rem; color:#6c757d; font-weight:600;">ESTIMATED PROFIT</div>
                                    <div id="profit_val" style="font-size:1.1rem; font-weight:700; color:#198754;">Rs. 0.00</div>
                                </div>
                                <div class="text-end">
                                    <div style="font-size:0.75rem; color:#6c757d; font-weight:600;">MARGIN</div>
                                    <div id="profit_margin" style="font-size:0.9rem; font-weight:600; color:#d4ac0d;">0%</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Image</label>
                            <input type="file" class="form-control" name="product_image" id="product_image" accept="image/*">
                            <div id="image_preview" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <div class="modal-footer px-0 pb-0 mt-3 justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'quick_add_modals.php'; ?>
<?php require_once 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    const allTableData = <?php echo $product_data_json ?: '[]'; ?>;
    const allSuggestions = <?php echo $suggestions_json ?: '[]'; ?>;
    const mainModal = new bootstrap.Modal(document.getElementById('mainModal'));

    // Select2 Initialization
    $('.select2-box').select2({
        dropdownParent: $('#mainModal'),
        width: '100%', placeholder: '-- Select --', allowClear: true
    });

    // --- SINHALA TOAST MESSAGE CONFIGURATION ---
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end', // Display at Top Right
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // --- PROFIT CALC ---
    function calculateProfit() {
        let buy = parseFloat($('#buy_price').val()) || 0;
        let sell = parseFloat($('#sell_price').val()) || 0;
        let profit = sell - buy;
        let margin = (buy > 0) ? (profit / buy) * 100 : (sell > 0 ? 100 : 0);
        $('#profit_val').text('Rs. ' + profit.toFixed(2)).css('color', profit < 0 ? '#dc3545' : '#198754');
        $('#profit_margin').text(margin.toFixed(1) + '%');
    }
    $('#buy_price, #sell_price').on('input', calculateProfit);

    // --- MAIN MODAL ---
    window.openModal = function(mode) {
        $('#productForm')[0].reset();
        $('#category_id').val('').trigger('change');
        $('#supplier_id').val('').trigger('change');
        $('#product_id_modal').val('');
        $('#form_action').val('insert');
        $('#image_preview').empty();
        $('#item_type').val(mode);
        calculateProfit();

        // Remove colored background for clean look
        $('#modalHeader').removeClass('bg-success bg-info');
        
        if(mode === 'product') {
            $('#modalTitle').html('<i class="fas fa-box"></i> New Product Details');
        } else {
            $('#modalTitle').html('<i class="fas fa-tools"></i> New Service Details');
        }
        $('#div_buy_price, #div_profit_display').show(); 
        mainModal.show();
    };

    window.loadForEdit = function(id) {
        const item = allTableData.find(p => p.product_id == id);
        if(!item) return;

        const isService = (item.product_code && item.product_code.startsWith('KWS')) || (!item.supplier_id && parseInt(item.stock_quantity) == 0);
        const mode = isService ? 'service' : 'product';
        
        openModal(mode); 
        $('#form_action').val('update');
        
        // Remove colored background for clean look
        $('#modalHeader').removeClass('bg-success bg-info');
        $('#modalTitle').html(`<i class="fas fa-edit"></i> Edit ${mode === 'product' ? 'Product Details' : 'Service Details'}`);
        $('#saveBtn').text('Update Item');
        
        $('#product_id_modal').val(item.product_id);
        $('#product_name').val(item.product_name);
        $('#product_code').val(item.product_code);
        $('#category_id').val(item.category_id).trigger('change');
        $('#supplier_id').val(item.supplier_id).trigger('change');
        $('#sell_price').val(item.sell_price);
        $('#description').val(item.description);
        $('#buy_price').val(item.buy_price);
        calculateProfit();
        if(mode === 'product') $('#stock_quantity').val(item.stock_quantity);
        if(item.image_path) $('#image_preview').html(`<img src="${item.image_path}" width="60" class="rounded border mt-2">`);
    };

    // MAIN PRODUCT FORM SUBMIT
    $('#productForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: 'product_process.php', type: 'POST', data: formData,
            contentType: false, processData: false, dataType: 'json',
            success: res => {
                if(res.status === 'success') {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'සාර්ථකයි!', 
                        text: 'සාර්ථකව සුරැකිනි.',
                        timer: 1500, 
                        showConfirmButton: false 
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'දෝෂයක්!', text: res.message }); 
                }
            }
        });
    });

    window.confirmDelete = function(id, name) {
        Swal.fire({
            title: 'ඔබට විශ්වාසද?', 
            text: `මෙය නැවත හැරවිය නොහැක! ("${name}")`, 
            icon: 'warning',
            showCancelButton: true, 
            confirmButtonColor: '#d33', 
            cancelButtonText: 'නැත', 
            confirmButtonText: 'ඔව්, මකන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('product_process.php', { action: 'delete', product_id: id }, r => {
                    if(r.status === 'success') {
                        Swal.fire('මැකී ගියා!', 'සාර්ථකව ඉවත් කරන ලදී.', 'success').then(() => location.reload());
                    } else Swal.fire('දෝෂයක්', r.message, 'error');
                }, 'json');
            }
        });
    };

    $('#liveSearchInput').on('keyup', function() {
        const query = $(this).val().toLowerCase();
        const box = $('#productSuggestions');
        if(query.length < 2) { box.hide(); return; }
        const filtered = allSuggestions.filter(i => (i.product_name && i.product_name.toLowerCase().includes(query)) || (i.product_code && i.product_code.toLowerCase().includes(query)) || (i.category_name && i.category_name.toLowerCase().includes(query)) || (i.supplier_name && i.supplier_name.toLowerCase().includes(query))).slice(0, 6);
        let html = '';
        if(filtered.length) {
            filtered.forEach(i => {
                let img = i.image_path || 'uploads/products/default.png';
                let price = parseFloat(i.sell_price).toFixed(2);
                let stockHtml = (i.buy_price == 0 && i.stock_quantity == 0 && !i.supplier_id) ? `<span class="badge bg-info">Svc</span>` : `<span class="badge bg-${i.stock_quantity<5?'danger':'success'}">${i.stock_quantity}</span>`;
                
                // SUPPLIER DATA IN SUGGESTION CENTERED (Simplified)
                let supplierHtml = i.supplier_name ? `<span class="badge bg-light text-secondary border">${i.supplier_name}</span>` : '';

                html += `<div class="suggestion-item" onclick="window.location.href='products.php?search=${encodeURIComponent(i.product_name)}'">
                    <div class="suggestion-img-box"><img src="${img}"></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">${i.product_name} <span class="badge bg-light text-dark border">${i.product_code}</span></div>
                        
                        <div class="d-flex align-items-center mt-1">
                            <small class="text-primary fw-bold">${supplierHtml}</small>
                        </div>
                        
                        <small class="text-muted fst-italic d-block" style="font-size:0.8em;">${i.category_name || '-'} | ${desc}</small> 
                    </div>
                    <div class="text-end"><div class="fw-bold text-success">Rs. ${price}</div>${stockHtml}</div>
                </div>`;
            });
            box.html(html).show();
        } else { box.hide(); }
    });
    $(document).click(e => { if(!$(e.target).closest('#liveSearchInput, #productSuggestions').length) $('#productSuggestions').hide(); });

    // ============================================================
    //  QUICK ADD LOGIC (Validation & Messages)
    // ============================================================
    
    window.categoriesList = []; window.suppliersList = [];

    window.openQuickAdd = function(type) {
        prepareQuickAddModal(type); fetchAndRefreshList(type);
        new bootstrap.Modal(document.getElementById(type === 'category' ? 'categoryQuickAddModal' : 'supplierQuickAddModal')).show();
    };
    window.closeQuickAddModal = function(type) {
        bootstrap.Modal.getInstance(document.getElementById(type === 'category' ? 'categoryQuickAddModal' : 'supplierQuickAddModal')).hide();
    };
    window.prepareQuickAddModal = function(type) {
        $('#' + type + 'QuickAddForm')[0].reset();
        $('#' + type + '_id_quick').val('');
        $('#' + type + '_search_input').val('');
        
        // Reset Select2 search input clear
        const selectId = (type === 'category') ? '#category_id' : '#supplier_id';
        $(selectId).val('').trigger('change');
    };

    window.fetchAndRefreshList = function(type, autoSelectName = null) {
        $.post(type === 'category' ? 'category_process.php' : 'supplier_process.php', { action: 'fetch' }, function(res) {
            if(res.status === 'success') {
                if(type === 'category') categoriesList = res.data; else suppliersList = res.data;
                renderList(type, res.data);
                refreshMainDropdown(type, res.data, autoSelectName);
            }
        }, 'json');
    };

    window.renderList = function(type, data) {
        const tbody = (type === 'category') ? $('#category_table_body') : $('#supplier_table_body');
        tbody.empty();
        if(data.length === 0) { tbody.html('<tr><td colspan="2" class="text-center text-muted small">No records found</td></tr>'); return; }
        
        data.forEach(item => {
            let id = item.category_id || item.supplier_id;
            let name = item.category_name || item.supplier_name;
            let display = name;
            if(type === 'supplier' && item.contact_no) display += ` <small class="text-muted">(${item.contact_no})</small>`;

            let html = `<tr><td class="align-middle">${display}</td><td class="text-center">
                <button class="btn btn-sm btn-outline-primary py-0" onclick="editQuickItem('${type}', ${id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteQuickItem('${type}', ${id})"><i class="fas fa-trash-alt"></i></button>
            </td></tr>`;
            tbody.append(html);
        });
    };

    // New: Numeric Input Validation for Supplier Contact
    $('#supplier_contact_quick').on('input', function() {
        let input = $(this).val();
        if (/[^0-9]/.test(input)) {
            let cleanedInput = input.replace(/[^0-9]/g, '');
            $(this).val(cleanedInput);
            Toast.fire({
                icon: 'warning',
                title: 'අංක පමණක් ඇතුලත් කරන්න!', 
                position: 'top', 
                timer: 2500,
                showConfirmButton: false
            });
        }
    });

    $('#category_search_input').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        renderList('category', categoriesList.filter(item => item.category_name.toLowerCase().includes(val)));
    });
    $('#supplier_search_input').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        renderList('supplier', suppliersList.filter(item => item.supplier_name.toLowerCase().includes(val)));
    });

    window.editQuickItem = function(type, id) {
        const list = (type === 'category') ? categoriesList : suppliersList;
        const item = list.find(i => (type === 'category' ? i.category_id == id : i.supplier_id == id));

        if(item) {
            $('#' + type + '_id_quick').val(id);
            $('#' + type + (type === 'category' ? '_name_quick' : '_name_quick')).val((type === 'category') ? item.category_name : item.supplier_name);
            if(type === 'supplier') $('#supplier_contact_quick').val(item.contact_no);
            $('#' + type + (type === 'category' ? '_name_quick' : '_name_quick')).focus();
        }
    };

    window.deleteQuickItem = function(type, id) {
        Swal.fire({
            title: 'ඔබට විශ්වාසද?', 
            text: "මෙය නැවත හැරවිය නොහැක!",
            icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', 
            cancelButtonText: 'නැත',
            confirmButtonText: 'ඔව්, මකන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(type === 'category' ? 'category_process.php' : 'supplier_process.php', 
                       { action: 'delete', [type + '_id']: id }, 
                       function(res) { 
                           if(res.status === 'success') {
                               Toast.fire({ icon: 'success', title: 'සාර්ථකව ඉවත් කරන ලදී!' });
                               fetchAndRefreshList(type); 
                           } else Swal.fire('දෝෂයක්', res.message, 'error'); 
                       }, 'json');
            }
        });
    };

    $(document).on('submit', '#categoryQuickAddForm, #supplierQuickAddForm', function(e) {
        e.preventDefault();
        const type = (this.id === 'categoryQuickAddForm') ? 'category' : 'supplier';
        const isEdit = $('#' + type + '_id_quick').val() !== '';
        const enteredName = $('#' + type + (type==='category'?'_name_quick':'_name_quick')).val();

        $.ajax({
            url: type === 'category' ? 'category_process.php' : 'supplier_process.php',
            type: 'POST', data: $(this).serialize() + '&action=' + (isEdit ? 'update' : 'insert'), dataType: 'json',
            success: res => {
                const message = res.message.toLowerCase();
                if(res.status === 'success') {
                    fetchAndRefreshList(type, enteredName);
                    
                    Toast.fire({ icon: 'success', title: 'සාර්ථකව සුරැකිනි!' });

                    // Reset Form
                    $('#' + type + '_id_quick').val('');
                    $('#' + type + (type==='category'?'_name_quick':'_name_quick')).val('');
                    if(type === 'supplier') $('#supplier_contact_quick').val('');
                } else if (message.includes('duplicate') || message.includes('exists') || message.includes('already')) {
                     // Duplicate/Exists Error: Use less intrusive Toast
                    Toast.fire({
                        icon: 'warning',
                        title: 'දැනටමත් පවතී!', 
                        text: 'මෙම නම හෝ අංකය දැනටමත් ඇතුලත් කර ඇත.', 
                        timer: 3500
                    });
                } else { 
                    // Generic Error: Use central Swal
                    Swal.fire({ icon: 'error', title: 'දෝෂයක්...', text: res.message });
                }
            }
        });
    });

    function refreshMainDropdown(type, data, autoSelectName) {
        const selectId = (type === 'category') ? '#category_id' : '#supplier_id';
        const currentVal = $(selectId).val();
        let html = `<option value="">-- Select --</option>`;
        let idToSelect = null;
        data.forEach(item => {
            let id = item.category_id || item.supplier_id;
            let name = item.category_name || item.supplier_name;
            html += `<option value="${id}">${name}</option>`;
            if(autoSelectName && name.toLowerCase() === autoSelectName.toLowerCase()) idToSelect = id;
        });
        $(selectId).html(html);
        if(idToSelect) $(selectId).val(idToSelect).trigger('change');
        else if(currentVal) $(selectId).val(currentVal).trigger('change');
        else $(selectId).trigger('change');
    }
});
</script>