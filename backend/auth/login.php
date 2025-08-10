<?php
session_start();

// Auto logout logic
$timeout_duration = 1200; // 20 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=true");
    exit();
}
$_SESSION['last_activity'] = time();

// Database connection
$conn = new mysqli("localhost", "root", "root", "icspl1");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

// Show session timeout message if redirected
if (isset($_GET["timeout"]) && $_GET["timeout"] === "true") {
    $error = "⏰ Session expired due to inactivity. Please login again.";
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, email, password, role FROM admin_users12 WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($admin_id, $db_email, $hashedPassword, $role);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set all session variables
            $_SESSION["admin"] = $db_email;
            $_SESSION["admin_id"] = $admin_id;
            $_SESSION["admin_role"] = $role;
            $_SESSION["last_activity"] = time();

            // Log login
            $log_stmt = $conn->prepare("INSERT INTO admin_login_logs12 (email, ip_address, user_agent) VALUES (?, ?, ?)");
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt->bind_param("sss", $db_email, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();

            // Log activity
            $action = "Logged in";
            $activity_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
            $activity_stmt->bind_param("is", $admin_id, $action);
            $activity_stmt->execute();
            $activity_stmt->close();

            // Clear output buffer
            if (ob_get_length()) {
                ob_end_clean();
            }
            
            // Redirect to admin dashboard
            header("Location: /admin");
            exit();
        } else {
            $error = "❌ Invalid password.";
        }
    } else {
        $error = "⚠️ Email not found.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Trident Systems</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --text: #1e293b;
            --light: #f8fafc;
            --error: #ef4444;
            --success: #10b981;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text);
            position: relative;
            overflow: auto;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            animation: rotate 20s linear infinite;
            z-index: 0;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-container {
            position: relative;
            width: 100%;
            max-width: 420px;
            z-index: 10;
            margin: 20px 0;
        }
        
        .login-box {
            background: rgba(255, 255, 255, 0.98);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            animation: fadeInUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
            width: 100%;
        }
        
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo img {
            max-width: 160px;
            height: auto;
            transition: transform 0.3s ease;
        }
        
        .logo:hover img {
            transform: scale(1.05);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            outline: none;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background-color: #f8fafc;
        }
        
        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background-color: white;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9375rem;
        }
        
        .form-group input:focus + .input-icon {
            color: var(--primary);
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .error {
            margin: 1.25rem 0;
            padding: 0.875rem;
            text-align: center;
            color: var(--error);
            font-size: 0.875rem;
            background-color: rgba(239, 68, 68, 0.1);
            border-radius: 0.5rem;
            border-left: 3px solid var(--error);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .forgot {
            text-align: center;
            margin-top: 1.25rem;
            font-size: 0.875rem;
        }
        
        .forgot a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
        }
        
        .forgot a:hover {
            color: var(--primary-dark);
        }
        
        .forgot a i {
            margin-right: 0.3rem;
            font-size: 0.8rem;
        }
        
        /* Loading spinner */
        .btn-loading {
            pointer-events: none;
            color: transparent;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: button-loading-spinner 0.8s linear infinite;
        }
        
        @keyframes button-loading-spinner {
            from { transform: translate(-50%, -50%) rotate(0turn); }
            to { transform: translate(-50%, -50%) rotate(1turn); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-box {
                padding: 1.5rem;
            }
            
            .login-header h2 {
                font-size: 1.375rem;
            }
            
            .logo img {
                max-width: 140px;
            }
        }
        
        @media (max-height: 700px) {
            .login-container {
                margin: 40px 0;
            }
            
            .login-box {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <img src="/assets/images/Trident-1-logo.png" alt="Trident Systems">
            </div>
            
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Please enter your credentials to access the admin panel</p>
            </div>
            
            <form method="post" autocomplete="off" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="email" placeholder="admin@example.com" required autocomplete="off">
                        <div class="input-icon"><i class="fas fa-envelope"></i></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="new-password">
                        <div class="input-icon"><i class="fas fa-lock"></i></div>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="forgot">
                <a href="/forgot-password">
                    <i class="fas fa-key"></i> Forgot your password?
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            button.innerHTML = '';
            button.classList.add('btn-loading');
            button.disabled = true;
        });
        
        // Add subtle hover effect to the login box
        const loginBox = document.querySelector('.login-box');
        loginBox.addEventListener('mouseenter', () => {
            loginBox.style.transform = 'translateY(-5px)';
            loginBox.style.boxShadow = '0 15px 30px -5px rgba(0,0,0,0.15)';
        });
        
        loginBox.addEventListener('mouseleave', () => {
            loginBox.style.transform = 'translateY(0)';
            loginBox.style.boxShadow = '0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)';
        });
    </script>
</body>
</html>