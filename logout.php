<?php
session_start();

// Kung pinindot ang Yes, isagawa na ang pag-destroy ng session at i-redirect sa login
if (isset($_GET['action']) && $_GET['action'] === 'confirm') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - ASCOT RecordsHub</title>
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        /* Dark Forest Green Background Overlay */
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
        /* Confirm Button Style (Yes, Logout) */
        .swal2-confirm.login-glass-btn {
            background: #90d296 !important;
            color: #0d2614 !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            padding: 10px 20px !important;
            box-shadow: none !important;
        }
        .swal2-confirm.login-glass-btn:hover {
            background: #7ec485 !important;
        }
        /* Cancel Button Style */
        .swal2-cancel.login-glass-cancel-btn {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            padding: 10px 20px !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            box-shadow: none !important;
        }
        .swal2-cancel.login-glass-cancel-btn:hover {
            background: rgba(255, 255, 255, 0.2) !important;
        }
    </style>
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Sign Out',
                text: 'Are you sure you want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                background: 'transparent',
                iconColor: '#e2c044',
                customClass: {
                    popup: 'login-glass-popup',
                    title: 'swal2-title',
                    htmlContainer: 'swal2-html-container',
                    confirmButton: 'login-glass-btn',
                    cancelButton: 'login-glass-cancel-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Babalik sa sariling file pero may dalang ?action=confirm para tuluyang ma-destroy ang session
                    window.location.href = 'logout.php?action=confirm';
                } else {
                    // Kung kinancel, babalik sa huling pahina (dashboard)
                    window.history.back();
                }
            });
        });
    </script>
</body>
</html>