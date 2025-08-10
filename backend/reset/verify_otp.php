<?php
session_start();
$conn = new mysqli("localhost", "root", "root", "icspl1");

$message = "";

if (!isset($_SESSION['reset_email'])) {
    header("Location: /forgot-password");
    exit();
}

$email = $_SESSION['reset_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['resend_otp'])) {
        // Generate new OTP
        $new_otp = strval(rand(100000, 999999));
        $new_expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
        
        // Update database with new OTP and reset attempts
        $stmt = $conn->prepare("UPDATE admin_users12 SET otp = ?, otp_expiry = ?, otp_attempts = 0 WHERE email = ?");
        $stmt->bind_param("sss", $new_otp, $new_expiry, $email);
        
        if ($stmt->execute()) {
            // In a real application, you would send this OTP to the user's email
            mail($email, "Your New OTP Code", "Your new OTP is: $new_otp");
            $message = "✅ New OTP has been generated and sent to your email.";
        } else {
            $message = "❌ Failed to generate new OTP. Please try again.";
        }
        $stmt->close();
    } else {
        $entered_otp = trim($_POST["otp"]);

        // Check OTP and expiry
        $stmt = $conn->prepare("SELECT otp, otp_expiry, otp_attempts FROM admin_users12 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($stored_otp, $otp_expiry, $otp_attempts);
        $stmt->fetch();
        $stmt->close();

        $current_time = date("Y-m-d H:i:s");

        // Check if OTP attempts exceeded
        if ($otp_attempts >= 10) {
            $message = "❌ Too many failed attempts. Please request a new OTP.";
        }
        // Check if OTP matches and is not expired
        elseif ($entered_otp == $stored_otp && $current_time <= $otp_expiry) {
            // Reset attempt counter on success
            $reset_stmt = $conn->prepare("UPDATE admin_users12 SET otp_attempts = 0 WHERE email = ?");
            $reset_stmt->bind_param("s", $email);
            $reset_stmt->execute();
            $reset_stmt->close();
            
            $_SESSION['otp_verified'] = true;
            header("Location: /reset-password");
            exit();
        } else {
            // Increment attempt counter on failure
            $update_stmt = $conn->prepare("UPDATE admin_users12 SET otp_attempts = otp_attempts + 1 WHERE email = ?");
            $update_stmt->bind_param("s", $email);
            $update_stmt->execute();
            $update_stmt->close();
            
            $message = "❌ Invalid or expired OTP. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .otp-box {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .otp-box h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            margin-bottom: 1rem;
        }
        .btn {
            width: 100%;
            padding: 12px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.3s;
            margin-bottom: 0.5rem;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-verify {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .btn-resend {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 1rem;
            text-align: center;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 1rem;
            text-align: center;
        }
        .attempts-warning {
            color: #856404;
            background-color: #fff3cd;
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="otp-box">
        <h2>Enter OTP</h2>
        
        <?php if (isset($otp_attempts) && $otp_attempts >= 3): ?>
        <div class="attempts-warning">
            ⚠️ You have <?= 5 - $otp_attempts ?> attempts remaining
        </div>
        <?php endif; ?>
        
        <form method="post">
            <input type="text" name="otp" placeholder="🔢 Enter 6-digit OTP" 
                   pattern="\d{6}" title="Please enter exactly 6 digits" required>
            <button type="submit" class="btn btn-verify">Verify OTP</button>
            <button type="submit" name="resend_otp" class="btn btn-resend">Resend OTP</button>
        </form>
        
        <?php if ($message): ?>
        <div class="<?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>