<?php
// Kunin muna kung naka-check ang "Remember Me" BAGO tumawag ng session_start(),
// dahil kailangang i-set ang cookie lifetime bago pa magsimula ang session.
$remember_me = isset($_POST['remember']) && $_POST['remember'] == '1';

// 30 araw kung naka-check ang Remember Me, kung hindi, mananatili sa default
// (mawawala ang session pagsara ng browser, katulad ng dati)
$cookie_lifetime = $remember_me ? (30 * 24 * 60 * 60) : 0;
session_set_cookie_params($cookie_lifetime);

// Simulan ang session para matandaan ng system kung sino ang naka-login
session_start();

// Isama ang database connection file
include 'db_conn.php';

// Helper function para sa SweetAlert na may bilog sa icon pero walang solid white background
function showSweetAlert($icon, $title, $text, $actionType) {
    $actionScript = ($actionType === 'redirect') 
        ? "window.location.href = 'dashboard.php';" 
        : "window.history.back();";

    $timerSetting = ($icon === 'success') ? "timer: 1500, showConfirmButton: false," : "showConfirmButton: true, confirmButtonText: 'Okay',";

    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>ASCOT RecordsHub - Authentication</title>
        <!-- Google Fonts Inter -->
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
        <!-- SweetAlert2 -->
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Inter', sans-serif;
            }
            html, body {
                width: 100%;
                min-height: 100vh;
                margin: 0;
                padding: 0;
                overflow-x: hidden;
            }
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                background-image: url('488116812_2472210126505231_2397120033774347188_n.jpg');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                background-attachment: fixed;
                position: relative;
            }
            /* Dark Forest Green Background Overlay na sakop ang buong screen nang walang putol */
            body::before {
                content: '';
                position: fixed;
                left: 0;
                top: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(12, 30, 18, 0.78);
                z-index: 1;
                pointer-events: none;
            }
            /* SweetAlert container setup */
            .swal2-container {
                z-index: 10 !important;
                position: fixed !important;
                inset: 0 !important;
            }
            .swal2-popup.login-glass-popup {
                background: rgba(255, 255, 255, 0.07) !important;
                border: 1px solid rgba(255, 255, 255, 0.15) !important;
                backdrop-filter: blur(20px) !important;
                -webkit-backdrop-filter: blur(20px) !important;
                border-radius: 24px !important;
                padding: 35px !important;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4) !important;
                color: #ffffff !important;
                font-family: 'Inter', sans-serif !important;
                width: 100% !important;
                max-width: 420px !important;
            }
            .swal2-title {
                color: #ffffff !important;
                font-size: 24px !important;
                font-weight: 700 !important;
                letter-spacing: -0.5px !important;
                margin-top: 15px !important;
            }
            .swal2-html-container {
                color: rgba(255, 255, 255, 0.7) !important;
                font-size: 13px !important;
            }
            .swal2-confirm.login-glass-btn {
                background: #90d296 !important;
                color: #0d2614 !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                border-radius: 8px !important;
                padding: 10px 24px !important;
                box-shadow: none !important;
            }
            .swal2-confirm.login-glass-btn:hover {
                background: #7ec485 !important;
            }
            /* Inaalis ang puting background at solid lines sa loob ng bilog, pinapanatili ang ring outline */
            .swal2-icon.swal2-success {
                border-color: #90d296 !important;
            }
            .swal2-success-ring {
                background-color: transparent !important;
                border-color: rgba(144, 210, 150, 0.3) !important;
            }
            .swal2-success-circular-line-left, 
            .swal2-success-circular-line-right, 
            .swal2-success-fix {
                background-color: transparent !important;
            }
        </style>
    </head>
    <body>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '$icon',
                    title: '$title',
                    text: '$text',
                    $timerSetting
                    background: 'transparent',
                    iconColor: '#90d296',
                    customClass: {
                        popup: 'login-glass-popup',
                        title: 'swal2-title',
                        htmlContainer: 'swal2-html-container',
                        confirmButton: 'login-glass-btn'
                    }
                }).then(() => {
                    $actionScript
                });
            });
        </script>
    </body>
    </html>
    ";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email='$username' OR id_number='$username' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        // I-verify muna ang password bago ipakita ang account status,
        // para hindi malaman ng ibang tao kung "pending"/"inactive" ang status
        // ng isang account sa pamamagitan lang ng pag-guess ng email/ID number.
        if (password_verify($password, $user['password_hash'])) {

            if ($user['account_status'] === 'pending') {
                showSweetAlert('warning', 'Account Pending', 'Your account is still pending approval from the admin.', 'back');
            } else if ($user['account_status'] === 'inactive') {
                showSweetAlert('error', 'Account Deactivated', 'Your account has been deactivated. Please contact the administrator.', 'back');
            } else {
                // Regenerate session ID para maiwasan ang session fixation attacks
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['firstname'] = $user['firstname'];
                $_SESSION['lastname']  = $user['lastname'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['department']= $user['department'];
                $_SESSION['email']     = $user['email']; // FIX: dati wala nito, kaya laging default/blangko ang email sa settings.php

                showSweetAlert('success', 'Welcome Back, ' . $user['firstname'] . '!', 'Login successful. Redirecting to your dashboard...', 'redirect');
            }
        } else {
            showSweetAlert('error', 'Incorrect Password', 'The password you entered is incorrect. Please try again.', 'back');
        }
    } else {
        showSweetAlert('error', 'Account Not Found', 'No account is associated with this Email or ID Number.', 'back');
    }
}
?>