<?php
// log_activity.php - Universal Audit Logger
function log_activity($conn, $user_id, $action, $description) {
    // Sinisigurong tugma ang 'description' sa column ng database table natin
    $stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $description);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>