<?php
// File Name: create_quotation.php
// Description: Create & Edit Quotation (Smart Return Logic)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'header.php';
require_once 'db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Database connection failed: " . $conn->connect_error); }

$is_edit = false;
$q_id = 0;
$q_data = ['client_id' => '', 'quotation_date' => date('Y-m-d'), 'valid_until' => '', 'quotation_terms' => '', 'quotation_number' => ''];
$q_items = [];

// Default Terms
$res_s = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_warranty_terms'");
if($res_s && $res_s->num_rows > 0) $q_data['quotation_terms'] = $res_s->fetch_assoc()['setting_value'];

// CHECK EDIT MODE
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $q_id = intval($_GET['id']);
    $is_edit = true;

    $stmt = $conn->prepare("SELECT * FROM quotations WHERE quotation_id = ?");
    $stmt->bind_param("i", $q_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $q_data = $res->fetch_assoc();
        $stmt_items = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
        $stmt_items->bind_param("i", $q_id);
        $stmt_items->execute();
        $res_items = $stmt_items->get_result();
        while ($row = $res_items->fetch_assoc()) { $q_items[] = $row; }
    } else {
        echo "<script>alert('Invalid Quotation ID'); window.location='quotations.php';</script>"; exit;
    }
}

// HANDLE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        $client_id = intval($_POST['client_id']);
        $user_id = intval($_SESSION['user_id']);
        $date = $_POST['quotation_date'];
        $valid = !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;
        $terms = $_POST['quotation_terms'];
        $sub_total = floatval($_POST['sub_total']);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $grand_total = floatval($_POST['grand_total']);
        $items = $_POST['items'] ?? [];

        if (empty($items)) throw new Exception("Please add at least one item.");

        if ($is_edit) {
            $sql = "UPDATE quotations SET client_id=?, quotation_date=?, valid_until=?, sub_total=?, tax_amount=?, grand_total=?, quotation_terms=? WHERE quotation_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdddsi", $client_id, $date, $valid, $sub_total, $tax_amount, $grand_total, $terms, $q_id);
            $stmt->execute();
            $conn->query("DELETE FROM quotation_items WHERE quotation_id = $q_id");
            $target_id = $q_id;
            $msg = "Quotation updated successfully!";
        } else {
            $prefix = 'QUO-' . date('Y') . '-';
            $res = $conn->query("SELECT quotation_number FROM quotations WHERE quotation_number LIKE '$prefix%' ORDER BY quotation_id DESC LIMIT 1");
            $next = 1;
            if($res->num_rows > 0) {
                $parts = explode('-', $res->fetch_assoc()['quotation_number']);
                $next = intval(end($parts)) + 1;
            }
            $q_num = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO quotations (quotation_number, client_id, user_id, quotation_date, valid_until, sub_total, tax_amount, grand_total, status, quotation_terms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siissddds", $q_num, $client_id, $user_id, $date, $valid, $sub_total, $tax_amount, $grand_total, $terms);
            $stmt->execute();
            $target_id = $conn->insert_id;
            $msg = "Quotation ($q_num) created successfully!";
        }

        $stmt_item = $conn->prepare("INSERT INTO quotation_items (quotation_id, product_id, item_name, quantity, unit_price, buy_price) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $pname = $item['item_name']; 
            $qty = floatval($item['quantity']);
            $price = floatval($item['unit_price']);
            $bp = 0;
            $p_res = $conn->query("SELECT buy_price FROM products WHERE product_id = $pid");
            if($p_res->num_rows > 0) $bp = $p_res->fetch_assoc()['buy_price'];
            $stmt_item->bind_param("iisddd", $target_id, $pid, $pname, $qty, $price, $bp);
            $stmt_item->execute();
        }

        $conn->commit();
        echo "<script>
            Swal.fire({ title: 'Success!', text: '$msg', icon: 'success', showConfirmButton: false, timer: 1500 }).then(() => {
                window.location.href = 'view_quotation.php?id=$target_id';
            });
        </script>";
        exit(); 

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = $e->getMessage();
    }
}

$clients = []; $res_c = $conn->query("SELECT client_id, client_name, phone FROM clients ORDER BY client_name ASC"); while($row = $res_c->fetch_assoc()) $clients[] = $row;
$products = []; $res_p = $conn->query("SELECT product_id, product_name, product_code, sell_price, stock_quantity, buy_price FROM products ORDER BY product_name ASC"); while($row = $res_p->fetch_assoc()) $products[] = $row;
$conn->close();
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE); 
$existing_items_json = json_encode($q_items);

// --- SMART CANCEL LINK ---
$cancel_link = $is_edit ? "view_quotation.php?id=$q_id" : "quotations.php";
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da; }
    .select2-container--bootstrap-5 .select2-selection { border-color: #ced4da; }
    .totals-box { background: #f8f9fa; border-radius: 10px; padding: 20px; border: 1px solid #e9ecef; }
    .grand-total-text { font-size: 1.5rem; color: #27b19d; font-weight: 800; }
</style>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
        <strong>Error:</strong> <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="quotationForm">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h2 class="text-primary fw-bold m-0">
                <i class="<?php echo $is_edit ? 'fas fa-edit' : 'fas fa-file-invoice'; ?>"></i> 
                <?php echo $is_edit ? "Edit Quotation ({$q_data['quotation_number']})" : "Create New Quotation"; ?>
            </h2>
        </div>
        <div>
            <a href="<?php echo $cancel_link; ?>" class="btn btn-secondary me-2">Cancel</a>
            <button type="submit" class="btn btn-primary shadow-sm px-4">
                <i class="fas fa-save me-2"></i> <?php echo $is_edit ? "Update" : "Save"; ?>
            </button>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-uppercase text-muted">Select Client <span class="text-danger">*</span></label>
                    <select class="form-select select2-client" name="client_id" required>
                        <option value="">-- Choose Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['client_id']; ?>" <?php echo ($q_data['client_id'] == $c['client_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['client_name']) . ' (' . $c['phone'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Date</label>
                    <input type="date" class="form-control" name="quotation_date" value="<?php echo $q_data['quotation_date']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">Valid Until</label>
                    <input type="date" class="form-control" name="valid_until" value="<?php echo $q_data['valid_until']; ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Items & Services</h6>
            <button type="button" class="btn btn-success btn-sm rounded-pill px-3" onclick="addNewRow()">
                <i class="fas fa-plus-circle me-1"></i> Add Item
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 align-middle" id="q-table">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th width="3%" class="text-center">#</th>
                            <th width="40%">Item Description <span class="text-danger">*</span></th>
                            <th width="15%" class="text-end">Unit Price</th>
                            <th width="12%" class="text-center">Qty</th>
                            <th width="18%" class="text-end">Total (Rs.)</th>
                            <th width="5%" class="text-center"><i class="fas fa-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="q-table-body"></tbody>
                </table>
            </div>
            <div id="empty-msg" class="text-center py-5 text-muted" style="display:none;">
                <i class="fas fa-box-open fa-3x mb-3 text-light-gray"></i><br>No items added yet. Click "Add Item" to start.
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow-sm mb-4 h-100">
                <div class="card-header bg-light fw-bold text-muted small text-uppercase">Terms & Conditions</div>
                <div class="card-body">
                    <textarea class="form-control" name="quotation_terms" rows="6" style="font-size:0.9rem;"><?php echo htmlspecialchars($q_data['quotation_terms']); ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="totals-box shadow-sm text-end h-100 d-flex flex-column justify-content-center">
                <div class="row mb-2">
                    <div class="col-6 text-muted">Sub Total:</div>
                    <div class="col-6 fw-bold" id="display_sub_total">0.00</div>
                    <input type="hidden" name="sub_total" id="sub_total" value="0">
                </div>
                <div class="row mb-3 align-items-center justify-content-end">
                    <div class="col-auto text-muted">Tax / VAT:</div>
                    <div class="col-4">
                        <input type="number" class="form-control text-end form-control-sm" name="tax_amount" id="tax_amount" value="<?php echo isset($q_data['tax_amount']) ? $q_data['tax_amount'] : '0.00'; ?>" step="0.01" oninput="calculateTotals()">
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-6 h5 mb-0 text-muted text-uppercase">Grand Total</div>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const productsDB = <?php echo $products_json; ?>;
    const existingItems = <?php echo $existing_items_json; ?>;
    let rowCount = 0;

    $(document).ready(function() {
        $('.select2-client').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Select Client --' });
        if (existingItems.length > 0) { existingItems.forEach(item => { addNewRow(item); }); } else { addNewRow(); }
    });

    function addNewRow(data = null) {
        $('#empty-msg').hide();
        rowCount++;
        let options = `<option value="">-- Select Item --</option>`;
        productsDB.forEach(p => {
            let stockInfo = (p.buy_price > 0) ? `(Stock: ${p.stock_quantity})` : `(Service)`;
            let selected = (data && data.product_id == p.product_id) ? 'selected' : '';
            options += `<option value="${p.product_id}" data-price="${p.sell_price}" data-name="${p.product_name}" ${selected}>${p.product_name} - ${p.product_code} ${stockInfo}</option>`;
        });

        let qty = data ? data.quantity : 1;
        let price = data ? data.unit_price : '0.00';
        let name = data ? data.item_name : '';

        let html = `
            <tr id="row-${rowCount}">
                <td class="text-center text-muted align-middle">${rowCount}</td>
                <td><select class="form-select item-select" name="items[${rowCount}][product_id]" required onchange="itemSelected(this, ${rowCount})">${options}</select><input type="hidden" name="items[${rowCount}][item_name]" id="name-${rowCount}" value="${name}"></td>
                <td><input type="number" class="form-control text-end" name="items[${rowCount}][unit_price]" id="price-${rowCount}" value="${price}" step="0.01" oninput="calculateRow(${rowCount})" required></td>
                <td><input type="number" class="form-control text-center" name="items[${rowCount}][quantity]" id="qty-${rowCount}" value="${qty}" min="0.1" step="any" oninput="calculateRow(${rowCount})" required></td>
                <td><input type="text" class="form-control text-end fw-bold bg-white border-0" id="total-${rowCount}" value="0.00" readonly></td>
                <td class="text-center align-middle"><button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(${rowCount})"><i class="fas fa-trash-alt"></i></button></td>
            </tr>`;

        $('#q-table-body').append(html);
        $(`#row-${rowCount} .item-select`).select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Search Product / Service', dropdownParent: $('body') });
        calculateRow(rowCount);
    }

    function itemSelected(select, id) {
        let option = $(select).find(':selected');
        $(`#price-${id}`).val(parseFloat(option.data('price') || 0).toFixed(2));
        $(`#name-${id}`).val(option.data('name') || '');
        calculateRow(id);
    }

    function calculateRow(id) {
        let qty = parseFloat($(`#qty-${id}`).val()) || 0;
        let price = parseFloat($(`#price-${id}`).val()) || 0;
        $(`#total-${id}`).val((qty * price).toFixed(2));
        calculateTotals();
    }

    function removeRow(id) {
        $(`#row-${id}`).remove();
        if($('#q-table-body tr').length === 0) $('#empty-msg').show();
        calculateTotals();
    }

    function calculateTotals() {
        let subTotal = 0;
        $('input[id^="total-"]').each(function() { subTotal += parseFloat($(this).val()) || 0; });
        let tax = parseFloat($('#tax_amount').val()) || 0;
        let grandTotal = subTotal + tax;
        $('#display_sub_total').text(subTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#sub_total').val(subTotal);
        $('#display_grand_total').text(grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#grand_total').val(grandTotal);
    }
</script>