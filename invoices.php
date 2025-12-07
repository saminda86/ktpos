<?php
include 'header.php'; // Header එක සහ Navigation එක
include 'db.php';     // Database සම්බන්ධතාවය

// Invoices සහ ඊට අදාල Customer විස්තර ලබා ගැනීම
$query = "SELECT * FROM invoices ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Invoice List (බිල්පත් ලැයිස්තුව)</h6>
            <a href="create_invoice.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Create New Invoice
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="invoiceTable" width="100%" cellspacing="0">
                    <thead class="thead-dark">
                        <tr>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>Total Amount</th>
                            <th>Paid Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                // Status එක අනුව පාට වෙනස් කිරීම (Optional styling)
                                $status_badge = ($row['payment_status'] == 'Paid') ? 'badge-success' : 'badge-warning';
                                ?>
                                <tr>
                                    <td><?php echo $row['invoice_no']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?></td>
                                    <td><?php echo $row['customer_name']; ?></td>
                                    <td>Rs. <?php echo number_format($row['grand_total'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $status_badge; ?>">
                                            <?php echo $row['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="print_invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm" target="_blank" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        
                                        <a href="edit_invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <a href="delete_invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('ඔබට මෙම Invoice එක මැකීමට අවශ්‍ය බව විශ්වාසද?');" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>No invoices found (බිල්පත් කිසිවක් හමු නොවීය)</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
$(document).ready(function() {
    $('#invoiceTable').DataTable({
        "order": [[ 0, "desc" ]] // අලුත්ම Invoice එක උඩින් පෙන්වයි
    });
});
</script>