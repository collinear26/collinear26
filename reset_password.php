<?php
session_start();
include 'db_conn.php';

// Kung naka-login na, hindi na kailangan ng reset flow na ito
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// I-guard ang page na ito: kailangan munang dumaan sa forgot_password.php
// at ma-verify ang identity bago makapasok dito
if (!isset($_SESSION['reset_verified_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}

$verified_user_id = $_SESSION['reset_verified_user_id'];
$verified_name = $_SESSION['reset_verified_name'] ?? '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill out both password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password_hash = '" . mysqli_real_escape_string($conn, $hashed_password) . "' WHERE id = " . intval($verified_user_id);

        try {
            mysqli_query($conn, $update_query);

            // Mag-record sa Audit Logs
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $action_msg = "Reset own password via Forgot Password flow.";
            $log_name = mysqli_real_escape_string($conn, $verified_name ?: 'User');

            $log_query = "INSERT INTO audit_logs (user_name, action, ip_address) 
                          VALUES ('$log_name', '$action_msg', '$ip_address')";
            mysqli_query($conn, $log_query);

            // I-clear ang pansamantalang verification session, para isang beses lang magagamit
            unset($_SESSION['reset_verified_user_id']);
            unset($_SESSION['reset_verified_name']);

            header("Location: login.php?reset=success");
            exit();
        } catch (mysqli_sql_exception $e) {
            $error_message = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | ASCOT RecordsHub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url("488116812_2472210126505231_2397120033774347188_n.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        body::before {
            content: "";
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(12, 30, 18, 0.78);
            z-index: -1;
        }

        .login-card {
            position: relative;
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 35px 35px 30px 35px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            margin: 20px;
        }

        .icon-badge {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(144, 210, 150, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
        }

        .icon-badge i {
            font-size: 22px;
            color: #90d296;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .subtitle strong {
            color: #90d296;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .input-box {
            position: relative;
        }

        .input-box input {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        .input-box input:focus {
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.12);
        }

        .input-box input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .submit-btn {
            width: 100%;
            height: 42px;
            border: none;
            border-radius: 8px;
            background: #90d296;
            color: #0d2614;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 8px;
        }

        .submit-btn:hover {
            background: #7ec485;
        }

        .error-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            font-size: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        @media(max-width: 480px){
            .login-card {
                margin: 15px;
                padding: 30px 20px 25px 20px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="icon-badge">
            <i class="fa-solid fa-lock"></i>
        </div>

        <h1>Reset Password</h1>
        <p class="subtitle">Hi <strong><?php echo htmlspecialchars($verified_name ?: 'there'); ?></strong>, please set your new password below.</p>

        <?php if (!empty($error_message)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="reset_password.php" method="POST">
            <div class="form-group">
                <label>New Password</label>
                <div class="input-box">
                    <input type="password" name="new_password" placeholder="Enter new password (min. 8 characters)" required minlength="8">
                </div>
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <div class="input-box">
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="8">
                </div>
            </div>

            <button type="submit" class="submit-btn">Reset Password</button>
        </form>
    </div>

</body>
</html>