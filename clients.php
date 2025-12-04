<?php
// File Name: clients.php (Final Working Version with Admin Delete Limit)

// Session ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// පරිශීලකයා ලොග් වී ඇත්දැයි පරීක්ෂා කිරීම
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Clients පිටුවේ මාතෘකාව header.php වෙත යැවීම
$page_title = 'Clients';

// header.php ඇතුළත් කිරීම
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
// Client Delete Logic (🛑 Administrator සීමා කිරීම)
// -----------------------------------------------------------
if (isset($_GET['delete_id'])) {
    
    // 🛑🛑🛑 ආරක්ෂක පරීක්ෂාව: Admin ('Admin' User Type) පමණක් Delete කළ හැක 🛑🛑🛑
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
        $_SESSION['error_message'] = "Delete කිරීමට අවසර ඇත්තේ පරිපාලක (Administrator) හට පමණි.";
        header('Location: clients.php');
        exit();
    }

    $client_id = intval($_GET['delete_id']);
    
    $sql = "DELETE FROM clients WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    
    if ($stmt->execute()) {
         $_SESSION['success_message'] = "Client ID {$client_id} successfully deleted!";
    } else {
        $_SESSION['error_message'] = "Error deleting client: " . $conn->error;
    }
    $stmt->close();
    header('Location: clients.php');
    exit();
}

// -----------------------------------------------------------
// Fetch Client Data (with Search and Pagination)
// -----------------------------------------------------------
$client_data = [];
$total_records = 0;
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " WHERE client_name LIKE '%{$search_query}%' OR phone LIKE '%{$search_query}%'";
}

$total_result = $conn->query("SELECT COUNT(client_id) AS count FROM clients" . $search_condition);
$total_records = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

$sql = "SELECT client_id, client_name, phone, email, address, whatsapp, rating FROM clients" . $search_condition . " ORDER BY client_id DESC LIMIT {$start}, {$limit}";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $client_data[] = $row;
    }
}
$conn->close();

function get_alert_message($name) {
    $name_html = '<b>' . htmlspecialchars($name) . '</b>';
    $message = "ඔබට {$name_html} ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.";
    return json_encode($message);
}

$client_data_json = json_encode($client_data); 
?>

<style>
    /* 🛑 Search Suggestions Styling 🛑 */
    #clientSuggestions {
        border: 1px solid #ced4da !important; 
        border-radius: 0.25rem !important; 
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15) !important; 
        background-color: #ffffff !important; 
        max-height: 250px; 
        overflow-y: auto; 
    }

    #clientSuggestions .list-group-item {
        color: #212529 !important; /* තද කළු/අළු පැහැති අකුරු */
        font-size: 0.95rem !important; 
        padding: 10px 15px !important;
        border-left: none !important;
        border-right: none !important;
        border-top: 1px solid #f0f0f0 !important; 
        border-color: #e9ecef !important;
        line-height: 1.2;
    }
    
    /* 🛑🛑 Hover effect (මූසිකය උඩින් ගෙන යන විට පසුබිම වෙනස් කිරීම) 🛑🛑 */
    #clientSuggestions .list-group-item:hover,
    #clientSuggestions .list-group-item:focus {
        background-color: #e9ecef !important; /* ඉතා ලා අළු */
        color: #000000 !important; 
        cursor: pointer;
    }
    #clientSuggestions .list-group-item strong {
        font-weight: 700 !important; /* අකුරු ඉතා තද කරයි */
    }
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

    <h1 class="mb-4 text-primary"><i class="fas fa-users"></i> Client Management</h1>
    <hr>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">All Registered Clients (Total: <?php echo $total_records; ?>)</h6>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal" onclick="prepareAddModal()">
                <i class="fas fa-plus"></i> Add New Client
            </button>
        </div>
        <div class="card-body">
            
            <div class="row mb-3">
                <div class="col-md-7">
                    <form action="clients.php" method="GET" class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search by Name or Phone" 
                               name="search" 
                               id="clientSearchInput"
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               autocomplete="off">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                        
                        <?php if (!empty($search_query)): ?>
                            <a href="clients.php" class="btn btn-secondary" title="Clear Search">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                    <div id="clientSuggestions" class="list-group position-absolute mt-1" style="z-index: 1000; width: 35%;">
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="clientsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Rating</th> 
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($client_data)): ?>
                            <?php foreach ($client_data as $client): 
                                $js_edit_call = "loadClientForEdit({$client['client_id']})"; 
                                // 🛑 Delete බොත්තම පෙන්වීම සඳහා කොන්දේසිය
                                $show_delete_button = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['client_id']); ?></td>
                                <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td><?php echo htmlspecialchars($client['address']); ?></td>
                                
                                <td>
                                    <?php 
                                        $rating = $client['rating'] ?? 0;
                                        $stars = '';
                                        for ($i = 1; $i <= 5; $i++) {
                                            $stars .= ($i <= $rating) ? '⭐' : '☆';
                                        }
                                        echo $stars;
                                    ?>
                                </td>
                                
                                <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="<?php echo $js_edit_call; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if ($show_delete_button): ?>
                                    <button class="btn btn-sm btn-danger" onclick="confirmAndDelete(<?php echo $client['client_id']; ?>, '<?php echo htmlspecialchars($client['client_name'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No clients found <?php echo !empty($search_query) ? "matching '{$search_query}'" : ""; ?>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">Showing <?php echo min($limit, count($client_data)); ?> of <?php echo $total_records; ?> records.</div>
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="clients.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                            </li>
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="clients.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; 
                            
                            if ($end_page < $total_pages) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                            ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="clients.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
            
        </div>
    </div>

<div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"> 
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title" id="addClientModalLabel"><i class="fas fa-user-plus"></i> Add New Client Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        
        <form id="clientInsertForm">
            <div id="client_id_display" class="row mb-3" style="display: none;">
                <div class="col-md-12">
                    <label for="client_id_modal_display" class="form-label text-muted">Client ID:</label>
                    <input type="text" class="form-control text-muted" id="client_id_modal_display" readonly>
                    <input type="hidden" name="client_id" id="client_id_modal" value=""> 
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="client_name_modal" class="form-label">Client Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="client_name_modal" name="client_name" required>
                </div>
                <div class="col-md-6">
                    <label for="phone_modal" class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="phone_modal" name="phone" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="whatsapp_modal" class="form-label">WhatsApp Number</label>
                    <input type="tel" class="form-control" id="whatsapp_modal" name="whatsapp">
                </div>
                <div class="col-md-6">
                    <label for="email_modal" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email_modal" name="email">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="client_rating_modal" class="form-label">Client Rating</label>
                    <select class="form-select" id="client_rating_modal" name="rating" required>
                        <option value="5">5 ⭐ (Excellent)</option>
                        <option value="4">4 ⭐ (Very Good)</option>
                        <option value="3">3 ⭐ (Average)</option>
                        <option value="2">2 ⭐ (Poor)</option>
                        <option value="1">1 ⭐ (Bad)</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="address_modal" class="form-label">Address</label>
                    <textarea class="form-control" id="address_modal" name="address" rows="2"></textarea>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between mt-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveClientButton"><i class="fas fa-save"></i> Save Client</button>
            </div>
        </form>
      </div>
      
    </div>
  </div>
</div>

<?php 
require_once 'footer.php';
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    
    // PHP Client Data to JavaScript variable
    const allClientsData = <?php echo json_encode($client_data ?: []); ?>;
    
    // -----------------------------------------------------------
    // 1. Edit/Load Client Data Logic
    // -----------------------------------------------------------
    window.loadClientForEdit = function(clientId) {
        const client = allClientsData.find(c => c.client_id == clientId);
        if (!client) {
            Swal.fire('දෝෂයක්!', 'Client දත්ත සොයාගත නොහැක.', 'error');
            return;
        }

        // Set Modal Title and Button Text
        $('#addClientModalLabel').html('<i class="fas fa-edit"></i> Edit Client Details');
        $('#saveClientButton').text('Update Client');
        
        // Client ID Display Logic (Edit Mode)
        $('#client_id_display').show(); 
        $('#client_id_modal_display').val(client.client_id); 
        $('#client_id_modal').val(client.client_id); 

        // Populate Form Fields
        $('#client_name_modal').val(client.client_name);
        $('#phone_modal').val(client.phone);
        $('#email_modal').val(client.email);
        $('#address_modal').val(client.address);
        $('#whatsapp_modal').val(client.whatsapp || ''); 
        $('#client_rating_modal').val(client.rating || 5); 
        
        // Show Modal
        $('#addClientModal').modal('show');
    };

    // Prepare modal for adding new client (resetting fields)
    window.prepareAddModal = function() {
        $('#clientInsertForm')[0].reset();
        
        // Client ID Display Logic (Add Mode)
        $('#client_id_modal').val(''); 
        $('#client_id_display').hide(); 
        
        // Reset Modal Title and set default rating
        $('#addClientModalLabel').html('<i class="fas fa-user-plus"></i> Add New Client Details');
        $('#saveClientButton').text('Save Client');
        $('#client_rating_modal').val(5); // Default to 5 stars for new client
    };
    
    // -----------------------------------------------------------
    // 2. AJAX Submission Handler (Handles both ADD and EDIT)
    // -----------------------------------------------------------
    $('#clientInsertForm').on('submit', function(e) {
        e.preventDefault(); 
        
        const isEditing = $('#client_id_modal').val() !== '';
        const targetUrl = isEditing ? 'client_update_process.php' : 'client_insert_process.php';

        $.ajax({
            url: targetUrl, 
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json', 
            
            success: function(response) {
                Swal.fire({
                    title: response.title || 'දැනුම්දීම!',
                    text: response.message || 'කිසියම් දෝශයක් ඇත.',
                    icon: response.icon || 'info'
                }).then((result) => {
                    if (response.status === 'success') {
                        // Refresh the page to show the update/new entry
                        $('#addClientModal').modal('hide');
                        window.location.reload(); 
                    }
                });
            },
            
            error: function(xhr, status, error) {
                // Generic error handling
                Swal.fire({
                    title: 'දත්ත යැවීමේ දෝෂයක්!',
                    text: 'කිසියම් දෝශයක් ඇත. Server සම්බන්ධතාවය පරීක්ෂා කරන්න.',
                    icon: 'error'
                });
            }
        });
    });

    // -----------------------------------------------------------
    // 3. AJAX Sweet Alert Delete Confirmation
    // -----------------------------------------------------------
    window.confirmAndDelete = function(clientId, clientName) {
        const is_admin = <?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') ? 'true' : 'false'; ?>;
        
        if (!is_admin) {
            Swal.fire('අවසර නැත!', 'Delete කිරීමට අවසර ඇත්තේ පරිපාලක හට පමණි.', 'warning');
            return;
        }

        Swal.fire({
            title: 'ඔබට විශ්වාසද?',
            html: `ඔබට Client **${clientName}** (ID: ${clientId}) ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ඔව්, ඉවත් කරන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `clients.php?delete_id=${clientId}`;
            }
        });
    };

    // -----------------------------------------------------------
    // 4. Live Search Suggestion Logic
    // -----------------------------------------------------------
    const searchInput = document.getElementById('clientSearchInput');
    const suggestionsDiv = document.getElementById('clientSuggestions');

    searchInput.addEventListener('input', function() {
        const query = this.value;
        if (query.length < 2) {
            suggestionsDiv.style.display = 'none';
            suggestionsDiv.innerHTML = '';
            return;
        }

        const filteredSuggestions = allClientsData
            .filter(client => 
                client.client_name.toLowerCase().includes(query.toLowerCase()) || 
                client.phone.includes(query)
            )
            .slice(0, 5); // Max 5 suggestions

        if (filteredSuggestions.length > 0) {
            let html = '';
            filteredSuggestions.forEach(client => {
                html += `<a href="clients.php?search=${encodeURIComponent(client.client_name)}" class="list-group-item list-group-item-action">
                            <strong>${client.client_name}</strong> - ${client.phone}
                        </a>`;
            });
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = 'block';
        } else {
            suggestionsDiv.style.display = 'none';
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !suggestionsDiv.contains(event.target)) {
            suggestionsDiv.style.display = 'none';
        }
    });

    // Optional: Auto-focus the first input field when the modal opens
    $('#addClientModal').on('shown.bs.modal', function () {
        $('#client_name_modal').focus();
    });

});
</script>