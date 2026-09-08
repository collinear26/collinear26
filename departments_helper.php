<?php
// Centralized na helper para sa listahan ng mga registered offices/departments.
// Ito ang single source of truth para sa lahat ng office dropdown sa buong app
// (Forward Record, Forward/Route Document, Add/Edit User) — pinapalitan ang
// dating malayang pagta-type ng office name sa bawat form, na siyang dahilan
// kung bakit nagkakaroon ng magkakaibang baybay ng parehong opisina sa datos
// (hal. "SOIT", "SOIT Office", "SOIT Department" — tatlong magkaibang text
// para sa iisang opisina lang).
//
// Ang users.department / documents.department / tracking_logs.routing_from
// /routing_to ay nananatiling VARCHAR (hindi ito ginawang foreign key) — sa
// halip, dito lang pinipigilan ang pagpasok ng hindi kilalang opisina bago pa
// ito ma-save, habang buo pa rin ang lahat ng dating record na naka-imbak na
// bilang plain text (kasama na ang mga historical/system-state na label tulad
// ng "Archives" o "Approval Queue" na hindi naman talaga opisina).

// Ibalik ang listahan ng lahat ng ACTIVE na registered offices, pinagsunod-sunod
function get_active_departments($conn) {
    $departments = [];
    $result = mysqli_query($conn, "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row;
        }
    }
    return $departments;
}

// Tsekan kung kilalang/rehistradong opisina ang ibinigay na pangalan (case-insensitive)
function is_registered_department($conn, $name) {
    $name = trim((string) $name);
    if ($name === '') return false;
    $stmt = mysqli_prepare($conn, "SELECT id FROM departments WHERE LOWER(name) = LOWER(?) AND is_active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result && mysqli_num_rows($result) > 0;
}

// I-rehistro ang isang bagong opisina kung wala pa ito (ginagamit lang sa Add/Edit
// User, kung saan legitimate ang pangangailangang mag-onboard ng bagong opisina
// habang gumagawa ng unang user account nito). Idempotent — hindi gagawa ng
// duplicate kung existing na ang pangalan (case-insensitive check).
function ensure_department_registered($conn, $name) {
    $name = trim((string) $name);
    if ($name === '') return;
    if (is_registered_department($conn, $name)) return;
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO departments (name) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);
}
