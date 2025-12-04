<?php
// File Name: create_invoice.php (New Invoice Form)

$page_title = 'Create New Invoice';
require_once 'header.php';
require_once 'db_connect.php';
require_once 'invoice_helpers.php'; // Include helpers

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// -----------------------------------------------------------
// Handle Form Submission (POST)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    
    try {
        // --- 1. Get Form Data ---
        $client_id = intval($_POST['client_id']);
        $user_id = intval($_SESSION['user_id']);
        $invoice_date = $_POST['invoice_date'];
        $payment_status = $_POST['payment_status'];
        $invoice_terms = $_POST['invoice_terms'] ?? NULL;
        $sub_total = floatval($_POST['sub_total']);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $grand_total = floatval($_POST['grand_total']);
        
        $items = $_POST['items'] ?? [];
        
        if (empty($items)) {
            throw new Exception("You must add at least one item to the invoice.");
        }

        // --- 2. Generate Invoice Number ---
        $invoice_number = generateInvoiceNumber($conn);

        // --- 3. Insert into `invoices` table ---
        $sql_invoice = "INSERT INTO invoices (invoice_number, client_id, user_id, invoice_date, sub_total, tax_amount, grand_total, payment_status, invoice_terms) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("siisddsss", $invoice_number, $client_id, $user_id, $invoice_date, $sub_total, $tax_amount, $grand_total, $payment_status, $invoice_terms);
        
        if (!$stmt_invoice->execute()) {
            throw new Exception("Failed to save invoice: " . $stmt_invoice->error);
        }
        $invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        // --- 4. Insert into `invoice_items` table ---
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, item_name, serial_number, quantity, unit_price, buy_price) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);
        
        foreach ($items as $item) {
            $stmt_items->bind_param(
                "iisssdd",
                $invoice_id,
                $item['product_id'],
                $item['item_name'], // Storing name at time of sale
                $item['serial_number'],
                $item['quantity'],
                $item['unit_price'],
                $item['buy_price']
            );
            if (!$stmt_items->execute()) {
                throw new Exception("Failed to save invoice item: " . $stmt_items->error);
            }
        }
        $stmt_items->close();
        
        // --- 5. Adjust Product Stock ---
        if (!adjustStockOnCreate($conn, $items)) {
            // Error message is set inside the function
            throw new Exception($_SESSION['error_message'] ?? "Stock adjustment failed.");
        }

        // --- 6. Commit Transaction ---
        $conn->commit();
        $_SESSION['success_message'] = "Invoice (<b>{$invoice_number}</b>) created successfully! Stock has been updated.";
        header('Location: invoices.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error creating invoice: " . $e->getMessage();
        // Stay on the same page to show error
        header('Location: create_invoice.php');
        exit();
    }
}

// -----------------------------------------------------------
// Fetch Data for Form (GET)
// -----------------------------------------------------------

// 1. Fetch Clients
$clients = [];
$client_result = $conn->query("SELECT client_id, client_name, phone FROM clients ORDER BY client_name ASC");
if ($client_result) {
    while ($row = $client_result->fetch_assoc()) {
        $clients[] = $row;
    }
}

// 2. Fetch Products & Services
$products = [];
// We get products from `products` table AND services from `services` table
// Your `products.php` file implies products and services are in *one* table: `products`
// I will follow the logic from your `products.php`
// 'Services' are items in the `products` table where buy_price is 0.

$product_result = $conn->query("SELECT product_id, product_name, product_code, sell_price, buy_price, stock_quantity 
                                FROM products 
                                ORDER BY product_name ASC");
if ($product_result) {
    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}

// 3. Fetch Settings
$settings = [];
$settings_result = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('default_warranty_terms', 'show_warranty_on_invoice')");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$conn->close();
$products_json = json_encode($products);
?>

<style>
    #invoice-items-table tbody tr {
        vertical-align: middle;
    }
    #invoice-items-table .form-control {
        font-size: 0.85rem;
    }
    #invoice-items-table .profit-display {
        font-size: 0.8rem;
        font-weight: bold;
    }
    .profit-positive { color: #198754; }
    .profit-negative { color: #dc3545; }
    .profit-zero { color: #6c757d; }
</style>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php 
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="create_invoice.php" method="POST" id="invoice-form">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-primary m-0"><i class="fas fa-file-invoice-dollar"></i> Create New Invoice</h1>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Save Invoice
        </button>
    </div>
    <hr>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Invoice Details</h6>
        </div>
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
                        <option value="Paid" class="text-success fw-bold">Paid (ගෙවා ඇත)</option>
                        <option value="Pending" selected class="text-warning fw-bold">Pending (අර්ධව ගෙවා ඇත)</option>
                        <option value="Unpaid" class="text-danger fw-bold">Unpaid (නොගෙවා ඇත)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Invoice Items</h6>
            <button type="button" class="btn btn-success btn-sm" onclick="addNewItemRow()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="invoice-items-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Item/Service <span class="text-danger">*</span></th>
                            <th style="width: 15%;">Serial Number</th>
                            <th style="width: 10%;">Stock</th>
                            <th style="width: 10%;">Quantity <span class="text-danger">*</span></th>
                            <th style="width: 15%;">Unit Price (රු.) <span class="text-danger">*</span></th>
                            <th style="width: 15%;">Total (රු.)</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-7">
            <?php if (isset($settings['show_warranty_on_invoice']) && $settings['show_warranty_on_invoice'] == '1'): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Terms & Conditions</h6>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="invoice_terms" rows="8"><?php echo htmlspecialchars($settings['default_warranty_terms'] ?? ''); ?></textarea>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-5">
            <div class="card shadow mb-4">
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
                        <label class="col-sm-5 col-form-label h4 text-primary fw-bolder">Grand Total (රු.):</label>
                        <div class="col-sm-7">
                            <input type="number" readonly class="form-control-plaintext text-end h4 text-primary fw-bolder" id="grand_total_display" value="0.00">
                            <input type="hidden" name="grand_total" id="grand_total_hidden" value="0.00">
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
</form>


<?php 
require_once 'footer.php';
?>

<script>
// Store product data from PHP
const allProductsData = <?php echo $products_json; ?>;
let itemRowCounter = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Add one item row by default
    addNewItemRow();
});

function addNewItemRow() {
    itemRowCounter++;
    const tableBody = document.getElementById('invoice-items-table').getElementsByTagName('tbody')[0];
    const newRow = tableBody.insertRow();
    newRow.id = `item-row-${itemRowCounter}`;
    
    // 0. Product/Service Dropdown
    const cellProduct = newRow.insertCell(0);
    let productOptions = `<option value="" data-name="" data-sell="0" data-buy="0" data-stock="0" disabled selected>-- Select Item --</option>`;
    allProductsData.forEach(p => {
        let stockInfo = (p.buy_price > 0 || p.stock_quantity > 0) ? `(Stock: ${p.stock_quantity})` : `(Service)`;
        productOptions += `<option value="${p.product_id}" data-name="${p.product_name}" data-sell="${p.sell_price}" data-buy="${p.buy_price}" data-stock="${p.stock_quantity}">
                                ${p.product_name} ${stockInfo}
                           </option>`;
    });
    cellProduct.innerHTML = `
        <select class="form-select" name="items[${itemRowCounter}][product_id]" id="product_id_${itemRowCounter}" onchange="productSelected(this)" required>
            ${productOptions}
        </select>
        <input type="hidden" name="items[${itemRowCounter}][item_name]" id="item_name_${itemRowCounter}" value="">
        <input type="hidden" name="items[${itemRowCounter}][buy_price]" id="buy_price_${itemRowCounter}" value="0.00">
    `;

    // 1. Serial Number
    const cellSerial = newRow.insertCell(1);
    cellSerial.innerHTML = `<input type="text" class="form-control" name="items[${itemRowCounter}][serial_number]" placeholder="Serial / Note">`;

    // 2. Stock
    const cellStock = newRow.insertCell(2);
    cellStock.innerHTML = `<input type="text" class="form-control-plaintext text-center fw-bold" id="stock_${itemRowCounter}" value="-" readonly>`;

    // 3. Quantity
    const cellQty = newRow.insertCell(3);
    cellQty.innerHTML = `<input type="number" class="form-control text-end" name="items[${itemRowCounter}][quantity]" id="quantity_${itemRowCounter}" value="1" step="any" min="0.01" oninput="calculateTotals()" required>`;
    
    // 4. Unit Price
    const cellPrice = newRow.insertCell(4);
    cellPrice.innerHTML = `<input type="number" class="form-control text-end" name="items[${itemRowCounter}][unit_price]" id="unit_price_${itemRowCounter}" value="0.00" step="0.01" min="0" oninput="calculateTotals()" required>`;

    // 5. Total
    const cellTotal = newRow.insertCell(5);
    cellTotal.innerHTML = `
        <input type="text" class="form-control-plaintext text-end fw-bold" id="total_display_${itemRowCounter}" value="0.00" readonly>
        <div class="profit-display profit-zero" id="profit_${itemRowCounter}">Profit: 0.00</div>
    `;

    // 6. Action
    const cellAction = newRow.insertCell(6);
    cellAction.innerHTML = `<button type="button" class="btn btn-danger btn-sm" onclick="deleteItemRow(this)"><i class="fas fa-trash-alt"></i></button>`;
}

function productSelected(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const rowId = selectElement.id.split('_')[2];
    
    const sellPrice = selectedOption.getAttribute('data-sell');
    const buyPrice = selectedOption.getAttribute('data-buy');
    const stock = selectedOption.getAttribute('data-stock');
    const itemName = selectedOption.getAttribute('data-name');

    document.getElementById(`unit_price_${rowId}`).value = parseFloat(sellPrice).toFixed(2);
    document.getElementById(`buy_price_${rowId}`).value = parseFloat(buyPrice).toFixed(2);
    document.getElementById(`stock_${rowId}`).value = (buyPrice > 0 || stock > 0) ? stock : 'N/A';
    document.getElementById(`item_name_${rowId}`).value = itemName;
    
    // Add stock validation style
    if (buyPrice > 0 && parseFloat(stock) <= 0) {
        document.getElementById(`stock_${rowId}`).classList.add('text-danger');
    } else {
        document.getElementById(`stock_${rowId}`).classList.remove('text-danger');
    }

    calculateTotals();
}

function deleteItemRow(button) {
    // Don't delete the last row
    const tableBody = document.getElementById('invoice-items-table').getElementsByTagName('tbody')[0];
    if (tableBody.rows.length <= 1) {
        Swal.fire('Warning', 'You cannot remove the last item row.', 'warning');
        return;
    }
    
    const row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
    calculateTotals();
}

function calculateTotals() {
    let subTotal = 0;
    const tableBody = document.getElementById('invoice-items-table').getElementsByTagName('tbody')[0];
    
    for (let i = 0; i < tableBody.rows.length; i++) {
        const row = tableBody.rows[i];
        const rowId = row.id.split('-')[2];
        
        const quantity = parseFloat(document.getElementById(`quantity_${rowId}`).value) || 0;
        const unitPrice = parseFloat(document.getElementById(`unit_price_${rowId}`).value) || 0;
        const buyPrice = parseFloat(document.getElementById(`buy_price_${rowId}`).value) || 0;

        const lineTotal = quantity * unitPrice;
        const lineProfit = (unitPrice - buyPrice) * quantity;
        
        // Update line total display
        document.getElementById(`total_display_${rowId}`).value = lineTotal.toFixed(2);
        
        // Update profit display
        const profitEl = document.getElementById(`profit_${rowId}`);
        profitEl.textContent = `Profit: ${lineProfit.toFixed(2)}`;
        profitEl.className = 'profit-display'; // Reset classes
        if (lineProfit > 0) {
            profitEl.classList.add('profit-positive');
        } else if (lineProfit < 0) {
            profitEl.classList.add('profit-negative');
        } else {
            profitEl.classList.add('profit-zero');
        }

        subTotal += lineTotal;
    }

    const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    const grandTotal = subTotal + tax;

    // Update footer totals
    document.getElementById('sub_total_display').value = subTotal.toFixed(2);
    document.getElementById('sub_total_hidden').value = subTotal.toFixed(2);
    document.getElementById('grand_total_display').value = grandTotal.toFixed(2);
    document.getElementById('grand_total_hidden').value = grandTotal.toFixed(2);
}
</script>