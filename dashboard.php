<?php
// File Name: dashboard.php (Final Working Version with Real Data Counts)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🛑 ආරක්ෂක පරීක්ෂාව
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Clients, Products, Invoices ගණන ගණනය කිරීමට අවශ්‍යයි
require_once 'db_connect.php'; 

// -----------------------------------------------------------
// 1. Data Fetching Logic (Database Connection)
// -----------------------------------------------------------
$total_clients = 0;
$total_products = 0;
$total_invoices = 0;
$userType = $_SESSION['user_type'] ?? 'User'; // User Type ලබා ගැනීම

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // දත්ත සම්බන්ධතාවය අසාර්ථක නම්, 0 ලෙස පෙන්වයි
    // Error message set කිරීමට අවශ්‍ය නම් මෙහිදී කළ හැක
} else {
    // A. Clients ගණන
    $result_clients = $conn->query("SELECT COUNT(client_id) AS count FROM clients");
    if ($result_clients) {
        $total_clients = $result_clients->fetch_assoc()['count'];
    }

    // B. Products ගණන
    $result_products = $conn->query("SELECT COUNT(product_id) AS count FROM products");
    if ($result_products) {
        $total_products = $result_products->fetch_assoc()['count'];
    }

    // C. Invoices ගණන
    $result_invoices = $conn->query("SELECT COUNT(invoice_id) AS count FROM invoices");
    if ($result_invoices) {
        $total_invoices = $result_invoices->fetch_assoc()['count'];
    }

    $conn->close();
}

// Dashboard එකේ ඉහළින්ම Header ඇතුළත් කිරීමට අවශ්‍යයි
$page_title = 'Dashboard';
require_once 'header.php'; 

// -----------------------------------------------------------
// HTML Display Variables (Real Data Counts)
// -----------------------------------------------------------
$icon_base_path = 'uploads/icon/';

// Demo Finance Values (ඔබගේ සැබෑ ගණනයන් වෙනස් කළ යුතුය)
$total_quotations = 156; // Demo Data
$successful_invoices = 89; // Demo Data

if ($userType == 'Admin') {
    // Admin සඳහා මුදල් අගයන් පෙන්වයි (Demo)
    $quotation_value_display = number_format(750000, 2); 
    $invoice_amount_display = number_format(750000, 2); 
} else {
    // User සඳහා සංඛ්‍යාව පමණක් පෙන්වයි
    $quotation_value_display = $total_quotations; 
    $invoice_amount_display = $successful_invoices; 
}

// Login Time Display
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = date('Y-m-d H:i:s');
}
$login_time = $_SESSION['login_time'];
?>

    <h1 class="mb-4 text-primary"><i class="fas fa-tachometer-alt"></i> Dashboard Summary</h1>
    <hr>
    
    <div class="row">
        
        <?php if ($userType == 'Admin'): ?>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card card-quotations">
                <div class="card-text-area">
                    <div class="h5 mb-0 font-weight-bold">Total Quotations</div>
                    <div class="small text-white opacity-75">LKR Amount</div> 
                    <div class="display-4 font-weight-bold mt-2"><?php echo $quotation_value_display; ?></div> 
                </div>
                <div class="card-icon-container">
                    <div class="icon-wrapper">
                        <img class="card-icon-img" src="<?php echo $icon_base_path; ?>quotation_icon.png" alt="Quotations Icon">
                        <span class="icon-value-badge"><?php echo $total_quotations; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow h-100 p-3 text-center bg-light text-secondary">
                **Restricted Access**
            </div>
        </div>
        <?php endif; ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card card-invoices">
                <div class="card-text-area">
                    <div class="h5 mb-0 font-weight-bold">Total Invoices</div>
                    <div class="small text-white opacity-75">Count</div> 
                    <div class="display-4 font-weight-bold mt-2"><?php echo number_format($total_invoices); ?></div> 
                </div>
                <div class="card-icon-container">
                    <div class="icon-wrapper">
                        <img class="card-icon-img" src="<?php echo $icon_base_path; ?>invoice_icon.png" alt="Invoices Icon">
                        <span class="icon-value-badge"><?php echo number_format($total_invoices); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card card-products">
                 <div class="card-text-area">
                    <div class="h5 mb-0 font-weight-bold">Total Products</div>
                    <div class="small text-white opacity-75">Count</div>
                    <div class="display-4 font-weight-bold mt-2"><?php echo number_format($total_products); ?></div> 
                </div>
                <div class="card-icon-container">
                    <div class="icon-wrapper">
                        <img class="card-icon-img" src="<?php echo $icon_base_path; ?>inventory_icon.png" alt="Inventory Icon">
                        <span class="icon-value-badge"><?php echo number_format($total_products); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card card-clients">
                 <div class="card-text-area">
                    <div class="h5 mb-0 font-weight-bold">Total Clients</div>
                    <div class="small text-white opacity-75">Count</div>
                    <div class="display-4 font-weight-bold mt-2"><?php echo number_format($total_clients); ?></div> 
                </div>
                <div class="card-icon-container">
                    <div class="icon-wrapper">
                        <img class="card-icon-img" src="<?php echo $icon_base_path; ?>clients_icon.png" alt="Clients Icon">
                        <span class="icon-value-badge"><?php echo number_format($total_clients); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar"></i> Monthly Revenue & Profit Summary</h6>
                </div>
                <div class="card-body">
                    <canvas id="profitBarChart" style="display: block; width: 100%; height: 300px;"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle"></i> System Status & Developer Info</h6>
                </div>
                <div class="card-body text-center bg-light d-flex flex-column justify-content-between pt-4 pb-4">
                    
                    <div>
                        <div id="liveDigitalDate" class="small text-muted mb-1" style="font-weight: 600;">
                            </div>
                        <div id="liveDigitalTime" class="display-5 text-primary mb-2" style="font-weight: 800; line-height: 1;">
                            </div>
                        <p class="mb-0 small text-muted">Logged In Since: **<?php echo date('H:i:s', strtotime($login_time)); ?>**</p>
                    </div>

                    <hr class="my-3">
                    
                    <div class="d-flex flex-column align-items-center">
                        
                        <h5 class="mb-0" style="color: var(--sidebar-bg); font-weight: bold; font-size: 1.1rem;">KAWDU TECHNOLOGY</h5>
                        <p class="mb-0 small text-muted">Custom ERP Solutions</p>
                        <p class="mb-1 small text-secondary">Designed and Developed in Sri Lanka</p>
                        
                        <p class="mb-2 small text-primary" style="font-weight: 600;">
                            0776 228 943 | 0786 228 943
                        </p>
                        
                        <img src="<?php echo $icon_base_path; ?>logo.png" alt="Developer Logo" class="mt-2" style="max-height: 40px; border-radius: 3px;">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row mt-3">
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-receipt"></i> Recent Invoices (Last 5)</h6>
                    <a href="invoices.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>No.</th><th>Client</th><th>Amount (රු.)</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                            <tr><td>INV-0010</td><td>ABC Hardware</td><td>55,000.00</td><td>Nov 09, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>INV-0009</td><td>Mr. Nimal P.</td><td>12,500.00</td><td>Nov 05, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>INV-0008</td><td>Global Security</td><td>145,000.00</td><td>Oct 30, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>INV-0007</td><td>Mrs. S. Silva</td><td>8,200.00</td><td>Oct 25, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>INV-0006</td><td>Tech Solutions</td><td>34,500.00</td><td>Oct 20, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice"></i> Recent Quotations (Last 5)</h6>
                    <a href="quotations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>No.</th><th>Client</th><th>Amount (රු.)</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                            <tr><td>QUO-0025</td><td>New Project Z</td><td>250,000.00</td><td>Nov 10, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>QUO-0024</td><td>W. Senarathne</td><td>15,800.00</td><td>Nov 07, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>QUO-0023</td><td>Kandy Systems</td><td>89,000.00</td><td>Oct 31, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>QUO-0022</td><td>Lanka Telco</td><td>62,000.00</td><td>Oct 26, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                            <tr><td>QUO-0021</td><td>M. Perera</td><td>4,900.00</td><td>Oct 22, 2025</td><td><a href="#" class="btn btn-sm btn-outline-info">View</a></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div> 
<?php 
// Footer ඇතුළත් කිරීම (ChartJS සහ SweetAlert ඇතුළත් වේ)
require_once 'footer.php';
?>

<script>
    // Live Digital Clock Function
    function updateClock() {
        const now = new Date();
        
        // 1. Date (e.g., Tuesday, November 11, 2025)
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const formattedDate = now.toLocaleDateString('en-US', dateOptions);

        // 2. Time (e.g., 01:30:37 AM - Large Digital Display)
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const formattedTime = now.toLocaleTimeString('en-US', timeOptions);

        const dateElement = document.getElementById('liveDigitalDate');
        const timeElement = document.getElementById('liveDigitalTime');
        
        if(dateElement) {
            dateElement.textContent = formattedDate;
        }
        if(timeElement) {
            timeElement.textContent = formattedTime;
        }
    }

    // Initialize and set interval
    updateClock();
    setInterval(updateClock, 1000);

    // ChartJS Initialization Script (පෙර තිබූ පරිදිම)
    const ctx = document.getElementById('profitBarChart').getContext('2d');
    
    // Bar Chart එක නිර්මාණය කිරීම
    const profitBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
            datasets: [{
                label: 'Total Revenue (රු.)',
                data: [450000, 520000, 680000, 710000, 750000, 810000],
                backgroundColor: 'rgba(39, 177, 157, 0.7)', 
                borderColor: '#27b19d',
                borderWidth: 1
            },
            {
                label: 'Total Profit (රු.)',
                data: [110000, 135000, 160000, 185000, 195000, 210000],
                backgroundColor: 'rgba(255, 193, 7, 0.9)', 
                borderColor: '#ffc107',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, 
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (රු.)'
                    },
                    ticks: {
                        callback: function(value, index, ticks) {
                            return 'රු. ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Revenue and Profit Analysis (Last 6 Months)'
                }
            }
        }
    });
</script>