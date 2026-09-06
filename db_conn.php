<?php
// Itinatakda ang timezone papuntang Philippine Time (Asia/Manila).
// Dati, wala itong naka-set, kaya sinusunod ng PHP ang default na UTC —
// ito ang dahilan kung bakit mali/naka-lag ng ilang oras ang oras na
// nakikita sa mga messages, audit logs, at ibang parte ng system.
date_default_timezone_set('Asia/Manila');

$sname = "localhost";
$uname = "root";
$password = "";
$db_name = "ascot_recordshub_db"; // Ang bagong database na ginawa mo kanina

$conn = mysqli_connect($sname, $uname, $password, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Isyncronisa rin ang MySQL session timezone papunta sa +08:00 (Philippine Time),
// para tugma ang NOW()/CURRENT_TIMESTAMP ng database sa oras ng PHP
mysqli_query($conn, "SET time_zone = '+08:00'");
?>