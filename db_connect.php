<?php

// දත්ත සමුදා සම්බන්ධතා විස්තර (Database Connection Details)
$servername = "localhost"; // Localhost එකේදී 'localhost'
$username = "root"; // ඔබගේ ඉල්ලීම අනුව username
$password = "admin"; // ඔබගේ ඉල්ලීම අනුව password
$dbname = "kawdu_bill_system"; // කලින් නිර්මාණය කළ database නාමය

// MySQLi Object Oriented ක්‍රමය භාවිතයෙන් සම්බන්ධතාවය ඇති කිරීම
$conn = new mysqli($servername, $username, $password, $dbname);

// සම්බන්ධතාවය පරීක්ෂා කිරීම (Check connection)
if ($conn->connect_error) {
    // සම්බන්ධතාවය අසාර්ථක වුවහොත් දෝෂය පෙන්වීම
    die("Connection failed: " . $conn->connect_error);
}

// ඔබට අවශ්‍ය නම්, මෙම පේළිය ඉවත් කළ හැක. 
// echo "Database Connected successfully!"; 

// සම්බන්ධතාවය තවත් ගොනු වලට භාවිතා කිරීම සඳහා $conn විචල්‍යය සුදානම්ව ඇත.
// උදා: $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");

?>