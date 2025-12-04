<?php
// Session ආරම්භ කිරීම
session_start();

// පරිශීලකයා ලොග් වී ඇත්දැයි පරීක්ෂා කිරීම
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Reports පිටුවේ මාතෘකාව header.php වෙත යැවීම
$page_title = 'Reports';

// header.php ඇතුළත් කිරීම
require_once 'header.php';

// දත්ත සමුදා සම්බන්ධතාවය ඇතුළත් කිරීම (අවශ්‍ය නම්)
// require_once 'db_connect.php'; 

// Report Module Logic මෙහිදී එකතු වේ
?>

    <h1 class="mb-4 text-primary"><i class="fas fa-chart-line"></i> Business Reports</h1>
    <hr>
    
    <div class="card shadow mb-4">
        <div class="card-body">
            
            <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-report" type="button" role="tab" aria-controls="sales-report" aria-selected="true">
                        <i class="fas fa-receipt"></i> Sales & Revenue Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory-report" type="button" role="tab" aria-controls="inventory-report" aria-selected="false">
                        <i class="fas fa-boxes"></i> Inventory & Stock Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients-report" type="button" role="tab" aria-controls="clients-report" aria-selected="false">
                        <i class="fas fa-users"></i> Client Reports
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="reportTabsContent">
                
                <div class="tab-pane fade show active" id="sales-report" role="tabpanel" aria-labelledby="sales-tab">
                    <h5 class="text-secondary mb-3">Sales Performance Analysis</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="dateRange" class="form-label small">Select Date Range</label>
                            <input type="date" class="form-control form-control-sm" id="dateRange">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="reportType" class="form-label small">Report Type</label>
                            <select class="form-select form-select-sm" id="reportType">
                                <option>Daily Summary</option>
                                <option>Monthly Sales</option>
                                <option>Itemized Sales Report</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                             <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i> Generate Report</button>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-primary mt-4">Revenue vs. Cost of Goods Sold (Demo Chart)</h6>
                    <canvas id="salesChart" style="height: 350px;"></canvas>
                    
                    <h6 class="text-primary mt-4">Top 5 Selling Items (Last Month)</h6>
                    <table class="table table-bordered table-striped table-sm">
                        <thead><tr><th>Item Name</th><th>Total Quantity</th><th>Total Revenue (රු.)</th></tr></thead>
                        <tbody>
                            <tr><td>Dahua 4MP Camera</td><td>45</td><td>180,000.00</td></tr>
                            <tr><td>Seagate 1TB HDD</td><td>30</td><td>150,000.00</td></tr>
                            </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="inventory-report" role="tabpanel" aria-labelledby="inventory-tab">
                    <h5 class="text-secondary mb-3">Low Stock and Valuation Reports</h5>
                    <div class="alert alert-warning">
                        **Low Stock Alert:** 5 items are below safety stock levels.
                    </div>
                    <h6 class="text-primary mt-4">Current Stock Valuation</h6>
                    <p>Total Value of Inventory: **රු. 5,230,500.00**</p>
                    <h6 class="text-primary mt-4">Items Below Minimum Stock (Demo)</h6>
                    <table class="table table-bordered table-striped table-sm">
                        <thead><tr><th>Item Name</th><th>Current Stock</th><th>Min Level</th></tr></thead>
                        <tbody>
                            <tr><td>Cat 6 Cable (Box)</td><td>5</td><td>10</td></tr>
                            <tr><td>Dahua DVR 8CH</td><td>2</td><td>5</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="clients-report" role="tabpanel" aria-labelledby="clients-tab">
                    <h5 class="text-secondary mb-3">Client Spending History</h5>
                    <h6 class="text-primary mt-4">Top 5 Clients by Revenue</h6>
                    <table class="table table-bordered table-striped table-sm">
                        <thead><tr><th>Client Name</th><th>Total Spent (රු.)</th><th>Invoices</th></tr></thead>
                        <tbody>
                            <tr><td>Global Security Systems</td><td>1,500,000.00</td><td>5</td></tr>
                            <tr><td>ABC Hardware</td><td>850,000.00</td><td>3</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

<?php 
// Footer ඇතුළත් කිරීම
require_once 'footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Sales Report Demo Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    
    const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Gross Sales (රු.)',
                data: [150000, 180000, 220000, 200000],
                borderColor: '#17a2b8', // Quotation Color
                tension: 0.1,
                fill: false
            },
            {
                label: 'Net Profit (රු.)',
                data: [40000, 45000, 55000, 50000],
                borderColor: '#28a745', // Invoice Color
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
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
                title: {
                    display: true,
                    text: 'Weekly Sales vs. Profit'
                }
            }
        }
    });
</script>