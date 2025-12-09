<?php
// File Name: db_auto_update.php
// මෙම ගොනුව වරක් Run කිරීමෙන් Database එක ස්වයංක්‍රීයව යාවත්කාලීන වේ.

include 'db_connect.php';

// දත්ත සමුදා සම්බන්ධතාවය පරීක්ෂා කිරීම
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Database Update Process Started...</h3>";

// 1. Invoices Table එකට 'updated_at' එකතු කිරීම (තිබේදැයි පරීක්ෂා කර)
$check_col = $conn->query("SHOW COLUMNS FROM invoices LIKE 'updated_at'");
if ($check_col->num_rows == 0) {
    $sql1 = "ALTER TABLE invoices ADD COLUMN updated_at DATETIME DEFAULT NULL";
    if ($conn->query($sql1) === TRUE) {
        echo "<p style='color:green;'>✔ 'updated_at' column added to invoices table.</p>";
    } else {
        echo "<p style='color:red;'>✘ Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange;'>⚠ 'updated_at' column already exists. No changes needed.</p>";
}

// 2. Invoice History Table එක සෑදීම (තිබේදැයි පරීක්ෂා කර)
$sql2 = "CREATE TABLE IF NOT EXISTS invoice_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    action_description TEXT NOT NULL,
    updated_by VARCHAR(100) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
)";

if ($conn->query($sql2) === TRUE) {
    echo "<p style='color:green;'>✔ 'invoice_history' table check/creation successful.</p>";
} else {
    echo "<p style='color:red;'>✘ Error creating table: " . $conn->error . "</p>";
}

echo "<hr><h3>Process Completed!</h3>";
echo "<p>දැන් ඔබට මෙම ගොනුව මකා දමා (Delete), සිස්ටම් එක භාවිතා කළ හැක.</p>";
echo "<a href='invoices.php'><button>Go to Invoices</button></a>";

$conn->close();
?>