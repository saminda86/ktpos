<?php
// File Name: ktpos/products.php (ULTRA FINAL UI/UX Version: Item Toggle and Aesthetic Finalization)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = 'Products & Inventory';
require_once 'header.php';
require_once 'db_connect.php'; 

// -----------------------------------------------------------
// CONFIGURATION AND SETUP
// -----------------------------------------------------------
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Pagination Variables
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';


// -----------------------------------------------------------
// Product Fetch Logic (with Search and Pagination)
// -----------------------------------------------------------
$product_data = [];
$total_records = 0;
$search_condition = '';

if (!empty($search_query)) {
    // Search by Name, Code, or Category
    $search_condition = " WHERE p.product_name LIKE '%{$search_query}%' 
                          OR p.product_code LIKE '%{$search_query}%'
                          OR c.category_name LIKE '%{$search_query}%'";
}

// 1. Total Count
$total_result = $conn->query("SELECT COUNT(p.product_id) AS count FROM products p LEFT JOIN categories c ON p.category_id = c.category_id" . $search_condition);
$total_records = $total_result->fetch_assoc()['count'] ?? 0; 
$total_pages = ceil($total_records / $limit);

// 2. Data Fetch (Fetching all columns required by the user)
$sql = "SELECT p.*, c.category_name, s.supplier_name, s.contact_no, s.address 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id"
        . $search_condition . " ORDER BY p.product_id DESC LIMIT {$start}, {$limit}";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $product_data[] = $row;
    }
}

// 3. Fetch all data for JS Suggestion Box (Only essential fields)
$all_products_data = [];
$all_product_result = $conn->query("SELECT p.product_id, p.product_code, p.product_name, p.description, c.category_name 
                                    FROM products p LEFT JOIN categories c ON p.category_id = c.category_id 
                                    ORDER BY p.product_name ASC");
if ($all_product_result) {
    while($row = $all_product_result->fetch_assoc()) {
        $all_products_data[] = $row;
    }
}


// 4. Fetch Categories and Suppliers for Modals (Used in quick_add_modals.php)
$categories = [];
$suppliers = [];

$cat_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
if ($cat_result) {
    while($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$sup_result = $conn->query("SELECT supplier_id, supplier_name, contact_no, address FROM suppliers ORDER BY supplier_name ASC");
if ($sup_result) {
    while($row = $sup_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}


$conn->close();

$product_data_json = json_encode($product_data); 
$suppliers_json = json_encode($suppliers);
$all_products_search_json = json_encode($all_products_data);
?>

<style>
    /* 🛑 Product Suggestion Styling (Professional) 🛑 */
    #productSuggestions {
        border: 1px solid #ced4da !important; 
        border-radius: 0.25rem !important; 
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15) !important; 
        background-color: #ffffff !important; 
        max-height: 250px; 
        overflow-y: auto; 
    }

    #productSuggestions .list-group-item {
        color: #212529 !important; 
        font-size: 0.95rem !important; 
        padding: 10px 15px !important;
        border-left: none !important;
        border-right: none !important;
        border-top: 1px solid #f0f0f0 !important; 
        border-color: #e9ecef !important;
        line-height: 1.3;
    }
    
    #productSuggestions .list-group-item:hover,
    #productSuggestions .list-group-item:focus {
        background-color: var(--light-grey-bg) !important;
        color: #000000 !important; 
        cursor: pointer;
    }
    #productSuggestions .list-group-item strong {
        font-weight: 700 !important; 
        color: var(--primary-color);
    }
    .suggestion-category {
        font-size: 0.8em;
        color: #6c757d;
        display: block;
    }

    /* 🛑 FINAL UI FIX: Custom styling for modal sections 🛑 */
    .modal-section-group {
        padding: 25px 20px; /* Increased padding */
        border-radius: 12px; /* Smoother corners */
        background-color: #ffffff; /* White card background */
        border: 1px solid #e0e0e0; /* Subtle border */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); /* Soft shadow for lift */
        height: 100%;
    }
    .modal-body {
        background-color: #f5f5f5; /* Light grey background for modal body */
        padding-bottom: 0 !important;
    }
    /* 🛑 Toggle Button Symmetry Fix (Applied to the Label) 🛑 */
    .btn-group .btn-lg {
        flex: 1 1 50%; /* Ensure equal width for both buttons */
        text-align: center;
        padding: 15px 10px;
        font-weight: 600;
        font-size: 1.1em;
        border-radius: 0 !important; /* Remove individual border radius */
    }
    .btn-group .btn-lg:first-child {
        border-top-left-radius: 0.5rem !important; /* Restore corner radius to the group edges */
        border-bottom-left-radius: 0.5rem !important;
    }
    .btn-group .btn-lg:last-child {
        border-top-right-radius: 0.5rem !important;
        border-bottom-right-radius: 0.5rem !important;
    }
</style>

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

    <h1 class="mb-4 text-primary"><i class="fas fa-boxes"></i> Product & Inventory Management</h1>
    <hr>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="m-0 font-weight-bold text-primary">All System Products (Total: <?php echo $total_records; ?>)</h6>
            
            <div class="btn-group mt-2 mt-md-0" role="group">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="prepareAddModal()">
                    <i class="fas fa-plus-circle"></i> Add New Item
                </button>
                
                <button class="btn btn-info text-white" onclick="openQuickAddModal('category')">
                    <i class="fas fa-tags"></i> Add New Category
                </button>
                
                <button class="btn btn-warning" onclick="openQuickAddModal('supplier')">
                    <i class="fas fa-truck"></i> Add New Supplier
                </button>
            </div>
        </div>
        
        <div class="card-body">
            
            <div class="row mb-3">
                <div class="col-md-7 position-relative">
                    <form action="products.php" method="GET" class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search by Name, Code, or Category" 
                               name="search" 
                               id="productSearchInput"
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               autocomplete="off">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                        
                        <?php if (!empty($search_query)): ?>
                            <a href="products.php" class="btn btn-secondary" title="Clear Search">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                    <div id="productSuggestions" class="list-group position-absolute mt-1" style="z-index: 1000; width: 66%;">
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="productsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th> 
                            <th>Product Code</th>
                            <th>Image</th> 
                            <th>Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Buy Price (රු.)</th>
                            <th>Sell Price (රු.)</th>
                            <th>Quantity</th>
                            <th>Supplier</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($product_data)): ?>
                            <?php foreach ($product_data as $index => $product): ?>
                            <tr>
                                <td><?php echo $start + $index + 1; ?></td> 
                                <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                                
                                <td>
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                            alt="Product Image" 
                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($product['description'] ?? '-', 0, 30)); ?>...</td>
                                <td><?php echo number_format($product['buy_price'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($product['sell_price'] ?? 0, 2); ?></td>
                                <td><span class="badge bg-<?php echo ($product['stock_quantity'] > 10) ? 'success' : (($product['stock_quantity'] > 0) ? 'warning' : 'danger'); ?>"><?php echo number_format($product['stock_quantity']); ?></span></td>
                                <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'N/A'); ?></td>
                                
                                <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="loadProductForEdit(<?php echo $product['product_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if ($_SESSION['user_type'] === 'Admin'): ?>
                                    <button class="btn btn-sm btn-danger" onclick="confirmAndDelete(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No products found <?php echo !empty($search_query) ? "matching '{$search_query}'" : ""; ?>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">Showing <?php echo min($limit, count($product_data)); ?> of <?php echo $total_records; ?> records.</div>
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="products.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                            </li>
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="products.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; 
                            
                            if ($end_page < $total_pages) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                            ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="products.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
            
        </div>
    </div>


<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"> 
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title" id="productModalLabel"><i class="fas fa-cube"></i> Add New Item Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        
        <form id="productInsertUpdateForm" enctype="multipart/form-data">
            <input type="hidden" name="product_id" id="product_id_modal" value="">
            
            <div class="row">
                
                <div class="col-12 mb-4">
                    <h6 class="text-primary mb-2"><i class="fas fa-th-list"></i> Item Type Selection</h6>
                    <div class="btn-group w-100" role="group" aria-label="Item Type">
                        <input type="radio" class="btn-check" name="item_type" id="type_product" value="product" autocomplete="off" checked>
                        <label class="btn btn-outline-success btn-lg" for="type_product">
                            <i class="fas fa-box"></i> Product (Inventory)
                        </label>

                        <input type="radio" class="btn-check" name="item_type" id="type_service" value="service" autocomplete="off">
                        <label class="btn btn-outline-info btn-lg" for="type_service">
                            <i class="fas fa-tools"></i> Service
                        </label>
                    </div>
                    <small class="text-muted d-block mt-1">Selecting 'Service' will disable stock tracking.</small>
                </div>
                
                <div class="col-md-6">
                    <div class="modal-section-group">
                        <h6 class="text-primary mb-3"><i class="fas fa-info-circle"></i> Basic Item Details</h6>

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="product_name_modal" class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="product_name_modal" name="product_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="product_code_display" class="form-label text-muted">Item Code</label>
                                <input type="text" class="form-control text-muted" id="product_code_display" readonly value="(Auto Generate)">
                                <input type="hidden" name="product_code" id="product_code_modal" value=""> 
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="description_modal" class="form-label">Description / Notes</label>
                                <textarea class="form-control" id="description_modal" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <h6 class="text-primary my-3" id="image_header"><i class="fas fa-image"></i> Item Image (Optional)</h6>
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-7">
                                <label for="product_image_modal" class="form-label">Upload Image</label>
                                <input type="file" class="form-control" id="product_image_modal" name="product_image" accept="image/*">
                            </div>
                             <div class="col-md-5 text-center d-flex justify-content-center align-items-center" id="image_preview_container">
                                </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="modal-section-group">
                        <h6 class="text-primary mb-3"><i class="fas fa-dollar-sign"></i> Pricing & Stock</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6" id="buy_price_group">
                                <label for="buy_price_modal" class="form-label">Buy Price (රු.)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="buy_price_modal" name="buy_price" value="0.00">
                            </div>
                            <div class="col-md-6">
                                <label for="sell_price_modal" class="form-label">Sell Price (රු.) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="sell_price_modal" name="sell_price" required value="0.00">
                            </div>
                        </div>
                        
                        <div class="row mb-3" id="profit_display_group">
                            <div class="col-md-12">
                                <label class="form-label text-muted">Calculated Profit (රු. / %)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-success font-weight-bold" id="profit_display_rs">රු. 0.00</span>
                                    <span class="input-group-text bg-light text-warning font-weight-bold" id="profit_display_percent">0.00%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label for="stock_quantity_modal" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" min="0" class="form-control" id="stock_quantity_modal" name="stock_quantity" required value="0">
                                <small id="stockHelp" class="form-text text-danger" style="display:none;">Stock tracking is disabled for Services.</small>
                            </div>
                        </div>
                        
                        <h6 class="text-primary my-3"><i class="fas fa-link"></i> Classification & Sourcing</h6>

                        <div class="row mb-3 align-items-end">
                            <div class="col-md-8">
                                <label for="category_id_modal" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id_modal" name="category_id" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category_id']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-info btn-sm w-100 text-white" onclick="openQuickAddModal('category')">
                                    <i class="fas fa-plus-square"></i> Add New
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-3 align-items-end" id="supplier_group">
                            <div class="col-md-8">
                                <label for="supplier_id_modal" class="form-label">Supplier</label>
                                <select class="form-select" id="supplier_id_modal" name="supplier_id">
                                    <option value="">-- Select Supplier (Optional) --</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?php echo htmlspecialchars($sup['supplier_id']); ?>"><?php echo htmlspecialchars($sup['supplier_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-warning btn-sm w-100" onclick="openQuickAddModal('supplier')">
                                    <i class="fas fa-plus-square"></i> Add New
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between mt-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveProductButton"><i class="fas fa-save"></i> Save Item</button>
            </div>
        </form>
      </div>
      
    </div>
  </div>
</div>

<?php require_once 'quick_add_modals.php'; ?>


<?php 
require_once 'delete_modal.php'; 
require_once 'footer.php';
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    
    // Fetch initial data arrays from PHP
    const allProductsData = <?php echo $all_products_search_json ?: '[]'; ?>; // For search suggestion
    const allTableData = <?php echo $product_data_json ?: '[]'; ?>; // For table edits
    let allSuppliersData = <?php echo $suppliers_json ?: '[]'; ?>; // For supplier quick edit loading (Mutable for updates)
    const is_admin = <?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') ? 'true' : 'false'; ?>;
    
    // Global lists to be updated by AJAX
    let categoriesList = <?php echo json_encode($categories); ?>;
    
    // Get Bootstrap Modals
    const productModal = new bootstrap.Modal(document.getElementById('productModal'), { backdrop: 'static', keyboard: false });
    const categoryQuickAddModal = new bootstrap.Modal(document.getElementById('categoryQuickAddModal'), { backdrop: false });
    const supplierQuickAddModal = new bootstrap.Modal(document.getElementById('supplierQuickAddModal'), { backdrop: false });
    
    // -----------------------------------------------------------
    // 1. Search Suggestion Logic
    // -----------------------------------------------------------
    const searchInput = document.getElementById('productSearchInput');
    const suggestionsDiv = document.getElementById('productSuggestions');
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        if (query.length < 2) {
            suggestionsDiv.style.display = 'none';
            suggestionsDiv.innerHTML = '';
            return;
        }

        const filteredSuggestions = allProductsData
            .filter(product => 
                (product.product_name && product.product_name.toLowerCase().includes(query)) || 
                (product.product_code && product.product_code.toLowerCase().includes(query)) ||
                (product.category_name && product.category_name.toLowerCase().includes(query))
            )
            .slice(0, 5); // Max 5 suggestions

        if (filteredSuggestions.length > 0) {
            let html = '';
            filteredSuggestions.forEach(product => {
                html += `<a href="products.php?search=${encodeURIComponent(product.product_name)}" class="list-group-item list-group-item-action">
                            <strong>${product.product_name}</strong>
                            <span class="suggestion-category">Code: ${product.product_code || 'N/A'} | Category: ${product.category_name || 'Uncategorized'}</span>
                        </a>`;
            });
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = 'block';
        } else {
            suggestionsDiv.style.display = 'none';
        }
    });

    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !suggestionsDiv.contains(event.target)) {
            suggestionsDiv.style.display = 'none';
        }
    });
    
    // -----------------------------------------------------------
    // 2. Product/Service Toggle Logic
    // -----------------------------------------------------------
    function toggleProductServiceFields(type) {
        const isProduct = type === 'product';
        
        // Stock Quantity Field
        $('#stock_quantity_modal').prop('readonly', !isProduct);
        $('#stockHelp').toggle(!isProduct);
        
        // Buy Price & Profit Calculation
        $('#buy_price_group, #profit_display_group').toggle(isProduct);
        
        // Supplier Group
        $('#supplier_group').toggle(isProduct);
        
        // Set required attribute for Stock based on Type
        $('#stock_quantity_modal').prop('required', isProduct);
        
        // Image Header and Upload fields remain visible for both Product and Service
        $('#image_header').html(`<i class="fas fa-image"></i> Item Image (Optional)`);
        
        // Update Save Button Text
        $('#saveProductButton').html('<i class="fas fa-save"></i> Save ' + (isProduct ? 'Product' : 'Service'));

        if (!isProduct) {
            // Clear unnecessary fields if switching to service
            $('#buy_price_modal').val(0.00);
            $('#stock_quantity_modal').val(0);
            calculateProfit();
        }
    }

    $('input[name="item_type"]').on('change', function() {
        toggleProductServiceFields($(this).val());
    });
    
    // -----------------------------------------------------------
    // 3. Core Functions (Quick-Add Helpers)
    // -----------------------------------------------------------
    
    function calculateProfit() {
        const buyPrice = parseFloat($('#buy_price_modal').val()) || 0;
        const sellPrice = parseFloat($('#sell_price_modal').val()) || 0;
        
        const profit = sellPrice - buyPrice;
        let profitPercent = 0;
        
        if (buyPrice > 0) {
            profitPercent = (profit / buyPrice) * 100;
        } else if (sellPrice > 0) {
            profitPercent = 100;
        }
        
        $('#profit_display_rs').text('රු. ' + profit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#profit_display_percent').text(profitPercent.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%');
    }

    $('#buy_price_modal, #sell_price_modal').on('input', calculateProfit);
    
    function refreshDropdowns(type, data) {
        const dropdown = $('#' + type + '_id_modal');
        dropdown.empty(); 
        
        let placeholder = (type === 'category') ? '-- Select Category --' : '-- Select Supplier (Optional) --';
        dropdown.append($('<option>', {
            value: '',
            text: placeholder
        }));
        
        data.forEach(item => {
            const id = item[type + '_id'];
            const name = item[type + '_name'];
            dropdown.append($('<option>', {
                value: id,
                text: name
            }));
        });
    }
    
    // -----------------------------------------------------------
    // 4. Quick-Add List Update & Edit Load Functions (with Filter Logic)
    // -----------------------------------------------------------
    
    function updateQuickAddList(type, data, filterQuery = '') {
        const container = $('#' + type + 'ListContainer');
        container.empty();
        
        // Determine the list to filter (categoriesList or allSuppliersData)
        const currentList = (type === 'category') ? categoriesList : allSuppliersData;
        
        const filteredData = currentList.filter(item => {
             const nameMatch = item[type + '_name'].toLowerCase().includes(filterQuery.toLowerCase());
             if (type === 'supplier') {
                 // Also search contact number for supplier
                 return nameMatch || (item.contact_no && item.contact_no.includes(filterQuery));
             }
             return nameMatch;
        });

        if (filteredData.length === 0) {
            container.append('<div class="small text-muted p-3">No ' + type + 's found matching the filter.</div>');
            return;
        }

        filteredData.forEach(item => { 
            const id = item[type + '_id'];
            const name = item[type + '_name'];
            
            const editButton = `<button type="button" class="btn btn-sm btn-info text-white ms-2 me-1" onclick="loadQuickAddForEdit('${type}', ${id})">
                                    <i class="fas fa-edit"></i>
                                </button>`;
            let deleteButton = '';
            
            if (is_admin || type === 'category' || type === 'supplier') { 
                deleteButton = `<button type="button" class="btn btn-sm btn-danger" onclick="deleteQuickAdd('${type}', ${id}, '${name}')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>`;
            } 
            
            container.append(`
                <div class="list-group-item d-flex justify-content-between align-items-center p-2">
                    <span class="text-truncate">${name}</span>
                    <div>
                        ${editButton}
                        ${deleteButton}
                    </div>
                </div>
            `);
        });
        
    }

    // Attach Filter Input Event Handlers
    $('#categoryFilterInput').on('keyup', function() {
        updateQuickAddList('category', categoriesList, $(this).val());
    });

    $('#supplierFilterInput').on('keyup', function() {
        updateQuickAddList('supplier', allSuppliersData, $(this).val());
    });
    
    // Global functions for Quick-Add Edit/Reset (called from quick_add_modals.php)
    window.loadQuickAddForEdit = function(type, id) {
        const currentList = (type === 'category') ? categoriesList : allSuppliersData;
        const item = currentList.find(i => i[type + '_id'] == id);
        
        if (!item) {
            Swal.fire('දෝෂයක්!', type + ' දත්ත සොයාගත නොහැක.', 'error');
            return;
        }

        // Set form to EDIT mode
        $('#' + type + '_id_quick').val(id);
        $('#' + type + 'FormTitle').text('Edit ' + (type === 'category' ? 'Category' : 'Supplier') + ' (ID: ' + id + ')');
        $('#save' + type.charAt(0).toUpperCase() + type.slice(1) + 'Button').html('<i class="fas fa-save"></i> Update ' + type.charAt(0).toUpperCase() + type.slice(1));
        $('#cancel' + type.charAt(0).toUpperCase() + type.slice(1) + 'Edit').show();

        // Populate fields
        if (type === 'category') {
            $('#category_name_quick').val(item.category_name);
        } else if (type === 'supplier') {
            const supplierItem = allSuppliersData.find(i => i.supplier_id == id);
            $('#supplier_name_quick').val(supplierItem.supplier_name);
            $('#contact_no_quick').val(supplierItem.contact_no);
            $('#address_quick').val(supplierItem.address || '');
        }
    }
    
    window.prepareQuickAddModal = function(type) {
        $('#' + type + 'QuickAddForm')[0].reset();
        $('#' + type + '_id_quick').val('');
        $('#' + type + 'FormTitle').text('Add New ' + type.charAt(0).toUpperCase() + type.slice(1));
        $('#save' + type.charAt(0).toUpperCase() + type.slice(1) + 'Button').html('<i class="fas fa-plus"></i> Add New ' + type.charAt(0).toUpperCase() + type.slice(1));
        $('#cancel' + type.charAt(0).toUpperCase() + type.slice(1) + 'Edit').hide();
        $('#' + type + 'FilterInput').val(''); // Clear filter input
        fetchAndRefreshData(type); // Refresh and show unfiltered list
    };


    function fetchAndRefreshData(type, selectId = null) {
        const targetUrl = type + '_process.php';

        $.ajax({
            url: targetUrl,
            type: 'POST',
            data: { action: 'fetch' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    
                    if (type === 'category') {
                        categoriesList = data;
                    } else if (type === 'supplier') {
                        allSuppliersData = data; // Update global supplier list for editing
                    }
                    
                    refreshDropdowns(type, data);
                    if (selectId) {
                        $('#' + type + '_id_modal').val(selectId); 
                    }
                    
                    updateQuickAddList(type, data); // Display unfiltered list
                }
            },
            error: function() {
                // Silent error on fetch for better UX
            }
        });
    }
    
    // -----------------------------------------------------------
    // 5. Quick-Add Modal Open/Close/Submission Logic (Persistence/Auto-Select Fix)
    // -----------------------------------------------------------
    
    window.openQuickAddModal = function(type) {
        prepareQuickAddModal(type); // Reset form to Add mode
        fetchAndRefreshData(type); 
        
        if (type === 'category') {
            categoryQuickAddModal.show();
        } else if (type === 'supplier') {
            supplierQuickAddModal.show();
        }
    };
    
    window.closeQuickAddModal = function(type) {
        // Only closes when manually clicked (Persistence fix)
        if (type === 'category') {
            categoryQuickAddModal.hide();
        } else if (type === 'supplier') {
            supplierQuickAddModal.hide();
        }
        $('#productModal').focus(); // Keep focus on the main product modal
    };

    // Category Quick-Add Submission (Handles Insert & Update)
    $('#categoryQuickAddForm').on('submit', function(e) {
        e.preventDefault(); 
        const form = $(this);
        const isEditing = $('#category_id_quick').val() !== '';
        const action = isEditing ? 'update' : 'insert';
        
        $.ajax({
            url: 'category_process.php', 
            type: 'POST',
            data: form.serialize() + '&action=' + action,
            dataType: 'json', 
            success: function(response) {
                if (response.status === 'success') {
                    const newId = isEditing ? $('#category_id_quick').val() : response.data.category_id;
                    
                    // 🛑 Auto-Select Fix 🛑
                    fetchAndRefreshData('category', newId); 
                    
                    // 🛑 Persistence/Close Fix: Close only on Insert 🛑
                    if (!isEditing) {
                        closeQuickAddModal('category'); 
                        form[0].reset(); 
                    } else {
                        prepareQuickAddModal('category'); 
                    }
                }
                Swal.fire({ title: response.title, text: response.message, icon: response.icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            },
            error: function() { Swal.fire('තාක්ෂණික දෝෂයක්!', 'Category දත්ත යැවීමේ ගැටලුවක්.', 'error'); }
        });
    });

    // Supplier Quick-Add Submission (Handles Insert & Update)
    $('#supplierQuickAddForm').on('submit', function(e) {
        e.preventDefault(); 
        const form = $(this);
        const isEditing = $('#supplier_id_quick').val() !== '';
        const action = isEditing ? 'update' : 'insert';

        $.ajax({
            url: 'supplier_process.php', 
            type: 'POST',
            data: form.serialize() + '&action=' + action,
            dataType: 'json', 
            success: function(response) {
                if (response.status === 'success') {
                    const newId = isEditing ? $('#supplier_id_quick').val() : response.data.supplier_id;
                    
                    // 🛑 Auto-Select Fix 🛑
                    fetchAndRefreshData('supplier', newId); 
                    
                    // 🛑 Persistence/Close Fix: Close only on Insert 🛑
                    if (!isEditing) {
                        closeQuickAddModal('supplier'); 
                        form[0].reset(); 
                    } else {
                        prepareQuickAddModal('supplier'); 
                    }
                }
                Swal.fire({ title: response.title, text: response.message, icon: response.icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            },
            error: function() { Swal.fire('තාක්ෂණික දෝෂයක්!', 'Supplier දත්ත යැවීමේ ගැටලුවක්.', 'error'); }
        });
    });
    
    window.deleteQuickAdd = function(type, id, name) {
        const targetUrl = type + '_process.php';
        
        Swal.fire({
            title: 'ඔබට විශ්වාසද?',
            html: `ඔබට **${name}** ${type} එක ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.<br><p class='small text-danger mt-2'>මෙම ක්‍රියාව ආපසු හැරවිය නොහැක.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ඔව්, ඉවත් කරන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                const postData = (type === 'category') ? { action: 'delete', category_id: id } : { action: 'delete', supplier_id: id };
                $.ajax({
                    url: targetUrl, 
                    type: 'POST',
                    data: postData,
                    dataType: 'json',
                    success: function(response) {
                        Swal.fire({ title: response.title, text: response.message, icon: response.icon });
                        if (response.status === 'success') {
                            fetchAndRefreshData(type);
                        }
                    },
                    error: function() { Swal.fire('තාක්ෂණික දෝෂයක්!', 'Server සම්බන්ධතාවය පරීක්ෂා කරන්න.', 'error'); }
                });
            }
        });
    };
    
    // -----------------------------------------------------------
    // 6. Main Product Form Functions (Final)
    // -----------------------------------------------------------
    
    window.prepareAddModal = function() {
        $('#productInsertUpdateForm')[0].reset();
        $('#product_id_modal').val('');
        $('#productModalLabel').html('<i class="fas fa-cube"></i> Add New Item Details');
        $('#saveProductButton').text('Save Item');
        $('#product_code_display').val('(Auto Generate)').removeClass('text-muted').addClass('text-danger');
        $('#image_preview_container').html('');
        calculateProfit(); 
        $('#category_id_modal').val('');
        $('#supplier_id_modal').val('');
        
        // Set default to Product and update UI
        $('#type_product').prop('checked', true);
        toggleProductServiceFields('product');
        
        fetchAndRefreshData('category'); 
        fetchAndRefreshData('supplier');
    };
    
    window.loadProductForEdit = function(productId) {
        const product = allTableData.find(p => p.product_id == productId);
        if (!product) {
            Swal.fire('දෝෂයක්!', 'Item දත්ත සොයාගත නොහැක.', 'error');
            return;
        }
        
        const itemType = (product.buy_price > 0 || product.stock_quantity > 0 || product.image_path) ? 'product' : 'service'; 
        
        $('#productModalLabel').html('<i class="fas fa-edit"></i> Edit Item Details (ID: ' + productId + ')');
        $('#saveProductButton').text('Update Item');
        $('#product_id_modal').val(product.product_id);
        
        // Set Toggle and update UI
        if(itemType === 'service') {
            $('#type_service').prop('checked', true);
        } else {
             $('#type_product').prop('checked', true);
        }
        toggleProductServiceFields(itemType);
        
        $('#product_name_modal').val(product.product_name);
        $('#description_modal').val(product.description);
        
        // Apply pricing/stock
        $('#buy_price_modal').val(parseFloat(product.buy_price).toFixed(2));
        $('#sell_price_modal').val(parseFloat(product.sell_price).toFixed(2));
        $('#stock_quantity_modal').val(product.stock_quantity);
        
        fetchAndRefreshData('category'); 
        fetchAndRefreshData('supplier'); 
        
        setTimeout(() => {
            $('#category_id_modal').val(product.category_id);
            $('#supplier_id_modal').val(product.supplier_id);
        }, 50); 
        
        $('#product_code_display').val(product.product_code).removeClass('text-danger').addClass('text-muted');

        if (product.image_path) {
             $('#image_preview_container').html(`<img src="${product.image_path}" alt="Product Image" style="max-width: 150px; max-height: 150px; border: 1px solid #ccc; padding: 5px; border-radius: 5px;">`);
        } else {
             $('#image_preview_container').html('<p class="small text-muted">No image uploaded.</p>');
        }
        
        calculateProfit();
        productModal.show();
    };


    $('#productInsertUpdateForm').on('submit', function(e) {
        e.preventDefault(); 
        if ($('#category_id_modal').val() === '') {
            Swal.fire('අවශ්‍ය දත්ත!', 'කරුණාකර Category එකක් තෝරන්න.', 'warning');
            return;
        }
        
        const isEditing = $('#product_id_modal').val() !== '';
        const formData = new FormData(this);
        formData.append('action', isEditing ? 'update' : 'insert');

        $.ajax({
            url: 'product_process.php', 
            type: 'POST',
            data: formData,
            processData: false, 
            contentType: false, 
            dataType: 'json', 
            success: function(response) {
                Swal.fire({ title: response.title || 'දැනුම්දීම!', text: response.message || 'කිසියම් දෝශයක් ඇත.', icon: response.icon || 'info' }).then(() => {
                    if (response.status === 'success') {
                        productModal.hide();
                        window.location.reload(); 
                    }
                });
            },
            error: function() { Swal.fire({ title: 'තාක්ෂණික දෝෂයක්!', text: 'දත්ත යැවීමේදී ගැටලුවක් සිදුවිය. Server සම්බන්ධතාවය පරීක්ෂා කරන්න.', icon: 'error' }); }
        });
    });
    
    window.confirmAndDelete = function(productId, productName) {
        if (!is_admin) {
            Swal.fire('අවසර නැත!', 'Delete කිරීමට අවසර ඇත්තේ පරිපාලක හට පමණි.', 'warning');
            return;
        }
        Swal.fire({
            title: 'ඔබට විශ්වාසද?',
            html: `ඔබට Item **${productName}** (ID: ${productId}) ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.<br><p class='small text-danger mt-2'>**දැනුම්දීම:** මෙම භාණ්ඩය වෙනත් ලේඛනවලට සම්බන්ධ නම් මකා දැමීම සිදු නොවේ.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ඔව්, ඉවත් කරන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'product_process.php', 
                    type: 'POST',
                    data: { action: 'delete', product_id: productId },
                    dataType: 'json',
                    success: function(response) {
                        Swal.fire({ title: response.title, text: response.message, icon: response.icon }).then(() => {
                            if (response.status === 'success') {
                                window.location.reload(); 
                            }
                        });
                    },
                    error: function() { Swal.fire('තාක්ෂණික දෝෂයක්!', 'Server සම්බන්ධතාවය පරීක්ෂා කරන්න.', 'error'); }
                });
            }
        });
    };
    
});
</script>