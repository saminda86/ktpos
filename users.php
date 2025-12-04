<?php
// File Name: users.php (User Management Module)

// Session ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🛑🛑🛑 ආරක්ෂක පරීක්ෂාව: Admin පමණක් මෙම පිටුවට ඇතුළු විය හැක 🛑🛑🛑
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    $_SESSION['error_message'] = "පරිශීලක කළමනාකරණයට ප්‍රවේශ වීමට අවසර ඇත්තේ පරිපාලක (Admin) හට පමණි.";
    header('Location: dashboard.php'); // Admin නොවේ නම් Dashboard වෙත යොමු කරයි
    exit();
}

$page_title = 'User Management';
require_once 'header.php'; 
require_once 'db_connect.php'; 

// -----------------------------------------------------------
// SETUP AND DATABASE CONNECTION
// -----------------------------------------------------------
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// -----------------------------------------------------------
// User Delete Logic (URL හරහා Delete ඉල්ලීම් හසුරුවයි)
// -----------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $user_id = intval($_GET['delete_id']);
    
    // 🛑 තමන්වම Delete කිරීම වැලැක්වීම
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "⚠️ ඔබට ඔබගේම ගිණුම මකා දැමිය නොහැක.";
    } else {
        try {
            $sql = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $_SESSION['success_message'] = "User ID {$user_id} successfully deleted!";
        } catch (Exception $e) {
            // Foreign Key දෝෂය
            $_SESSION['error_message'] = "⚠️ මකා දැමීම අසාර්ථකයි: මෙම පරිශීලකයාට පද්ධතියේ වෙනත් දත්ත (Invoices වැනි) සම්බන්ධ වී ඇත.";
        }
        $stmt->close();
    }
    header('Location: users.php');
    exit();
}

// -----------------------------------------------------------
// Fetch User Data
// -----------------------------------------------------------
$user_data = [];
$sql = "SELECT user_id, username, password, name, user_type, status, created_at FROM users ORDER BY user_id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $user_data[] = $row;
    }
}
$conn->close();

// JavaScript වෙත යැවීම සඳහා JSON encoding
$user_data_json = json_encode($user_data);
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

    <h1 class="mb-4 text-primary"><i class="fas fa-user-shield"></i> User Management</h1>
    <hr>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">All System Users (Total: <?php echo count($user_data); ?>)</h6>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareAddModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
        <div class="card-body">
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="usersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($user_data)): ?>
                            <?php foreach ($user_data as $user): 
                                $is_current_user = ($user['user_id'] == $_SESSION['user_id']);
                                $status_badge = ($user['status'] === 'Active') ? 'bg-success' : 'bg-danger';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_type']); ?></td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                
                                <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="loadUserForEdit(<?php echo $user['user_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if (!$is_current_user): ?>
                                    <button class="btn btn-sm btn-danger" onclick="confirmAndDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>

<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered"> 
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title" id="userModalLabel"><i class="fas fa-user-plus"></i> Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        
        <form id="userInsertUpdateForm">
            <input type="hidden" name="user_id" id="user_id_modal" value=""> 

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="name_modal" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name_modal" name="name" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="username_modal" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username_modal" name="username" required>
                </div>
            </div>

            <div class="row mb-3" id="password_section">
                <div class="col-md-12">
                    <label for="password_modal" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password_modal" name="password" required>
                    <div id="passwordHelp" class="form-text">Edit Mode: Leave blank to keep current password.</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="user_type_modal" class="form-label">User Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="user_type_modal" name="user_type" required>
                        <option value="Admin">Admin</option>
                        <option value="User">Standard User</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status_modal" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status_modal" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer justify-content-between mt-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveUserButton"><i class="fas fa-save"></i> Save User</button>
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
    
    // PHP User Data to JavaScript variable
    const allUsersData = <?php echo json_encode($user_data ?: []); ?>;
    
    // -----------------------------------------------------------
    // 1. Prepare Modal for ADD
    // -----------------------------------------------------------
    window.prepareAddModal = function() {
        $('#userInsertUpdateForm')[0].reset();
        
        // Reset to Add Mode
        $('#user_id_modal').val(''); 
        $('#userModalLabel').html('<i class="fas fa-user-plus"></i> Add New User');
        $('#saveUserButton').text('Save User');
        
        // Password section adjustments for ADD
        $('#password_section').show();
        $('#password_modal').prop('required', true); // Password is required for new user
        $('#passwordHelp').text('');
    };
    
    // -----------------------------------------------------------
    // 2. Load User Data for EDIT
    // -----------------------------------------------------------
    window.loadUserForEdit = function(userId) {
        const user = allUsersData.find(u => u.user_id == userId);
        if (!user) {
            Swal.fire('දෝෂයක්!', 'User දත්ත සොයාගත නොහැක.', 'error');
            return;
        }

        // Set Modal Title and Button Text
        $('#userModalLabel').html('<i class="fas fa-edit"></i> Edit User Details (ID: ' + userId + ')');
        $('#saveUserButton').text('Update User');
        
        // Populate Form Fields
        $('#user_id_modal').val(user.user_id); 
        $('#name_modal').val(user.name);
        $('#username_modal').val(user.username);
        $('#user_type_modal').val(user.user_type);
        $('#status_modal').val(user.status);
        
        // Password section adjustments for EDIT
        $('#password_section').show();
        $('#password_modal').val(''); // Clear password field
        $('#password_modal').prop('required', false); // Not required for update unless changed
        $('#passwordHelp').text('මුරපදය වෙනස් කිරීමට අවශ්‍ය නම් පමණක් ඇතුළත් කරන්න.');
        
        // Show Modal
        $('#userModal').modal('show');
    };

    // -----------------------------------------------------------
    // 3. AJAX Submission Handler (Handles both ADD and EDIT)
    // -----------------------------------------------------------
    $('#userInsertUpdateForm').on('submit', function(e) {
        e.preventDefault(); 
        
        const isEditing = $('#user_id_modal').val() !== '';
        const targetUrl = 'user_process.php'; 

        $.ajax({
            url: targetUrl, 
            type: 'POST',
            data: $(this).serialize() + '&action=' + (isEditing ? 'update' : 'insert'),
            dataType: 'json', 
            
            success: function(response) {
                Swal.fire({
                    title: response.title || 'දැනුම්දීම!',
                    text: response.message || 'කිසියම් දෝෂයක් ඇත.',
                    icon: response.icon || 'info'
                }).then((result) => {
                    if (response.status === 'success') {
                        // Refresh the page to show the update/new entry
                        $('#userModal').modal('hide');
                        window.location.reload(); 
                    }
                });
            },
            
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'තාක්ෂණික දෝෂයක්!',
                    text: 'සේවාදායකය (Server) සමඟ සම්බන්ධ වීමේ ගැටලුවක්. නැවත පරීක්ෂා කරන්න.',
                    icon: 'error'
                });
            }
        });
    });

    // -----------------------------------------------------------
    // 4. Delete Confirmation
    // -----------------------------------------------------------
    window.confirmAndDelete = function(userId, username) {
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;

        if (userId == currentUserId) {
            Swal.fire('අවසර නැත!', 'ඔබට ඔබගේම ගිණුම මකා දැමිය නොහැක.', 'error');
            return;
        }

        Swal.fire({
            title: 'ඔබට විශ්වාසද?',
            html: `ඔබට පරිශීලක **${username}** (ID: ${userId}) ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ඔව්, ඉවත් කරන්න!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `users.php?delete_id=${userId}`;
            }
        });
    };
});
</script>