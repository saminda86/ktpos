<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Invoice | Kawdu Technology</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; padding: 30px; }

        /* Card Style */
        .invoice-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 30px;
            border-top: 5px solid #007bff;
        }

        h3 { font-weight: 700; color: #333; margin-bottom: 25px; }

        /* --- PROFESSIONAL SEARCH DROPDOWN STYLES --- */
        .ui-autocomplete {
            max-height: 400px;
            overflow-y: auto; 
            overflow-x: hidden;
            font-family: 'Inter', sans-serif !important;
            border: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 0;
            z-index: 9999;
        }

        .ui-menu-item { border-bottom: 1px solid #f0f0f0; margin: 0; }
        
        /* Hover State */
        .ui-state-active, .ui-widget-content .ui-state-active {
            background: #eef6ff !important; border: none !important; color: inherit !important;
        }

        /* Search Row Layout */
        .search-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; cursor: pointer; }
        .info-col { display: flex; flex-direction: column; gap: 3px; }
        
        .s-name { font-size: 13px; font-weight: 700; color: #2c3e50; }
        .s-supplier { font-size: 11px; color: #7f8c8d; }
        .s-qty { 
            font-size: 10px; background-color: #17a2b8; color: #fff; 
            padding: 2px 8px; border-radius: 4px; width: fit-content; font-weight: 600; 
        }

        .price-col { text-align: right; border-left: 1px solid #eee; padding-left: 15px; margin-left: 10px; }
        .s-price { font-size: 14px; font-weight: 700; color: #27ae60; white-space: nowrap; }

        /* Table Styling */
        .table thead th {
            background-color: #f8f9fa; border-top: none; font-size: 13px; font-weight: 600; color: #555;
        }
        .table tbody td { vertical-align: middle; font-size: 14px; }
        
        /* Remove Button */
        .remove-btn { color: #e74c3c; cursor: pointer; font-size: 18px; transition: 0.2s; }
        .remove-btn:hover { color: #c0392b; transform: scale(1.1); }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-card">
        <h3>Create New Invoice</h3>
        
        <form action="save_invoice.php" method="POST" id="invoiceForm">
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control" placeholder="පාරිභෝගිකයාගේ නම" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="invoice_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Invoice No</label>
                        <input type="text" name="invoice_no" class="form-control" value="INV-<?php echo time(); ?>" readonly style="background:#eee;">
                    </div>
                </div>
            </div>

            <hr>

            <div class="form-group" style="position: relative;">
                <label style="font-weight: 600; color: #007bff;">Search Product</label>
                <input type="text" id="product_search" class="form-control form-control-lg" placeholder="භාණ්ඩයේ නම ටයිප් කරන්න..." autocomplete="off">
                <small class="text-muted">Tip: Type product name to search & add instantly.</small>
            </div>

            <table class="table table-hover table-bordered mt-3">
                <thead>
                    <tr>
                        <th width="40%">Product Name</th>
                        <th width="15%" class="text-right">Price (Rs)</th>
                        <th width="15%" class="text-center">Qty</th>
                        <th width="20%" class="text-right">Total (Rs)</th>
                        <th width="10%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="invoiceItems">
                    </tbody>
            </table>

            <div class="row justify-content-end mt-4">
                <div class="col-md-5">
                    <table class="table table-bordered">
                        <tr style="background: #f1f1f1;">
                            <td style="font-weight: 700; font-size: 16px;">Grand Total:</td>
                            <td class="text-right" style="font-size: 18px; font-weight: 800; color: #2c3e50;">
                                Rs. <span id="grand_total">0.00</span>
                                <input type="hidden" name="final_amount" id="final_amount_input">
                            </td>
                        </tr>
                    </table>
                    
                    <div class="text-right">
                        <button type="submit" class="btn btn-success btn-lg px-5 font-weight-bold">
                            Save Invoice <i class="fa fa-check"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
        </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {

    // --- 1. SWEETALERT TOAST SETUP (Sinhala) ---
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // --- 2. PROFESSIONAL AUTOCOMPLETE ---
    $("#product_search").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "fetch_products.php", // මෙය Backend file path එකයි
                type: "POST",
                dataType: "json",
                data: { term: request.term },
                success: function(data) {
                    if (data.length === 0) {
                        response([{ label: 'No product found', value: '', no_result: true }]);
                    } else {
                        response(data);
                    }
                }
            });
        },
        minLength: 1,
        autoFocus: true, // ඉබේම Select වීම

        select: function(event, ui) {
            if (ui.item.no_result) { 
                $(this).val(''); 
                return false; 
            }

            // භාණ්ඩය Table එකට එකතු කිරීම
            addProductToTable(ui.item);
            
            $(this).val(''); // Input එක clear කිරීම
            return false;
        }
    })
    // Custom Render (ඔබේ Design එක)
    .autocomplete("instance")._renderItem = function(ul, item) {
        if(item.no_result){
            return $("<li>").append("<div style='padding:10px; color:red; font-size:12px;'>No product found!</div>").appendTo(ul);
        }
        return $("<li>")
            .append(`
                <div class="search-row">
                    <div class="info-col">
                        <span class="s-name">${item.value}</span>
                        <span class="s-supplier">Supplier: ${item.supplier}</span>
                        <span class="s-qty">Stock: ${item.qty}</span>
                    </div>
                    <div class="price-col">
                        Rs. <span class="s-price">${item.price}</span>
                    </div>
                </div>
            `)
            .appendTo(ul);
    };

    // --- 3. TABLE LOGIC ---
    function addProductToTable(item) {
        // Price එකෙන් "," ඉවත් කර අංකයක් කරගැනීම
        let priceValue = parseFloat(item.price.replace(/,/g, ''));
        
        let row = `
            <tr>
                <td>
                    <strong>${item.value}</strong>
                    <input type="hidden" name="product_id[]" value="${item.id}">
                    <input type="hidden" name="product_name[]" value="${item.value}">
                </td>
                <td class="text-right">
                    ${item.price}
                    <input type="hidden" class="price-input" name="price[]" value="${priceValue}">
                </td>
                <td>
                    <input type="number" name="qty[]" class="form-control form-control-sm qty-input text-center" value="1" min="1">
                </td>
                <td class="text-right">
                    <span class="row-total">${priceValue.toFixed(2)}</span>
                </td>
                <td class="text-center">
                    <span class="remove-btn" title="Remove">&times;</span>
                </td>
            </tr>
        `;
        
        $("#invoiceItems").append(row);
        calculateGrandTotal();
        
        // Product එකක් දැමූ විට Alert එකක් (Optional)
        // Toast.fire({ icon: 'success', title: 'භාණ්ඩය එකතු කරන ලදී' });
    }

    // --- 4. DYNAMIC CALCULATIONS ---
    $(document).on('input', '.qty-input', function() {
        let row = $(this).closest('tr');
        let price = parseFloat(row.find('.price-input').val());
        let qty = parseFloat($(this).val());
        
        if(isNaN(qty) || qty < 1) qty = 0;

        let total = price * qty;
        row.find('.row-total').text(total.toFixed(2));
        
        calculateGrandTotal();
    });

    $(document).on('click', '.remove-btn', function() {
        $(this).closest('tr').remove();
        calculateGrandTotal();
    });

    function calculateGrandTotal() {
        let grandTotal = 0;
        $('.row-total').each(function() {
            grandTotal += parseFloat($(this).text());
        });
        
        $('#grand_total').text(grandTotal.toFixed(2));
        $('#final_amount_input').val(grandTotal.toFixed(2));
    }

    // --- 5. FORM SUBMISSION & SINHALA ALERTS ---
    $("#invoiceForm").on("submit", function(e) {
        e.preventDefault();

        // A. නම Validation
        var customerName = $("input[name='customer_name']").val();
        if (customerName.trim() == "") {
            Swal.fire({
                icon: 'warning',
                title: 'අවධානයට!',
                text: 'කරුණාකර පාරිභෝගිකයාගේ නම ඇතුලත් කරන්න.',
                confirmButtonColor: '#f39c12',
                confirmButtonText: 'හරි'
            });
            return false;
        }

        // B. භාණ්ඩ Validation
        if ($("#invoiceItems tr").length == 0) {
            Swal.fire({
                icon: 'error',
                title: 'භාණ්ඩ ඇතුලත් කර නැත!',
                text: 'කරුණාකර ඉන්වොයිසියට භාණ්ඩ (Products) එකතු කරන්න.',
                confirmButtonColor: '#d33',
                confirmButtonText: 'හරි මම දාන්නම්'
            });
            return false;
        }

        // C. සාර්ථක නම් (AJAX Submit Simulation)
        $.ajax({
            url: $(this).attr('action'),
            type: "POST",
            data: $(this).serialize(),
            success: function(response) {
                // Success Message (Sinhala)
                Toast.fire({
                    icon: 'success',
                    title: 'ඉන්වොයිසිය සාර්ථකව සකසන ලදී!'
                });

                // තත්පර 2කට පසු පිටුව reload කිරීම
                setTimeout(function() {
                    location.reload(); 
                }, 2000);
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'අසාර්ථකයි!',
                    text: 'තාක්ෂණික දෝෂයක් මතු විය.',
                });
            }
        });
    });

});
</script>

</body>
</html>