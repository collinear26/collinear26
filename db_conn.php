<?php
// SAFETY NET (technical audit finding): kahit na-set na ang display_errors=Off
// sa php.ini, sinisiguro pa rin dito sa runtime — kung sakaling ma-reset man
// ang php.ini sa ibang environment/deployment, hindi pa rin dapat lumalabas
// ang mga file path/stack trace/SQL details sa browser ng end user. Ang
// log_errors ay hindi ginagalaw dito, kaya laging nade-diagnose pa rin ang
// mga error sa php_error_log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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