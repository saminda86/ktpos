<?php
// File Name: create_invoice.php
// Description: Create New Invoice (With "Convert from Quotation" Logic)

$page_title = 'Create New Invoice';
require_once 'header.php';
require_once 'db_connect.php';
require_once 'invoice_helpers.php'; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// -----------------------------------------------------------
// 1. "CONVERT TO INVOICE" LOGIC (Pre-fill Data)
// -----------------------------------------------------------
$prefill_data = [];
$prefill_items = [];
$is_converting = false;

if (isset($_GET['from_quote']) && is_numeric($_GET['from_quote'])) {
    $q_id = intval($_GET['from_quote']);
    
    // Fetch Main Quotation Details
    $q_res = $conn->query("SELECT * FROM quotations WHERE quotation_id = $q_id");
    
    if ($q_res && $q_res->num_rows > 0) {
        $quote = $q_res->fetch_assoc();
        $is_converting = true;
        
        $prefill_data['client_id'] = $quote['client_id'];
        $prefill_data['sub_total'] = $quote['sub_total'];
        $prefill_data['tax_amount'] = $quote['tax_amount'];
        $prefill_data['grand_total'] = $quote['grand_total'];
        
        // Fetch Items & Join with Products to get Buy Price & Current Stock
        // Note: Quotations don't usually store buy_price, so we fetch current buy_price from products table
        $sql_q_items = "SELECT qi.*, p.buy_price, p.stock_quantity, p.product_code 
                        FROM quotation_items qi 
                        LEFT JOIN products p ON qi.product_id = p.product_id 
                        WHERE qi.quotation_id = $q_id";
        
        $res_q_items = $conn->query($sql_q_items);
        while($row = $res_q_items->fetch_assoc()) {
            // If product was deleted, buy_price might be null, handle gracefully
            if(is_null($row['buy_price'])) $row['buy_price'] = 0;
            if(is_null($row['stock_quantity'])) $row['stock_quantity'] = 0;
            $prefill_items[] = $row;
        }
    }
}

// -----------------------------------------------------------
// 2. HANDLE FORM SUBMISSION (POST)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    
    try {
        // --- Get Form Data ---
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

        // --- Generate Invoice Number ---
        $invoice_number = generateInvoiceNumber($conn);

        // --- Insert Invoice ---
        $sql_invoice = "INSERT INTO invoices (invoice_number, client_id, user_id, invoice_date, sub_total, tax_amount, grand_total, payment_status, invoice_terms) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("siisddsss", $invoice_number, $client_id, $user_id, $invoice_date, $sub_total, $tax_amount, $grand_total, $payment_status, $invoice_terms);
        
        if (!$stmt_invoice->execute()) {
            throw new Exception("Failed to save invoice: " . $stmt_invoice->error);
        }
        $invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        // --- Insert Items ---
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, item_name, serial_number, quantity, unit_price, buy_price) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);
        
        foreach ($items as $item) {
            $stmt_items->bind_param(
                "iisssdd",
                $invoice_id,
                $item['product_id'],
                $item['item_name'],
                $item['serial_number'],
                $item['quantity'],
                $item['unit_price'],
                $item['buy_price']
            );
            if (!$stmt_items->execute()) {
                throw new Exception("Failed to save item: " . $stmt_items->error);
            }
        }
        $stmt_items->close();
        
        // --- Adjust Stock ---
        if (!adjustStockOnCreate($conn, $items)) {
            throw new Exception($_SESSION['error_message'] ?? "Stock adjustment failed.");
        }
        
        // --- Update Quotation Status (If converted) ---
        if (isset($_POST['from_quote_id']) && is_numeric($_POST['from_quote_id'])) {
            $q_id_update = intval($_POST['from_quote_id']);
            $conn->query("UPDATE quotations SET status = 'Accepted' WHERE quotation_id = $q_id_update");
        }

        $conn->commit();
        $_SESSION['success_message'] = "Invoice (<b>{$invoice_number}</b>) created successfully!";
        header('Location: invoices.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: create_invoice.php" . ($is_converting ? "?from_quote=$q_id" : ""));
        exit();
    }
}

// -----------------------------------------------------------
// 3. FETCH DATA FOR DROPDOWNS
// -----------------------------------------------------------
// Clients
$clients = [];
$res_clients = $conn->query("SELECT client_id, client_name, phone FROM clients ORDER BY client_name ASC");
while ($row = $res_clients->fetch_assoc()) $clients[] = $row;

// Products (For JS Dropdown)
$products = [];
$res_products = $conn->query("SELECT product_id, product_name, product_code, sell_price, buy_price, stock_quantity FROM products ORDER BY product_name ASC");
while ($row = $res_products->fetch_assoc()) $products[] = $row;

// Settings
$default_terms = '';
$show_warranty = false;
$res_settings = $conn->query("SELECT * FROM system_settings WHERE setting_key IN ('default_warranty_terms', 'show_warranty_on_invoice')");
while ($row = $res_settings->fetch_assoc()) {
    if ($row['setting_key'] == 'default_warranty_terms') $default_terms = $row['setting_value'];
    if ($row['setting_key'] == 'show_warranty_on_invoice') $show_warranty = ($row['setting_value'] == '1');
}

$conn->close();
$products_json = json_encode($products);
?>

<style>
    #invoice-items-table tbody tr { vertical-align: middle; }
    #invoice-items-table .form-control { font-size: 0.85rem; }
    .profit-display { font-size: 0.8rem; font-weight: bold; }
    .profit-positive { color: #198754; }
    .profit-negative { color: #dc3545; }
    .profit-zero { color: #6c757d; }
</style>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form action="create_invoice.php" method="POST" id="invoice-form">
    
    <?php if ($is_converting): ?>
        <input type="hidden" name="from_quote_id" value="<?php echo $q_id; ?>">
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-primary m-0"><i class="fas fa-file-invoice-dollar"></i> Create New Invoice</h1>
        <button type="submit" class="btn btn-primary btn-lg shadow">
            <i class="fas fa-save"></i> Save Invoice
        </button>
    </div>
    
    <?php if ($is_converting): ?>
        <div class="alert alert-info border-info shadow-sm">
            <i class="fas fa-info-circle me-2"></i> 
            <strong>Creating Invoice from Quotation #<?php echo str_pad($q_id, 4, '0', STR_PAD_LEFT); ?></strong>. 
            Please verify quantities and stock before saving.
        </div>
    <?php endif; ?>

    <hr>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light">
            <h6 class="m-0 font-weight-bold text-primary">Invoice Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-5">
                    <label for="client_id" class="form-label fw-bold">Client <span class="text-danger">*</span></label>
                    <select class="form-select" id="client_id" name="client_id" required>
                        <option value="" disabled <?php echo !$is_converting ? 'selected' : ''; ?>>-- Select a Client --</option>
                        <?php foreach ($clients as $client): 
                            $selected = (isset($prefill_data['client_id']) && $prefill_data['client_id'] == $client['client_id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $client['client_id']; ?>" <?php echo $selected; ?>>
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
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
            <h6 class="m-0 font-weight-bold text-primary">Invoice Items</h6>
            <button type="button" class="btn btn-success btn-sm" onclick="addNewItemRow()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="invoice-items-table">
                    <thead class="table-light">
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
                        <?php if ($is_converting && !empty($prefill_items)): ?>
                            <?php foreach ($prefill_items as $index => $item): 
                                $row_id = $index + 1;
                                $qty = floatval($item['quantity']);
                                $price = floatval($item['unit_price']);
                                $buy_price = floatval($item['buy_price']);
                                $total = $qty * $price;
                                $profit = ($price - $buy_price) * $qty;
                                
                                // Stock Display Logic
                                $is_service = ($buy_price <= 0 && $item['stock_quantity'] <= 0);
                                $stock_display = $is_service ? 'N/A' : $item['stock_quantity'];
                                $stock_class = (!$is_service && $item['stock_quantity'] <= 0) ? 'text-danger fw-bold' : '';
                            ?>
                            <tr id="item-row-<?php echo $row_id; ?>">
                                <td>
                                    <select class="form-select" name="items[<?php echo $row_id; ?>][product_id]" id="product_id_<?php echo $row_id; ?>" onchange="productSelected(this)" required>
                                        <option value="">-- Select Item --</option>
                                        <?php foreach ($products as $p): 
                                            $sel = ($p['product_id'] == $item['product_id']) ? 'selected' : '';
                                            $stkInfo = ($p['buy_price'] > 0 || $p['stock_quantity'] > 0) ? "(Stock: {$p['stock_quantity']})" : "(Service)";
                                        ?>
                                            <option value="<?php echo $p['product_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($p['product_name']); ?>" 
                                                data-sell="<?php echo $p['sell_price']; ?>" 
                                                data-buy="<?php echo $p['buy_price']; ?>" 
                                                data-stock="<?php echo $p['stock_quantity']; ?>" <?php echo $sel; ?>>
                                                <?php echo htmlspecialchars($p['product_name']) . " " . $stkInfo; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="items[<?php echo $row_id; ?>][item_name]" id="item_name_<?php echo $row_id; ?>" value="<?php echo htmlspecialchars($item['item_name']); ?>">
                                    <input type="hidden" name="items[<?php echo $row_id; ?>][buy_price]" id="buy_price_<?php echo $row_id; ?>" value="<?php echo $buy_price; ?>">
                                </td>
                                <td><input type="text" class="form-control" name="items[<?php echo $row_id; ?>][serial_number]" placeholder="Serial / Note"></td>
                                <td><input type="text" class="form-control-plaintext text-center <?php echo $stock_class; ?>" id="stock_<?php echo $row_id; ?>" value="<?php echo $stock_display; ?>" readonly></td>
                                <td><input type="number" class="form-control text-end" name="items[<?php echo $row_id; ?>][quantity]" id="quantity_<?php echo $row_id; ?>" value="<?php echo $qty; ?>" step="any" min="0.01" oninput="calculateTotals()" required></td>
                                <td><input type="number" class="form-control text-end" name="items[<?php echo $row_id; ?>][unit_price]" id="unit_price_<?php echo $row_id; ?>" value="<?php echo $price; ?>" step="0.01" min="0" oninput="calculateTotals()" required></td>
                                <td>
                                    <input type="text" class="form-control-plaintext text-end fw-bold" id="total_display_<?php echo $row_id; ?>" value="<?php echo number_format($total, 2, '.', ''); ?>" readonly>
                                    <div class="profit-display <?php echo ($profit > 0 ? 'profit-positive' : ($profit < 0 ? 'profit-negative' : 'profit-zero')); ?>" id="profit_<?php echo $row_id; ?>">Profit: <?php echo number_format($profit, 2); ?></div>
                                </td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="deleteItemRow(this)"><i class="fas fa-trash-alt"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-7">
            <?php if ($show_warranty): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">Terms & Conditions</h6>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="invoice_terms" rows="8"><?php echo htmlspecialchars($default_terms); ?></textarea>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">Invoice Totals</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <label class="col-sm-5 col-form-label fw-bold">Sub Total (රු.):</label>
                        <div class="col-sm-7">
                            <input type="number" readonly class="form-control-plaintext text-end fw-bold" id="sub_total_display" value="<?php echo isset($prefill_data['sub_total']) ? $prefill_data['sub_total'] : '0.00'; ?>">
                            <input type="hidden" name="sub_total" id="sub_total_hidden" value="<?php echo isset($prefill_data['sub_total']) ? $prefill_data['sub_total'] : '0.00'; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-2 align-items-center">
                        <label for="tax_amount" class="col-sm-5 col-form-label fw-bold">Tax (රු.):</label>
                        <div class="col-sm-7">
                            <input type="number" class="form-control text-end" id="tax_amount" name="tax_amount" value="<?php echo isset($prefill_data['tax_amount']) ? $prefill_data['tax_amount'] : '0.00'; ?>" step="0.01" oninput="calculateTotals()">
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mb-2">
                        <label class="col-sm-5 col-form-label h4 text-primary fw-bolder">Grand Total (රු.):</label>
                        <div class="col-sm-7">
                            <input type="number" readonly class="form-control-plaintext text-end h4 text-primary fw-bolder" id="grand_total_display" value="<?php echo isset($prefill_data['grand_total']) ? $prefill_data['grand_total'] : '0.00'; ?>">
                            <input type="hidden" name="grand_total" id="grand_total_hidden" value="<?php echo isset($prefill_data['grand_total']) ? $prefill_data['grand_total'] : '0.00'; ?>">
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
</form>

<?php require_once 'footer.php'; ?>

<script>
const allProductsData = <?php echo $products_json; ?>;
// Initialize row counter based on pre-filled items
let itemRowCounter = <?php echo isset($prefill_items) ? count($prefill_items) : 0; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // If no items were pre-filled, add one empty row
    if (itemRowCounter === 0) {
        addNewItemRow();
    }
});

function addNewItemRow() {
    itemRowCounter++;
    const tableBody = document.getElementById('invoice-items-table').getElementsByTagName('tbody')[0];
    const newRow = tableBody.insertRow();
    newRow.id = `item-row-${itemRowCounter}`;
    
    // Product Dropdown
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

    // Serial
    const cellSerial = newRow.insertCell(1);
    cellSerial.innerHTML = `<input type="text" class="form-control" name="items[${itemRowCounter}][serial_number]" placeholder="Serial / Note">`;

    // Stock
    const cellStock = newRow.insertCell(2);
    cellStock.innerHTML = `<input type="text" class="form-control-plaintext text-center fw-bold" id="stock_${itemRowCounter}" value="-" readonly>`;

    // Quantity
    const cellQty = newRow.insertCell(3);
    cellQty.innerHTML = `<input type="number" class="form-control text-end" name="items[${itemRowCounter}][quantity]" id="quantity_${itemRowCounter}" value="1" step="any" min="0.01" oninput="calculateTotals()" required>`;
    
    // Unit Price
    const cellPrice = newRow.insertCell(4);
    cellPrice.innerHTML = `<input type="number" class="form-control text-end" name="items[${itemRowCounter}][unit_price]" id="unit_price_${itemRowCounter}" value="0.00" step="0.01" min="0" oninput="calculateTotals()" required>`;

    // Total & Profit
    const cellTotal = newRow.insertCell(5);
    cellTotal.innerHTML = `
        <input type="text" class="form-control-plaintext text-end fw-bold" id="total_display_${itemRowCounter}" value="0.00" readonly>
        <div class="profit-display profit-zero" id="profit_${itemRowCounter}">Profit: 0.00</div>
    `;

    // Delete Button
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
    document.getElementById(`item_name_${rowId}`).value = itemName;
    
    const stockEl = document.getElementById(`stock_${rowId}`);
    
    // Check if it's a service (Cost=0 & Stock=0)
    if (parseFloat(buyPrice) <= 0 && parseFloat(stock) <= 0) {
        stockEl.value = 'N/A';
        stockEl.classList.remove('text-danger');
    } else {
        stockEl.value = stock;
        if (parseFloat(stock) <= 0) {
            stockEl.classList.add('text-danger');
        } else {
            stockEl.classList.remove('text-danger');
        }
    }

    calculateTotals();
}

function deleteItemRow(button) {
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
        
        document.getElementById(`total_display_${rowId}`).value = lineTotal.toFixed(2);
        
        const profitEl = document.getElementById(`profit_${rowId}`);
        profitEl.textContent = `Profit: ${lineProfit.toFixed(2)}`;
        profitEl.className = 'profit-display'; 
        if (lineProfit > 0) profitEl.classList.add('profit-positive');
        else if (lineProfit < 0) profitEl.classList.add('profit-negative');
        else profitEl.classList.add('profit-zero');

        subTotal += lineTotal;
    }

    const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    const grandTotal = subTotal + tax;

    document.getElementById('sub_total_display').value = subTotal.toFixed(2);
    document.getElementById('sub_total_hidden').value = subTotal.toFixed(2);
    document.getElementById('grand_total_display').value = grandTotal.toFixed(2);
    document.getElementById('grand_total_hidden').value = grandTotal.toFixed(2);
}
</script>