<?php
// File Name: ktpos/quick_add_modals.php (Shared Modals for Products and Services - Final UI/Filter)

$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin';
?>

<div class="modal fade" id="categoryQuickAddModal" tabindex="-1" aria-labelledby="categoryQuickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"> 
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryQuickAddModalLabel"><i class="fas fa-tags"></i> Add/Edit Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeQuickAddModal('category')"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 border-end pe-4">
                        <form id="categoryQuickAddForm">
                            <input type="hidden" name="category_id" id="category_id_quick" value="">
                            <h6 class="text-secondary mb-3" id="categoryFormTitle">Add New Category</h6>
                            <div class="mb-3">
                                <label for="category_name_quick" class="form-label">Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="category_name_quick" name="category_name" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-info text-white" id="saveCategoryButton"><i class="fas fa-plus"></i> Add New Category</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="cancelCategoryEdit" style="display:none;" onclick="prepareQuickAddModal('category')">
                                Cancel Edit / Add New
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-7">
                        <h6 class="text-primary mb-3"><i class="fas fa-list"></i> Existing Categories:</h6>
                        
                        <div class="input-group mb-2">
                             <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="categoryFilterInput" placeholder="Filter Categories...">
                        </div>

                        <div id="categoryListContainer" class="list-group list-group-flush shadow-sm" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="supplierQuickAddModal" tabindex="-1" aria-labelledby="supplierQuickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"> 
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierQuickAddModalLabel"><i class="fas fa-truck"></i> Add/Edit Suppliers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeQuickAddModal('supplier')"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 border-end pe-4">
                        <form id="supplierQuickAddForm">
                            <input type="hidden" name="supplier_id" id="supplier_id_quick" value="">
                            <h6 class="text-secondary mb-3" id="supplierFormTitle">Add New Supplier</h6>
                            <div class="mb-3">
                                <label for="supplier_name_quick" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="supplier_name_quick" name="supplier_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_no_quick" class="form-label">Contact No <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_no_quick" name="contact_no" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address_quick" class="form-label">Address (Optional)</label>
                                <textarea class="form-control" id="address_quick" name="address" rows="2"></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning" id="saveSupplierButton"><i class="fas fa-plus"></i> Add New Supplier</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="cancelSupplierEdit" style="display:none;" onclick="prepareQuickAddModal('supplier')">
                                Cancel Edit / Add New
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-7">
                        <h6 class="text-primary mb-3"><i class="fas fa-list"></i> Existing Suppliers:</h6>
                        
                        <div class="input-group mb-2">
                             <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="supplierFilterInput" placeholder="Filter Suppliers...">
                        </div>

                        <div id="supplierListContainer" class="list-group list-group-flush shadow-sm" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>