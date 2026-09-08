<?php
session_start();
include 'db_conn.php';

// Kung naka-login na, hindi na kailangan ng forgot password flow
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

// RATE LIMITING (security audit finding): dati, walang anumang limitasyon
// sa pag-guess ng Email + ID Number combination dito, kahit na walang
// email/OTP verification ang flow na ito. Kinokopya ang parehong
// max-attempts/lockout pattern na ginagamit na ng login_process.php,
// pero ito ay per-IP address dahil hindi pa alam kung existing ba talaga
// ang account habang nag-a-attempt pa lang.
define('RESET_MAX_ATTEMPTS', 5);
define('RESET_LOCKOUT_MINUTES', 15);

$ip_address = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email']);
    $id_number = trim($_POST['id_number']);

    // Tignan muna kung naka-lock ang IP address na ito dahil sa sunod-sunod
    // na maling attempts kamakailan lang
    $lock_check_stmt = mysqli_prepare($conn, "SELECT attempts, locked_until FROM password_reset_attempts WHERE ip_address = ?");
    mysqli_stmt_bind_param($lock_check_stmt, "s", $ip_address);
    mysqli_stmt_execute($lock_check_stmt);
    $lock_row = mysqli_fetch_assoc(mysqli_stmt_get_result($lock_check_stmt));

    if ($lock_row && !empty($lock_row['locked_until']) && strtotime($lock_row['locked_until']) > time()) {
        $wait_minutes = max(1, (int) ceil((strtotime($lock_row['locked_until']) - time()) / 60));
        $error_message = "For security, too many attempts have been made recently. Please try again in about {$wait_minutes} minute(s).";
    } elseif (empty($email) || empty($id_number)) {
        $error_message = 'Please fill out both fields.';
    } else {
        // I-verify na magkatugma ang Email AT ID Number sa iisang account
        // (dalawang bagay na dapat malaman ng tunay na may-ari ng account,
        // hindi lang basta email — simpleng paraan ng identity verification
        // dahil walang naka-setup na email-sending/SMTP sa system)
        $query = "SELECT id, firstname FROM users WHERE email = ? AND id_number = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $email, $id_number);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            // Tamang combination: i-clear ang throttling counter ng IP na ito
            $clear_stmt = mysqli_prepare($conn, "DELETE FROM password_reset_attempts WHERE ip_address = ?");
            mysqli_stmt_bind_param($clear_stmt, "s", $ip_address);
            mysqli_stmt_execute($clear_stmt);

            // I-store sa session na verified na ang identity niya, pansamantala lang
            // (gagamitin ito ng reset_password.php, aalisin agad pagkatapos gamitin)
            $_SESSION['reset_verified_user_id'] = $user['id'];
            $_SESSION['reset_verified_name'] = $user['firstname'];

            header("Location: reset_password.php");
            exit();
        } else {
            // Maling combination: dagdagan ang attempt counter ng IP na ito,
            // at i-lock kung umabot na sa limit
            $new_attempts = ($lock_row ? intval($lock_row['attempts']) : 0) + 1;

            if ($new_attempts >= RESET_MAX_ATTEMPTS) {
                $upsert_stmt = mysqli_prepare($conn, "INSERT INTO password_reset_attempts (ip_address, attempts, locked_until) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
                    ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)");
                $lockout_minutes = RESET_LOCKOUT_MINUTES;
                mysqli_stmt_bind_param($upsert_stmt, "sii", $ip_address, $new_attempts, $lockout_minutes);
                mysqli_stmt_execute($upsert_stmt);
                $error_message = "For security, too many attempts have been made. Please try again in {$lockout_minutes} minutes.";
            } else {
                $upsert_stmt = mysqli_prepare($conn, "INSERT INTO password_reset_attempts (ip_address, attempts) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE attempts = VALUES(attempts)");
                mysqli_stmt_bind_param($upsert_stmt, "si", $ip_address, $new_attempts);
                mysqli_stmt_execute($upsert_stmt);
                $error_message = 'No account found matching that Email and ID Number combination.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | ASCOT RecordsHub</title>

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

        .back-to-login {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 11px;
            font-weight: 400;
            transition: 0.2s;
            margin-bottom: 20px;
        }

        .back-to-login:hover {
            color: #ffffff;
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
        <a href="login.php" class="back-to-login">&#8592; Back to Login</a>

        <div class="icon-badge">
            <i class="fa-solid fa-key"></i>
        </div>

        <h1>Forgot Password</h1>
        <p class="subtitle">Enter your registered Email Address and ID Number to verify your identity.</p>

        <?php if (!empty($error_message)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Enter your registered email" required>
                </div>
            </div>

            <div class="form-group">
                <label>ID Number</label>
                <div class="input-box">
                    <input type="text" name="id_number" placeholder="e.g. 2026-0001" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">Verify Identity</button>
        </form>
    </div>

</body>
</html>