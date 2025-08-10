<?php
session_start();

// Only allow superadmins to create admin accounts
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: /login");
    exit();
}

$conn = new mysqli("localhost", "root", "root", "icspl1");
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $plainPassword = $_POST["password"];
    $role = $_POST["role"] ?? 'moderator'; // Default to moderator if not specified

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Invalid email format";
    } elseif (!preg_match('/@gmail\.com$/', $email)) {
        $message = "❌ Only Gmail addresses are allowed (@gmail.com)";
    } elseif (strlen($plainPassword) < 8) {
        $message = "❌ Password must be at least 8 characters";
    } else {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO admin_users12 (email, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $hashedPassword, $role);

        try {
            if ($stmt->execute()) {
                // Log this action
                $admin_id = $_SESSION['admin_id'];
                $action = "Created new admin: $email (Role: $role)";
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
                $log_stmt->bind_param("is", $admin_id, $action);
                $log_stmt->execute();
                $log_stmt->close();
                
                $_SESSION['success_message'] = "✅ Admin account created successfully!";
                header("Location: /admin-users");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $message = "❌ This email is already registered";
            } else {
                $message = "❌ Database error: " . $e->getMessage();
                error_log("Admin creation error: " . $e->getMessage());
            }
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Admin User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .admin-box {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            position: relative;
        }

        .admin-box h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #444;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .form-group i {
            position: absolute;
            left: 1rem;
            top: 2.65rem;
            color: #667eea;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .message {
            margin: 1rem 0;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
        }

        .error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .success {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
        }

        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        .password-feedback {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #64748b;
            text-decoration: none;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: #334155;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-box">
        <h2><i class="fas fa-user-plus"></i> Create Admin Account</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="post" id="adminForm">
            <div class="form-group">
                <label for="email">Admin Email</label>
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="name@gmail.com" required autocomplete="off">
                <div class="password-feedback">Must be a Gmail address</div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required autocomplete="new-password">
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="password-feedback" id="passwordFeedback"></div>
            </div>
            
            <div class="form-group">
                <label for="role">Admin Role</label>
                <i class="fas fa-user-tag"></i>
                <select id="role" name="role" required>
                    <option value="moderator">Moderator</option>
                    <option value="admin">Admin</option>
                    <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                        <option value="superadmin">Super Admin</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <button type="submit" id="submitBtn">
                <i class="fas fa-user-plus"></i> Create Admin Account
            </button>
        </form>
        
        <a href="/admin-users" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Admin Users
        </a>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const feedback = document.getElementById('passwordFeedback');
            
            let strength = 0;
            let messages = [];
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Complexity checks
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            const width = (strength / 6) * 100;
            strengthBar.style.width = width + '%';
            
            // Set color and feedback
            if (password.length === 0) {
                strengthBar.style.background = 'transparent';
                feedback.textContent = '';
            } else if (strength <= 2) {
                strengthBar.style.background = '#ef4444';
                feedback.textContent = 'Weak password';
                feedback.style.color = '#ef4444';
            } else if (strength <= 4) {
                strengthBar.style.background = '#f59e0b';
                feedback.textContent = 'Moderate password';
                feedback.style.color = '#f59e0b';
            } else {
                strengthBar.style.background = '#10b981';
                feedback.textContent = 'Strong password';
                feedback.style.color = '#10b981';
            }
        });
        
        // Form submission loading state
        document.getElementById('adminForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            btn.disabled = true;
        });
        
        // Gmail validation
        document.getElementById('email').addEventListener('blur', function() {
            if (this.value && !this.value.endsWith('@gmail.com')) {
                this.setCustomValidity('Only Gmail addresses are allowed');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>