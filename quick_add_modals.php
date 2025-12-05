<style>
    .modal-backdrop.show { opacity: 0.85 !important; background-color: #000 !important; }
    .modal-content { box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important; border: none; }
</style>

<div class="modal fade" id="categoryQuickAddModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-tags"></i> Manage Categories</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeQuickAddModal('category')"></button>
            </div>
            <div class="modal-body">
                <form id="categoryQuickAddForm" class="mb-3">
                    <input type="hidden" name="category_id" id="category_id_quick">
                    <div class="input-group">
                        <input type="text" class="form-control" name="category_name" id="category_name_quick" placeholder="Enter Category Name" required>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
                <div class="mb-2"><input type="text" class="form-control form-control-sm" id="category_search_input" placeholder="Search..."></div>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-bordered mb-0"><tbody id="category_table_body"></tbody></table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierQuickAddModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-truck"></i> Manage Suppliers</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeQuickAddModal('supplier')"></button>
            </div>
            <div class="modal-body">
                <form id="supplierQuickAddForm" class="mb-3">
                    <input type="hidden" name="supplier_id" id="supplier_id_quick">
                    
                    <div class="mb-2">
                        <input type="text" class="form-control" name="supplier_name" id="supplier_name_quick" placeholder="Supplier Name" required>
                    </div>

                    <div class="input-group">
                        <input type="text" class="form-control" name="contact_no" id="supplier_contact_quick" placeholder="Contact Number (Unique)" required>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>

                <hr>

                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="supplier_search_input" placeholder="Search Suppliers...">
                </div>

                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-bordered mb-0"><tbody id="supplier_table_body"></tbody></table>
                </div>
            </div>
        </div>
    </div>
</div>