<?php
// File Name: ktpos/services.php (Final Version: Services Management Module)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = 'Service Management';
require_once 'header.php';
require_once 'db_connect.php'; 

// -----------------------------------------------------------
// CONFIGURATION AND SETUP
// -----------------------------------------------------------
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Pagination Variables (Similar to products.php)
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';


// -----------------------------------------------------------
// Service Fetch Logic (with Search and Pagination)
// -----------------------------------------------------------
$service_data = [];
$total_records = 0;
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " WHERE service_name LIKE '%{$search_query}%' OR service_code LIKE '%{$search_query}%'";
}

// 1. Total Count
$total_result = $conn->query("SELECT COUNT(service_id) AS count FROM services" . $search_condition);
$total_records = $total_result->fetch_assoc()['count'] ?? 0; 
$total_pages = ceil($total_records / $limit);

// 2. Data Fetch
$sql = "SELECT s.*, c.category_name 
        FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id"
        . $search_condition . " ORDER BY s.service_id DESC LIMIT {$start}, {$limit}";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $service_data[] = $row;
    }
}

// 3. Fetch Categories for the Service Modal dropdowns
$categories = [];
$cat_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
if ($cat_result) {
    while($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$conn->close();

$service_data_json = json_encode($service_data); 
$categories_json = json_encode($categories);
?>

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

    <h1 class="mb-4 text-primary"><i class="fas fa-tools"></i> Service Management</h1>
    <hr>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="m-0 font-weight-bold text-primary">All System Services (Total: <?php echo $total_records; ?>)</h6>
            
            <div class="btn-group mt-2 mt-md-0" role="group">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="prepareAddServiceModal()">
                    <i class="fas fa-plus-circle"></i> Add New Service
                </button>
                
                <button class="btn btn-info text-white" onclick="openServiceQuickAddModal('category')">
                    <i class="fas fa-tags"></i> Category
                </button>
            </div>
        </div>
        
        <div class="card-body">
            
            <div class="row mb-3">
                <div class="col-md-7">
                    <form action="services.php" method="GET" class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search by Name or Code" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               autocomplete="off">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                        
                        <?php if (!empty($search_query)): ?>
                            <a href="services.php" class="btn btn-secondary" title="Clear Search">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="servicesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Service Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Sell Price (රු.)</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($service_data)): ?>
                            <?php foreach ($service_data as $index => $service): ?>
                            <tr>
                                <td><?php echo $start + $index + 1; ?></td> 
                                <td><?php echo htmlspecialchars($service['service_code']); ?></td>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($service['category_name'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($service['sell_price'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($service['description'] ?? '-'); ?></td>
                                
                                <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="loadServiceForEdit(<?php echo $service['service_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if ($_SESSION['user_type'] === 'Admin'): // Admin Only Delete ?>
                                    <button class="btn btn-sm btn-danger" onclick="confirmDeleteService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No services found <?php echo !empty($search_query) ? "matching '{$search_query}'" : ""; ?>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">Showing <?php echo min($limit, count($service_data)); ?> of <?php echo $total_records; ?> records.</div>
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="services.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                            </li>
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="services.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; 
                            
                            if ($end_page < $total_pages) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                            ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="services.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
            
        </div>
    </div>


<div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"> 
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title" id="serviceModalLabel"><i class="fas fa-tools"></i> Add New Service Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        
        <form id="serviceInsertUpdateForm">
            <input type="hidden" name="service_id" id="service_id_modal" value="">
            
            <div class="row">
                <div class="col-md-7 border-end pe-4">
                    <h6 class="text-primary mb-3"><i class="fas fa-info-circle"></i> Basic Service Details</h6>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="service_name_modal" class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="service_name_modal" name="service_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="service_code_display" class="form-label text-muted">Service Code</label>
                            <input type="text" class="form-control text-muted" id="service_code_display" readonly value="(Auto Generate)">
                            <input type="hidden" name="service_code" id="service_code_modal" value=""> 
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description_modal" class="form-label">Description / Notes</label>
                            <textarea class="form-control" id="description_modal" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <h6 class="text-primary mb-3"><i class="fas fa-dollar-sign"></i> Pricing & Classification</h6>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label for="sell_price_modal" class="form-label">Sell Price (රු.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="sell_price_modal" name="sell_price" required value="0.00">
                        </div>
                    </div>
                    
                    <h6 class="text-primary my-3"><i class="fas fa-link"></i> Classification</h6>

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
                            <button type="button" class="btn btn-info btn-sm w-100 text-white" onclick="openServiceQuickAddModal('category')">
                                <i class="fas fa-plus-square"></i> Quick Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between mt-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveServiceButton"><i class="fas fa-save"></i> Save Service</button>
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
    
    const allServicesData = <?php echo $service_data_json ?: '[]'; ?>;
    let categoriesList = <?php echo $categories_json ?: '[]'; ?>;
    const is_admin = <?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') ? 'true' : 'false'; ?>;
    
    const serviceModal = new bootstrap.Modal(document.getElementById('serviceModal'), { backdrop: 'static', keyboard: false });
    const categoryQuickAddModal = new bootstrap.Modal(document.getElementById('categoryQuickAddModal'), { backdrop: false });
    
    // -----------------------------------------------------------
    // Helper Functions (Category Management)
    // -----------------------------------------------------------
    function refreshCategoryDropdowns(data, selectId = null) {
        // Targets the category dropdown ONLY within the service modal
        const dropdown = $('#serviceModal #category_id_modal'); 
        dropdown.empty(); 
        
        dropdown.append($('<option>', {
            value: '',
            text: '-- Select Category --'
        }));
        
        data.forEach(item => {
            const id = item.category_id;
            const name = item.category_name;
            dropdown.append($('<option>', {
                value: id,
                text: name
            }));
        });
        if (selectId) {
            dropdown.val(selectId);
        }
    }
    
    function fetchAndRefreshCategoryData(selectId = null) {
        $.ajax({
            url: 'category_process.php',
            type: 'POST',
            data: { action: 'fetch' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    categoriesList = response.data; 
                    refreshCategoryDropdowns(response.data, selectId);
                    // Update quick add list if open (requires global function from products.php)
                    if (typeof updateQuickAddList === 'function' && $('#categoryQuickAddModal').hasClass('show')) {
                        updateQuickAddList('category', response.data);
                    }
                }
            },
            error: function() {
                // Silent error
            }
        });
    }

    fetchAndRefreshCategoryData();

    // -----------------------------------------------------------
    // Service Modal Functions
    // -----------------------------------------------------------
    window.prepareAddServiceModal = function() {
        $('#serviceInsertUpdateForm')[0].reset();
        $('#service_id_modal').val('');
        $('#serviceModalLabel').html('<i class="fas fa-tools"></i> Add New Service Details');
        $('#saveServiceButton').text('Save Service');
        
        $('#service_code_display').val('(Auto Generate)').removeClass('text-muted').addClass('text-danger');
        
        $('#serviceModal #category_id_modal').val(''); 
        fetchAndRefreshCategoryData();
    };
    
    window.loadServiceForEdit = function(serviceId) {
        const service = allServicesData.find(s => s.service_id == serviceId);
        if (!service) {
            Swal.fire('දෝෂයක්!', 'Service දත්ත සොයාගත නොහැක.', 'error');
            return;
        }
        
        $('#serviceModalLabel').html('<i class="fas fa-edit"></i> Edit Service Details (ID: ' + serviceId + ')');
        $('#saveServiceButton').text('Update Service');
        
        $('#service_id_modal').val(service.service_id);
        $('#service_name_modal').val(service.service_name);
        $('#description_modal').val(service.description);
        $('#sell_price_modal').val(parseFloat(service.sell_price).toFixed(2));
        
        $('#service_code_display').val(service.service_code).removeClass('text-danger').addClass('text-muted');

        fetchAndRefreshCategoryData(service.category_id);
        
        serviceModal.show();
    };
    
    // -----------------------------------------------------------
    // Quick-Add Modal Logic (Opens Category Modal)
    // -----------------------------------------------------------
    window.openServiceQuickAddModal = function(type) {
        // This function calls the *global* quick-add function (defined in products.php script)
        if (typeof openQuickAddModal === 'function') {
            openQuickAddModal('category'); 
        } else {
             Swal.fire('දෝෂයක්!', 'Shared Quick Add Logic සොයාගත නොහැක. products.php පිටුවේ ඇති JavaScript functions නිවැරදිව ඇතුළත් කරන්න.', 'error');
        }
    };
    

    // -----------------------------------------------------------
    // Service Form Submission & Delete Logic
    // -----------------------------------------------------------
    $('#serviceInsertUpdateForm').on('submit', function(e) {
        e.preventDefault(); 
        
        if ($('#serviceModal #category_id_modal').val() === '') {
            Swal.fire('අවශ්‍ය දත්ත!', 'කරුණාකර Category එකක් තෝරන්න.', 'warning');
            return;
        }
        
        const isEditing = $('#service_id_modal').val() !== '';
        const formData = $(this).serialize() + '&action=' + (isEditing ? 'update' : 'insert');

        $.ajax({
            url: 'service_process.php', 
            type: 'POST',
            data: formData,
            dataType: 'json', 
            success: function(response) {
                Swal.fire({ title: response.title || 'දැනුම්දීම!', text: response.message || 'කිසියම් දෝශයක් ඇත.', icon: response.icon || 'info' }).then(() => {
                    if (response.status === 'success') {
                        serviceModal.hide();
                        window.location.reload(); 
                    }
                });
            },
            error: function() { Swal.fire({ title: 'තාක්ෂණික දෝෂයක්!', text: 'දත්ත යැවීමේදී ගැටලුවක් සිදුවිය. Server සම්බන්ධතාවය පරීක්ෂා කරන්න.', icon: 'error' }); }
        });
    });
    
    window.confirmDeleteService = function(serviceId, serviceName) {
        if (!is_admin) {
            Swal.fire('අවසර නැත!', 'Delete කිරීමට අවසර ඇත්තේ පරිපාලක හට පමණි.', 'warning');
            return;
        }
        Swal.fire({
            title: 'ඔබට විශ්වාසද?',
            html: `ඔබට Service **${serviceName}** (ID: ${serviceId}) ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.<br><p class='small text-danger mt-2'>මෙම ක්‍රියාව ආපසු හැරවිය නොහැක.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ඔව්, ඉවත් කරන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'service_process.php', 
                    type: 'POST',
                    data: { action: 'delete', service_id: serviceId },
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