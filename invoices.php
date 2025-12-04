<?php
// File Name: invoices.php (List All Invoices + Create/Edit Modals)

$page_title = 'Invoices';
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
// Fetch Invoice Data (List Page)
// -----------------------------------------------------------
$invoice_data = [];
$total_records = 0;
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " WHERE i.invoice_number LIKE '%{$search_query}%' 
                          OR c.client_name LIKE '%{$search_query}%'
                          OR c.phone LIKE '%{$search_query}%'";
}

// 1. Total Count
$total_result = $conn->query("SELECT COUNT(i.invoice_id) AS count 
                              FROM invoices i 
                              LEFT JOIN clients c ON i.client_id = c.client_id" . $search_condition);
$total_records = $total_result->fetch_assoc()['count'] ?? 0;
$total_pages = ceil($total_records / $limit);

// 2. Data Fetch for List
$sql = "SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.grand_total, i.payment_status, c.client_name 
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.client_id"
        . $search_condition . " ORDER BY i.invoice_id DESC LIMIT {$start}, {$limit}";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $invoice_data[] = $row;
    }
}

// -----------------------------------------------------------
// Fetch Data for MODALS
// -----------------------------------------------------------
// 1. Fetch Clients
$clients = [];
$client_result = $conn->query("SELECT client_id, client_name, phone FROM clients ORDER BY client_name ASC");
if ($client_result) {
    while ($row = $client_result->fetch_assoc()) {
        $clients[] = $row;
    }
}

// 2. Fetch Products & Services (For JS Suggestion Box)
$products = [];
$product_result = $conn->query("SELECT product_id, product_name, product_code, sell_price, buy_price, stock_quantity, image_path 
                                FROM products 
                                ORDER BY product_name ASC");
if ($product_result) {
    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}

// 3. Fetch Settings (For JS Modal)
$settings = [];
$settings_result = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('default_warranty_terms', 'show_warranty_on_invoice')");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$conn->close();

// Encode data for JavaScript
$products_json = json_encode($products);
$default_terms = json_encode($settings['default_warranty_terms'] ?? '');
$show_warranty = (isset($settings['show_warranty_on_invoice']) && $settings['show_warranty_on_invoice'] == '1');


// Helper function for status badges
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'Paid':
            return '<span class="badge bg-success">Paid</span>';
        case 'Pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'Unpaid':
            return '<span class="badge bg-danger">Unpaid</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}
?>

<style>
    /* 🛑 Professional Item Search Suggestions */
    #product-suggestions {
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        background-color: #ffffff;
        max-height: 250px;
        overflow-y: auto;
        position: absolute;
        z-index: 1056; /* Higher than modal */
        width: 96%; /* Fit inside the modal column */
    }
    #product-suggestions .list-group-item {
        color: #212529;
        font-size: 0.9rem;
        padding: 10px 15px;
        border-top: 1px solid #f0f0f0;
        line-height: 1.3;
    }
    #product-suggestions .list-group-item:hover,
    #product-suggestions .list-group-item:focus {
        background-color: var(--light-grey-bg);
        color: #000000;
        cursor: pointer;
    }
    #product-suggestions .suggestion-image {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 3px;
        margin-right: 10px;
    }
    #product-suggestions .suggestion-details {
        flex: 1;
    }
    #product-suggestions .suggestion-name {
        font-weight: 700;
        color: var(--primary-color);
    }
    #product-suggestions .suggestion-meta {
        font-size: 0.8em;
        color: #6c757d;
    }

    /* 🛑 Modal Item Table Enhancements */
    #invoice-items-table {
        font-size: 0.9rem;
    }
    #invoice-items-table th {
        white-space: nowrap;
    }
    #invoice-items-table tbody tr {
        vertical-align: middle;
    }
    #invoice-items-table .item-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 3px;
    }
    #invoice-items-table .form-control-sm {
        height: calc(1.5em + 0.5rem + 2px);
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .profit-display {
        font-size: 0.8rem;
        font-weight: bold;
    }
    .profit-positive { color: #198754; }
    .profit-negative { color: #dc3545; }
    .profit-zero { color: #6c757d; }
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

<h1 class="mb-4 text-primary"><i class="fas fa-receipt"></i> Invoice Management</h1>
<hr>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
        <h6 class="m-0 font-weight-bold text-primary">All System Invoices (Total: <?php echo $total_records; ?>)</h6>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create New Invoice
        </button>
    </div>
    <div class="card-body">
        
        <div class="row mb-3">
            <div class="col-md-7">
                <form action="invoices.php" method="GET" class="input-group">
                    <input type="text" 
                           class="form-control" 
                           placeholder="Search by Invoice #, Client Name, or Phone" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           autocomplete="off">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="invoices.php" class="btn btn-secondary" title="Clear Search"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="invoicesTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client Name</th>
                        <th>Date</th>
                        <th>Amount (රු.)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoice_data)): ?>
                        <?php foreach ($invoice_data as $invoice): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['client_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($invoice['invoice_date'])); ?></td>
                            <td><?php echo number_format($invoice['grand_total'], 2); ?></td>
                            <td><?php echo getPaymentStatusBadge($invoice['payment_status']); ?></td>
                            <td>
                                <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-success" title="View/Print">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-info text-white" onclick="openEditModal(<?php echo $invoice['invoice_id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin'): ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="confirmDeleteInvoice(<?php echo $invoice['invoice_id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number'], ENT_QUOTES, 'UTF-8'); ?>')" 
                                        title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No invoices found <?php echo !empty($search_query) ? "matching '{$search_query}'" : ""; ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <div class="small text-muted">Showing <?php echo min($limit, count($invoice_data)); ?> of <?php echo $total_records; ?> records.</div>
            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="invoices.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="invoices.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="invoices.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
        
    </div>
</div>


<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="invoice-form">
                <div class="modal-header">
                    <h5 class="modal-title text-primary" id="invoiceModalLabel"><i class="fas fa-file-invoice-dollar"></i> Create New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background-color: #f8f9fa;">
                    
                    <input type="hidden" id="invoice_id" name="invoice_id" value="">
                    
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-5">
                                    <label for="client_id" class="form-label fw-bold">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="" disabled selected>-- Select a Client --</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['client_id']; ?>">
                                                <?php echo htmlspecialchars($client['client_name']) . ' (' . htmlspecialchars($client['phone']) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="invoice_date" class="form-label fw-bold">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="payment_status" class="form-label fw-bold">Payment Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_status" name="payment_status" required>
                                        <option value="Paid" class="text-success fw-bold">Paid</option>
                                        <option value="Pending" selected class="text-warning fw-bold">Pending</option>
                                        <option value="Unpaid" class="text-danger fw-bold">Unpaid</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mb-3">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Invoice Items</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-8 position-relative">
                                    <label for="product_search" class="form-label">Search & Add Item</label>
                                    <input type="text" class="form-control" id="product_search" placeholder="Type product name or code to search...">
                                    <div id="product-suggestions" class="list-group" style="display: none;"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted">Stock Status</label>
                                    <input type="text" class="form-control-plaintext" id="product_search_stock" value="-" readonly>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table" id="invoice-items-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 5%;">No.</th>
                                            <th style="width: 8%;">Image</th>
                                            <th style="width: 25%;">Item/Service</th>
                                            <th style="width: 15%;">Serial</th>
                                            <th style="width: 10%;">Qty</th>
                                            <th style="width: 12%;">Unit Price</th>
                                            <th style="width: 10%;">Profit</th>
                                            <th style="width: 10%;">Total</th>
                                            <th style="width: 5%;">Act</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr id="items-table-placeholder">
                                            <td colspan="9" class="text-center text-muted p-4">Please add items using the search bar above.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-7" id="terms-container" style="display: <?php echo $show_warranty ? 'block' : 'none'; ?>;">
                            <div class="card shadow-sm mb-3">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Terms & Conditions</h6>
                                </div>
                                <div class="card-body">
                                    <textarea class="form-control" id="invoice_terms" name="invoice_terms" rows="8"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card shadow-sm mb-3">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Invoice Totals</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-sm-5 col-form-label fw-bold">Sub Total (රු.):</label>
                                        <div class="col-sm-7">
                                            <input type="number" readonly class="form-control-plaintext text-end fw-bold" id="sub_total_display" value="0.00">
                                            <input type="hidden" name="sub_total" id="sub_total_hidden" value="0.00">
                                        </div>
                                    </div>
                                    <div class="row mb-2 align-items-center">
                                        <label for="tax_amount" class="col-sm-5 col-form-label fw-bold">Tax (රු.):</label>
                                        <div class="col-sm-7">
                                            <input type="number" class="form-control text-end" id="tax_amount" name="tax_amount" value="0.00" step="0.01" oninput="calculateTotals()">
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <label class="col-sm-5 col-form-label h4 text-primary fw-bolder">Grand Total:</label>
                                        <div class="col-sm-7">
                                            <input type="number" readonly class="form-control-plaintext text-end h4 text-primary fw-bolder" id="grand_total_display" value="0.00">
                                            <input type="hidden" name="grand_total" id="grand_total_hidden" value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveInvoiceButton"><i class="fas fa-save"></i> Save Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php 
require_once 'footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// -----------------------------------------------------------
// GLOBAL DATA (from PHP)
// -----------------------------------------------------------
const allProductsData = <?php echo $products_json; ?>;
const defaultTerms = <?php echo $default_terms; ?>;
const mainModal = new bootstrap.Modal(document.getElementById('invoiceModal'));
let itemRowCounter = 0; // Counter for unique row IDs
let currentFormAction = ''; // URL to submit the form to

// -----------------------------------------------------------
// MODAL CONTROL (Create / Edit)
// -----------------------------------------------------------
function openCreateModal() {
    // 1. Reset Form
    $('#invoice-form')[0].reset();
    $('#invoice-items-table tbody').html('<tr id="items-table-placeholder"><td colspan="9" class="text-center text-muted p-4">Please add items using the search bar above.</td></tr>');
    itemRowCounter = 0;
    
    // 2. Set Titles & Actions
    $('#invoiceModalLabel').html('<i class="fas fa-file-invoice-dollar"></i> Create New Invoice');
    $('#saveInvoiceButton').html('<i class="fas fa-save"></i> Save Invoice');
    currentFormAction = 'invoice_process.php?action=create';
    
    // 3. Set Defaults
    $('#invoice_date').val('<?php echo date('Y-m-d'); ?>');
    $('#payment_status').val('Pending');
    $('#invoice_terms').val(defaultTerms);
    calculateTotals();
    
    // 4. Show Modal
    mainModal.show();
}

function openEditModal(invoiceId) {
    // 1. Reset Form
    $('#invoice-form')[0].reset();
    $('#invoice-items-table tbody').html('<tr id="items-table-placeholder"><td colspan="9" class="text-center text-muted p-4">Loading items...</td></tr>');
    itemRowCounter = 0;

    // 2. Set Titles & Actions
    $('#invoiceModalLabel').html(`<i class="fas fa-edit"></i> Edit Invoice (Loading...)`);
    $('#saveInvoiceButton').html('<i class="fas fa-save"></i> Update Invoice');
    currentFormAction = `invoice_process.php?action=update&id=${invoiceId}`;
    $('#invoice_id').val(invoiceId);

    // 3. Show Modal (empty)
    mainModal.show();
    
    // 4. Load Data via AJAX
    $.ajax({
        url: 'invoice_load.php',
        type: 'GET',
        data: { id: invoiceId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const invoice = response.data.invoice;
                const items = response.data.items;
                
                // Populate Header
                $('#invoiceModalLabel').html(`<i class="fas fa-edit"></i> Edit Invoice (${invoice.invoice_number})`);
                $('#client_id').val(invoice.client_id);
                $('#invoice_date').val(invoice.invoice_date);
                $('#payment_status').val(invoice.payment_status);
                
                // Populate Footer
                $('#invoice_terms').val(invoice.invoice_terms);
                $('#tax_amount').val(parseFloat(invoice.tax_amount).toFixed(2));
                
                // Populate Items
                $('#items-table-placeholder').remove(); // Remove placeholder
                items.forEach(item => {
                    addItemRow(item); // Pass full item object
                });
                
                calculateTotals();
            } else {
                Swal.fire('Error', response.message, 'error');
                mainModal.hide();
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to load invoice data. Please check connection.', 'error');
            mainModal.hide();
        }
    });
}

// -----------------------------------------------------------
// PRODUCT SUGGESTION (Live Search)
// -----------------------------------------------------------
const searchInput = document.getElementById('product_search');
const suggestionsDiv = document.getElementById('product-suggestions');
const stockDisplay = document.getElementById('product_search_stock');

searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    suggestionsDiv.innerHTML = '';
    
    if (query.length < 2) {
        suggestionsDiv.style.display = 'none';
        stockDisplay.value = '-';
        return;
    }

    const filtered = allProductsData.filter(p => 
        p.product_name.toLowerCase().includes(query) || 
        p.product_code.toLowerCase().includes(query)
    );
    
    if (filtered.length > 0) {
        suggestionsDiv.style.display = 'block';
        filtered.slice(0, 5).forEach(product => { // Show max 5
            const isService = (product.buy_price <= 0 && product.stock_quantity <= 0);
            const stockInfo = isService ? 'Service' : `Stock: ${product.stock_quantity}`;
            const img_path = product.image_path ? product.image_path : 'uploads/products/default.png';
            
            suggestionsDiv.innerHTML += `
                <a class="list-group-item list-group-item-action d-flex align-items-center" 
                   data-id="${product.product_id}" 
                   onmouseover="showStock(${product.product_id})" 
                   onclick="selectProduct(${product.product_id}); return false;">
                    
                    <img src="${img_path}" class="suggestion-image">
                    <div class="suggestion-details">
                        <div class="suggestion-name">${product.product_name}</div>
                        <div class="suggestion-meta">Code: ${product.product_code} | Price: ${product.sell_price} | ${stockInfo}</div>
                    </div>
                </a>
            `;
        });
    } else {
        suggestionsDiv.style.display = 'none';
    }
});

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target)) {
        suggestionsDiv.style.display = 'none';
        stockDisplay.value = '-';
    }
});

function showStock(productId) {
    const product = allProductsData.find(p => p.product_id == productId);
    if (product) {
        const isService = (product.buy_price <= 0 && product.stock_quantity <= 0);
        stockDisplay.value = isService ? 'Service (N/A)' : `Available: ${product.stock_quantity}`;
        
        if (!isService && product.stock_quantity <= 0) {
            stockDisplay.classList.add('text-danger', 'fw-bold');
        } else {
            stockDisplay.classList.remove('text-danger', 'fw-bold');
        }
    }
}

function selectProduct(productId) {
    const product = allProductsData.find(p => p.product_id == productId);
    if (product) {
        // Create an item object structure matching the 'Edit' load
        const itemData = {
            product_id: product.product_id,
            item_name: product.product_name,
            serial_number: '',
            quantity: 1,
            unit_price: product.sell_price,
            buy_price: product.buy_price,
            image_path: product.image_path
        };
        addItemRow(itemData);
    }
    
    // Clear search
    searchInput.value = '';
    suggestionsDiv.style.display = 'none';
    stockDisplay.value = '-';
}

// -----------------------------------------------------------
// DYNAMIC ITEM TABLE (Add / Remove / Calculate)
// -----------------------------------------------------------
function addItemRow(item) {
    // item = { product_id, item_name, serial_number, quantity, unit_price, buy_price, image_path }
    
    // Remove placeholder if it exists
    $('#items-table-placeholder').remove();
    
    itemRowCounter++;
    const rowId = `row_${itemRowCounter}`;
    const img_path = item.image_path ? item.image_path : 'uploads/products/default.png';
    
    const newRow = `
        <tr id="${rowId}">
            <td class="fw-bold">${itemRowCounter}</td>
            
            <td><img src="${img_path}" class="item-image"></td>
            
            <td>
                ${item.item_name}
                <input type="hidden" name="items[${rowId}][product_id]" value="${item.product_id}">
                <input type="hidden" name="items[${rowId}][item_name]" value="${item.item_name}">
                <input type="hidden" class="item-buy-price" name="items[${rowId}][buy_price]" value="${item.buy_price}">
            </td>
            
            <td><input type="text" class="form-control form-control-sm" name="items[${rowId}][serial_number]" value="${item.serial_number}" placeholder="Serial / Note"></td>
            
            <td><input type="number" class="form-control form-control-sm text-end item-qty" name="items[${rowId}][quantity]" value="${item.quantity}" step="any" min="0.01" oninput="calculateTotals()"></td>
            
            <td><input type="number" class="form-control form-control-sm text-end item-price" name="items[${rowId}][unit_price]" value="${parseFloat(item.unit_price).toFixed(2)}" step="0.01" min="0" oninput="calculateTotals()"></td>
            
            <td class="profit-display profit-zero text-end">0.00</td>
            
            <td class="fw-bold text-end item-total">0.00</td>
            
            <td><button type="button" class="btn btn-danger btn-sm" onclick="deleteItemRow('${rowId}')"><i class="fas fa-trash-alt"></i></button></td>
        </tr>
    `;
    $('#invoice-items-table tbody').append(newRow);
    calculateTotals();
}

function deleteItemRow(rowId) {
    Swal.fire({
        title: 'Remove Item?',
        text: "Are you sure you want to remove this item from the invoice?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $(`#${rowId}`).remove();
            calculateTotals();
            
            // Show placeholder if table is empty
            if ($('#invoice-items-table tbody tr').length === 0) {
                 $('#invoice-items-table tbody').html('<tr id="items-table-placeholder"><td colspan="9" class="text-center text-muted p-4">Please add items using the search bar above.</td></tr>');
            }
        }
    });
}

function calculateTotals() {
    let subTotal = 0;
    
    $('#invoice-items-table tbody tr').each(function() {
        if (this.id === 'items-table-placeholder') return;
        
        const qty = parseFloat($(this).find('.item-qty').val()) || 0;
        const price = parseFloat($(this).find('.item-price').val()) || 0;
        const buyPrice = parseFloat($(this).find('.item-buy-price').val()) || 0;

        const lineTotal = qty * price;
        const lineProfit = (price - buyPrice) * qty;
        
        // Update line total
        $(this).find('.item-total').text(lineTotal.toFixed(2));
        
        // Update profit display
        const profitEl = $(this).find('.profit-display');
        profitEl.text(lineProfit.toFixed(2));
        profitEl.removeClass('profit-positive profit-negative profit-zero');
        if (lineProfit > 0) profitEl.addClass('profit-positive');
        else if (lineProfit < 0) profitEl.addClass('profit-negative');
        else profitEl.addClass('profit-zero');

        subTotal += lineTotal;
    });

    const tax = parseFloat($('#tax_amount').val()) || 0;
    const grandTotal = subTotal + tax;

    // Update footer totals
    $('#sub_total_display').val(subTotal.toFixed(2));
    $('#sub_total_hidden').val(subTotal.toFixed(2));
    $('#grand_total_display').val(grandTotal.toFixed(2));
    $('#grand_total_hidden').val(grandTotal.toFixed(2));
}

// -----------------------------------------------------------
// FORM SUBMISSION (AJAX)
// -----------------------------------------------------------
$('#invoice-form').on('submit', function(e) {
    e.preventDefault();
    
    // Validation
    if ($('#invoice-items-table tbody tr').length === 0 || $('#items-table-placeholder').length > 0) {
        Swal.fire('Error', 'You must add at least one item to the invoice.', 'warning');
        return;
    }
    
    const formData = $(this).serialize();
    $('#saveInvoiceButton').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    
    $.ajax({
        url: currentFormAction,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                mainModal.hide();
                Swal.fire({
                    title: 'Success!',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload(); // Reload the list page
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'An unknown error occurred. Please try again.', 'error');
        },
        complete: function() {
            // Re-enable button
             $('#saveInvoiceButton').prop('disabled', false).html('<i class="fas fa-save"></i> Save Invoice');
        }
    });
});


// -----------------------------------------------------------
// PAGE-LEVEL DELETE (from list)
// -----------------------------------------------------------
function confirmDeleteInvoice(invoiceId, invoiceNumber) {
    Swal.fire({
        title: 'ඔබට විශ්වාසද?',
        html: `ඔබට ඉන්වොයිස් අංක <b>${invoiceNumber}</b> (ID: ${invoiceId}) ඉවත් කිරීමට අවශ්‍යද?<br><br><strong class='text-danger'>මෙම ක්‍රියාව ආපසු හැරවිය නොහැක. අදාළ සියලුම භාණ්ඩ තොග (Stock) වෙත නැවත එකතු වනු ඇත.</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ඔව්, ඉවත් කරන්න!',
        cancelButtonText: 'අවලංගු කරන්න'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to the delete script
            window.location.href = `delete_invoice.php?id=${invoiceId}`;
        }
    });
}
</script>