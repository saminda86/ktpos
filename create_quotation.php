<?php
// File Name: create_quotation.php
// Description: Professional Quotation Creation (Fixed: jQuery Added)

$page_title = 'Create New Quotation';
require_once 'header.php';
require_once 'db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- 1. Helper: Generate Quotation Number ---
function generateQuotationNumber($conn) {
    $prefix = 'QUO-';
    $year = date('Y');
    $search_prefix = $prefix . $year . '-';
    
    $sql = "SELECT quotation_number FROM quotations WHERE quotation_number LIKE ? ORDER BY quotation_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $param = $search_prefix . '%';
    $stmt->bind_param("s", $param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $new_number = 1;
    if ($result->num_rows > 0) {
        $last_quote = $result->fetch_assoc();
        $parts = explode('-', $last_quote['quotation_number']);
        $last_seq = end($parts);
        $new_number = intval($last_seq) + 1;
    }
    
    $stmt->close();
    return $search_prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

// --- 2. Handle POST Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $quotation_date = $_POST['quotation_date'];
        $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;
        $quotation_terms = $_POST['quotation_terms'] ?? '';
        
        $sub_total = floatval($_POST['sub_total']);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $grand_total = floatval($_POST['grand_total']);
        
        $items = $_POST['items'] ?? [];

        if ($client_id === 0) throw new Exception("කරුණාකර පාරිභෝගිකයෙකු තෝරන්න.");
        if (empty($items)) throw new Exception("අවම වශයෙන් එක් භාණ්ඩයක් හෝ ඇතුළත් කළ යුතුය.");

        $quotation_number = generateQuotationNumber($conn);

        // Insert Quotation
        $sql_quote = "INSERT INTO quotations (quotation_number, client_id, user_id, quotation_date, valid_until, sub_total, tax_amount, grand_total, status, quotation_terms) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";
        $stmt = $conn->prepare($sql_quote);
        $stmt->bind_param("siissddds", $quotation_number, $client_id, $user_id, $quotation_date, $valid_until, $sub_total, $tax_amount, $grand_total, $quotation_terms);
        
        if (!$stmt->execute()) throw new Exception("Quotation Error: " . $stmt->error);
        $quotation_id = $conn->insert_id;
        $stmt->close();

        // Insert Items
        $sql_item = "INSERT INTO quotation_items (quotation_id, product_id, item_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $pname = $item['item_name']; 
            $qty = floatval($item['quantity']);
            $price = floatval($item['unit_price']);
            
            $stmt_item->bind_param("iisdd", $quotation_id, $pid, $pname, $qty, $price);
            if (!$stmt_item->execute()) throw new Exception("Item Error: " . $stmt_item->error);
        }
        $stmt_item->close();

        $conn->commit();
        $_SESSION['success_message'] = "Quotation ($quotation_number) සාර්ථකව නිර්මාණය කරන ලදි!";
        echo "<script>window.location.href='quotations.php';</script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "දෝෂයක්: " . $e->getMessage();
    }
}

// --- 3. Fetch Data ---
$clients = [];
$res_c = $conn->query("SELECT client_id, client_name, phone FROM clients ORDER BY client_name ASC");
while($row = $res_c->fetch_assoc()) $clients[] = $row;

$products = [];
$res_p = $conn->query("SELECT product_id, product_name, product_code, sell_price, stock_quantity, buy_price FROM products ORDER BY product_name ASC");
while($row = $res_p->fetch_assoc()) $products[] = $row;

$default_terms = "";
$res_s = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_warranty_terms'");
if($res_s && $res_s->num_rows > 0) $default_terms = $res_s->fetch_assoc()['setting_value'];

$conn->close();
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE); 
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--bootstrap-5 .select2-selection { border-color: #ced4da; }
    .totals-box { background: #f8f9fa; border-radius: 10px; padding: 20px; }
    .grand-total-text { font-size: 1.5rem; color: #27b19d; font-weight: 800; }
</style>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="create_quotation.php" method="POST" id="quotationForm">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold m-0"><i class="fas fa-file-invoice"></i> New Quotation</h2>
        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
            <i class="fas fa-save me-2"></i> Save Quotation
        </button>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Select Client <span class="text-danger">*</span></label>
                    <select class="form-select select2-client" name="client_id" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['client_id']; ?>">
                                <?php echo htmlspecialchars($c['client_name']) . ' (' . $c['phone'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Date</label>
                    <input type="date" class="form-control" name="quotation_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Valid Until</label>
                    <input type="date" class="form-control" name="valid_until">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Quotation Items</h6>
            <button type="button" class="btn btn-success btn-sm" onclick="addNewRow()">
                <i class="fas fa-plus-circle me-1"></i> Add Item
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="q-table">
                    <thead class="table-light">
                        <tr>
                            <th width="40%">Item / Service Description <span class="text-danger">*</span></th>
                            <th width="15%" class="text-end">Unit Price</th>
                            <th width="12%" class="text-center">Qty</th>
                            <th width="18%" class="text-end">Total (Rs.)</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody id="q-table-body">
                        </tbody>
                </table>
            </div>
            <div id="empty-msg" class="text-center py-4 text-muted">
                <i class="fas fa-box-open fa-2x mb-2"></i><br>Click "Add Item" to start.
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light fw-bold">Terms & Conditions</div>
                <div class="card-body">
                    <textarea class="form-control" name="quotation_terms" rows="5"><?php echo htmlspecialchars($default_terms); ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="totals-box shadow-sm text-end">
                <div class="row mb-2">
                    <div class="col-6 text-muted">Sub Total:</div>
                    <div class="col-6 fw-bold" id="display_sub_total">0.00</div>
                    <input type="hidden" name="sub_total" id="sub_total" value="0">
                </div>
                <div class="row mb-3 align-items-center justify-content-end">
                    <div class="col-auto text-muted">Tax / VAT:</div>
                    <div class="col-4">
                        <input type="number" class="form-control text-end form-control-sm" name="tax_amount" id="tax_amount" value="0.00" step="0.01" oninput="calculateTotals()">
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-6 h5 mb-0">Grand Total:</div>
                    <div class="col-6 grand-total-text" id="display_grand_total">0.00</div>
                    <input type="hidden" name="grand_total" id="grand_total" value="0">
                </div>
            </div>
        </div>
    </div>

</form>

<?php require_once 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    const productsDB = <?php echo $products_json; ?>;
    let rowCount = 0;

    $(document).ready(function() {
        // Initialize Client Select2
        $('.select2-client').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Select Client --'
        });

        // Add first row automatically
        addNewRow();
    });

    function addNewRow() {
        $('#empty-msg').hide();
        rowCount++;
        
        let options = `<option value="">-- Select Item --</option>`;
        productsDB.forEach(p => {
            let stockInfo = (p.buy_price > 0) ? `(Stock: ${p.stock_quantity})` : `(Service)`;
            options += `<option value="${p.product_id}" data-price="${p.sell_price}" data-name="${p.product_name}">
                            ${p.product_name} - ${p.product_code} ${stockInfo}
                        </option>`;
        });

        let html = `
            <tr id="row-${rowCount}">
                <td>
                    <select class="form-select item-select" name="items[${rowCount}][product_id]" required onchange="itemSelected(this, ${rowCount})">
                        ${options}
                    </select>
                    <input type="hidden" name="items[${rowCount}][item_name]" id="name-${rowCount}">
                </td>
                <td>
                    <input type="number" class="form-control text-end" name="items[${rowCount}][unit_price]" id="price-${rowCount}" value="0.00" step="0.01" oninput="calculateRow(${rowCount})" required>
                </td>
                <td>
                    <input type="number" class="form-control text-center" name="items[${rowCount}][quantity]" id="qty-${rowCount}" value="1" min="1" step="any" oninput="calculateRow(${rowCount})" required>
                </td>
                <td>
                    <input type="text" class="form-control text-end fw-bold bg-white" id="total-${rowCount}" value="0.00" readonly>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(${rowCount})">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#q-table-body').append(html);

        // Initialize Select2 for the new dropdown only
        $(`#row-${rowCount} .item-select`).select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search Product / Service',
            dropdownParent: $('body') 
        });
    }

    function itemSelected(select, id) {
        let option = $(select).find(':selected');
        let price = option.data('price') || 0;
        let name = option.data('name') || '';

        $(`#price-${id}`).val(parseFloat(price).toFixed(2));
        $(`#name-${id}`).val(name);
        calculateRow(id);
    }

    function calculateRow(id) {
        let qty = parseFloat($(`#qty-${id}`).val()) || 0;
        let price = parseFloat($(`#price-${id}`).val()) || 0;
        let total = qty * price;
        $(`#total-${id}`).val(total.toFixed(2));
        calculateTotals();
    }

    function removeRow(id) {
        $(`#row-${id}`).remove();
        if($('#q-table-body tr').length === 0) {
            $('#empty-msg').show();
        }
        calculateTotals();
    }

    function calculateTotals() {
        let subTotal = 0;
        $('input[id^="total-"]').each(function() {
            subTotal += parseFloat($(this).val()) || 0;
        });

        let tax = parseFloat($('#tax_amount').val()) || 0;
        let grandTotal = subTotal + tax;

        $('#display_sub_total').text(subTotal.toFixed(2));
        $('#sub_total').val(subTotal);
        
        $('#display_grand_total').text(grandTotal.toFixed(2));
        $('#grand_total').val(grandTotal);
    }
</script>