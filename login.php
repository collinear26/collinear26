<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ASCOT RecordsHub</title>
    
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
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url("488116812_2472210126505231_2397120033774347188_n.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            overflow: hidden;
        }

        /* Dark Forest Green Background Overlay */
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

        /* Compact Glassmorphism Card */
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
        }

        /* Back to Home Link */
        .back-to-home {
            position: absolute;
            top: 20px;
            right: 25px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 11px;
            font-weight: 400;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .back-to-home:hover {
            color: #ffffff;
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 12px;
        }

        .logo img {
            width: 80px;
            height: auto;
        }

        h1 {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            margin-bottom: 22px;
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

        /* Inputs Container (Para sa Eye Icon Positioning) */
        .input-box {
            position: relative;
            width: 100%;
        }

        .input-box input {
            width: 100%;
            height: 42px;
            padding: 0 40px 0 14px; /* Dinagdagan ang kanang padding para hindi matakpan ng mata ang text */
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

        /* Style para sa Hide/Show Eye Icon */
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
        }

        .toggle-password:hover {
            color: #ffffff;
        }

        /* Ultra Fix para sa Autofill White Box */
        .input-box input:-webkit-autofill,
        .input-box input:-webkit-autofill:hover, 
        .input-box input:-webkit-autofill:focus, 
        .input-box input:-webkit-autofill:active {
            -webkit-text-fill-color: #ffffff !important;
            color: #ffffff !important;
            -webkit-box-shadow: 0 0 0px 1000px rgba(32, 48, 37, 0.6) inset !important;
            box-shadow: 0 0 0px 1000px rgba(32, 48, 37, 0.6) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* Checkbox at Links Area */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
        }

        .remember input {
            width: 14px;
            height: 14px;
            accent-color: #90d296;
            cursor: pointer;
        }

        .forgot {
            color: #e2c044;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot:hover {
            text-decoration: underline;
        }

        /* Sign In Button */
        .login-btn {
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
            margin-bottom: 12px;
        }

        .login-btn:hover {
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

        .success-box {
            background: rgba(144, 210, 150, 0.15);
            border: 1px solid rgba(144, 210, 150, 0.4);
            color: #bbf7d0;
            font-size: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        /* Responsive */
        @media(max-width: 480px){
            .login-card {
                margin: 15px;
                padding: 35px 20px 25px 20px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <a href="index.html" class="back-to-home">&#8592; Back to Home</a>

        <div class="logo">
            <img src="ascot seal.png" alt="ASCOT Seal">
        </div>

        <h1>Welcome Back</h1>
        <p class="subtitle">Sign in to your ASCOT RecordsHub Account</p>

        <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
            <div class="success-box">Your password has been reset successfully. Please sign in with your new password.</div>
        <?php endif; ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label>Email / ID Number</label>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Enter Email or ID Number" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-box">
                    <input type="password" id="password" name="password" placeholder="Enter your Password" required>
                    <i class="fa-solid fa-eye-slash toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <div class="form-options">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1"> Remember me
                </label>
                <a href="forgot_password.php" class="forgot">Forgot Password</a>
            </div>

            <button type="submit" class="login-btn">Sign In</button>
        </form>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordIcon = document.getElementById('togglePassword');

        togglePasswordIcon.addEventListener('click', function () {
            // Tinitingnan kung password o text ang kasalukuyang type
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Pinapalitan ang icon (Eye / Eye-Slash)
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>