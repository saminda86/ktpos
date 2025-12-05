<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; border: none; }
        .table thead { background-color: #343a40; color: white; }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="text-center mb-4 text-uppercase fw-bold text-primary">Inventory Management System</h2>

    <div class="card shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-circle"></i> + Add New Item
                </button>
                <button class="btn btn-secondary ms-2" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    Categories
                </button>
                <button class="btn btn-info ms-2 text-white" data-bs-toggle="modal" data-bs-target="#supplierModal">
                    Suppliers
                </button>
            </div>
            
            <div class="col-md-4 offset-md-2">
                <select id="category_filter" class="form-select border-primary">
                    <option value="all">Filter by Category: Show All</option>
                    <?php
                    // PHP Code to fetch categories for Dropdown
                    $conn = new mysqli("localhost", "root", "", "inventory_db");
                    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
                    $sql = "SELECT * FROM categories";
                    $result = $conn->query($sql);
                    while($row = $result->fetch_assoc()) {
                        echo "<option value='".$row['id']."'>".$row['name']."</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Buying Price (Cost)</th>
                            <th>Selling Price</th>
                            <th>Profit</th> <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="product_list">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addProductModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="add_product_form">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" id="p_name" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select id="p_cat" class="form-select">
                                <?php 
                                $cats = $conn->query("SELECT * FROM categories");
                                while($row = $cats->fetch_assoc()) { echo "<option value='".$row['id']."'>".$row['name']."</option>"; }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier</label>
                            <select id="p_sup" class="form-select">
                                <?php 
                                $sups = $conn->query("SELECT * FROM suppliers");
                                while($row = $sups->fetch_assoc()) { echo "<option value='".$row['id']."'>".$row['name']."</option>"; }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="row bg-light p-3 rounded">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Buying Price (Cost)</label>
                            <input type="number" step="0.01" id="buying_price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" id="selling_price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Estimated Profit</label>
                            <input type="text" id="profit_display" class="form-control fw-bold" readonly value="0.00">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 mt-3">Save Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Manage Categories</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="new_cat_name" class="form-control" placeholder="New Category Name">
                    <button class="btn btn-success" id="btn_add_cat">Add</button>
                </div>
                <ul class="list-group" id="cat_list_area">
                    <?php
                    $cats = $conn->query("SELECT * FROM categories");
                    while($row = $cats->fetch_assoc()) {
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">
                                '.$row['name'].'
                                <button class="btn btn-sm btn-danger del_cat" data-id="'.$row['id'].'">Delete</button>
                              </li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Manage Suppliers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="new_sup_name" class="form-control" placeholder="New Supplier Name">
                    <button class="btn btn-success" id="btn_add_sup">Add</button>
                </div>
                <ul class="list-group">
                    <?php
                    $sups = $conn->query("SELECT * FROM suppliers");
                    while($row = $sups->fetch_assoc()) {
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">
                                '.$row['name'].'
                                <button class="btn btn-sm btn-danger del_sup" data-id="'.$row['id'].'">Delete</button>
                              </li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function(){

    // --- 1. Load Products on Page Load ---
    loadProducts('all');

    // --- 2. Filter by Category ---
    $('#category_filter').change(function(){
        var category_id = $(this).val();
        loadProducts(category_id);
    });

    // AJAX Function
    function loadProducts(cat_id) {
        $.ajax({
            url: "action.php",
            method: "POST",
            data: {action: 'fetch_products', category_id: cat_id},
            success: function(data){
                $('#product_list').html(data);
            }
        });
    }

    // --- 3. Auto Calculate Profit (Real-time) ---
    $('#buying_price, #selling_price').on('input', function(){
        var buy = parseFloat($('#buying_price').val()) || 0;
        var sell = parseFloat($('#selling_price').val()) || 0;
        var profit = sell - buy;
        
        $('#profit_display').val(profit.toFixed(2));

        if(profit < 0) {
            $('#profit_display').css({'color': 'red', 'border-color': 'red'});
        } else {
            $('#profit_display').css({'color': 'green', 'border-color': 'green'});
        }
    });

    // --- 4. Add Product Form Submit ---
    $('#add_product_form').submit(function(e){
        e.preventDefault();
        
        var name = $('#p_name').val();
        var cat = $('#p_cat').val();
        var sup = $('#p_sup').val();
        var buy = $('#buying_price').val();
        var sell = $('#selling_price').val();

        $.ajax({
            url: "action.php",
            method: "POST",
            data: {
                action: 'add_product_data',
                name: name, category: cat, supplier: sup, buying: buy, selling: sell
            },
            success: function(response){
                if(response.trim() == 'success') {
                    alert("Product Added Successfully!");
                    $('#addProductModal').modal('hide');
                    $('#add_product_form')[0].reset();
                    $('#profit_display').val('0.00'); // Reset profit display
                    loadProducts('all');
                } else {
                    alert("Error: " + response);
                }
            }
        });
    });

    // --- 5. Add Category ---
    $('#btn_add_cat').click(function(){
        var name = $('#new_cat_name').val();
        if(name != '') {
            $.ajax({
                url: "action.php", method: "POST",
                data: {action: 'add_category', name: name},
                success: function(){ location.reload(); }
            });
        }
    });

    // --- 6. Delete Category (Safety Check) ---
    $(document).on('click', '.del_cat', function(){
        var id = $(this).data('id');
        if(confirm("Are you sure? Note: You cannot delete categories that have products.")) {
            $.ajax({
                url: "action.php", method: "POST",
                data: {action: 'delete_category', id: id},
                success: function(res){
                    if(res.trim() == 'success') { location.reload(); } else { alert(res); }
                }
            });
        }
    });

    // --- 7. Add Supplier ---
    $('#btn_add_sup').click(function(){
        var name = $('#new_sup_name').val();
        if(name != '') {
            $.ajax({
                url: "action.php", method: "POST",
                data: {action: 'add_supplier', name: name},
                success: function(){ location.reload(); }
            });
        }
    });

    // --- 8. Delete Supplier ---
    $(document).on('click', '.del_sup', function(){
        var id = $(this).data('id');
        if(confirm("Are you sure?")) {
            $.ajax({
                url: "action.php", method: "POST",
                data: {action: 'delete_supplier', id: id},
                success: function(res){
                    if(res.trim() == 'success') { location.reload(); } else { alert(res); }
                }
            });
        }
    });

});
</script>

</body>
</html>