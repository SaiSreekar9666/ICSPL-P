<?php
session_start();
$conn = new mysqli("localhost", "root", "root", "icspl1");

// Session timeout
$timeout_duration = 1200;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /login?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION["admin"])) {
    header("Location: /login");
    exit();
}

// Check if current admin is active (not deleted)
$admin_check = $conn->prepare("SELECT id FROM admin_users12 WHERE id = ? AND email = ?");
$admin_check->bind_param("is", $_SESSION['admin_id'], $_SESSION['admin']);
$admin_check->execute();
$admin_check->store_result();

if ($admin_check->num_rows == 0) {
    session_unset();
    session_destroy();
    header("Location: /login?error=invalid_session");
    exit();
}
$admin_check->close();

// Add admin user
$add_success = $add_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_admin'])) {
    // Only allow superadmin and admin to add users
    if ($_SESSION['admin_role'] === 'moderator') {
        $add_error = "You don't have permission to add admin users.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'admin';

        // First check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM admin_users12 WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $add_error = "This email address is already registered.";
            $check_stmt->close();
        } 
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $add_error = "Invalid email format.";
        } 
        elseif (strlen($password) < 8) {
            $add_error = "Password must be at least 8 characters.";
        } 
        else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_users12 (email, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $hashedPassword, $role);
            
            if ($stmt->execute()) {
                $add_success = "Admin user added successfully.";
                
                // Log the action
                $admin_id = $_SESSION["admin_id"];
                $action = "Added new admin: $email (Role: $role)";
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
                $log_stmt->bind_param("is", $admin_id, $action);
                $log_stmt->execute();
                $log_stmt->close();
            } else {
                $add_error = "Failed to add admin user: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Delete admin user
if (isset($_GET['delete'])) {
    // Only allow superadmin and admin to delete users
    if ($_SESSION['admin_role'] === 'moderator') {
        $add_error = "You don't have permission to delete admin users.";
    } else {
        $delete_id = intval($_GET['delete']);

        if (isset($_SESSION["admin_id"]) && $_SESSION["admin_id"] == $delete_id) {
            $add_error = "You cannot delete your own account.";
        } else {
            // Start transaction for atomic operations
            $conn->begin_transaction();
            
            try {
                // Get email before deleting for logging
                $stmt = $conn->prepare("SELECT email FROM admin_users12 WHERE id = ?");
                $stmt->bind_param("i", $delete_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $email = $user['email'];
                $stmt->close();

                // Delete related data first
                $conn->query("DELETE FROM admin_sessions WHERE admin_id = $delete_id");
                $conn->query("DELETE FROM admin_activity_logs WHERE admin_id = $delete_id");
                $conn->query("DELETE FROM admin_login_logs12 WHERE email = '$email'");
                
                // Now delete the admin user
                $stmt = $conn->prepare("DELETE FROM admin_users12 WHERE id = ?");
                $stmt->bind_param("i", $delete_id);
                
                if ($stmt->execute()) {
                    // Log the action
                    $admin_id = $_SESSION["admin_id"];
                    $action = "Deleted admin: $email and all related data";
                    $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
                    $log_stmt->bind_param("is", $admin_id, $action);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    $conn->commit();
                    header("Location: /admin-users?deleted=1");
                    exit();
                } else {
                    throw new Exception("Failed to delete admin user");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $add_error = "Error deleting admin: " . $e->getMessage();
            }
        }
    }
}

// Reset password
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_password'])) {
    $admin_id = intval($_POST['admin_id']);
    $new_password = trim($_POST['new_password']);
    
    if (strlen($new_password) < 8) {
        $add_error = "Password must be at least 8 characters.";
    } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin_users12 SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $admin_id);
        if ($stmt->execute()) {
            $add_success = "Password reset successfully.";
            
            // Log the action
            $current_admin_id = $_SESSION["admin_id"];
            $action = "Reset password for admin ID: $admin_id";
            $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
            $log_stmt->bind_param("is", $current_admin_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $add_error = "Failed to reset password.";
        }
        $stmt->close();
    }
}

// Fetch admin users with roles
$result = $conn->query("SELECT id, email, role, created_at, last_login FROM admin_users12 ORDER BY created_at DESC");

// Fetch login logs with pagination
$logs_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $logs_per_page;

$sql_logs = "SELECT id, email, login_time, ip_address, user_agent FROM admin_login_logs12 ORDER BY login_time DESC LIMIT $offset, $logs_per_page";
$result_logs = $conn->query($sql_logs);

// Get total logs count for pagination
$total_logs = $conn->query("SELECT COUNT(*) as total FROM admin_login_logs12")->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $logs_per_page);

// Fetch activity logs
$activity_logs = $conn->query("SELECT a.id, a.action, a.timestamp, u.email 
                              FROM admin_activity_logs a 
                              JOIN admin_users12 u ON a.admin_id = u.id 
                              ORDER BY a.timestamp DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="autocomplete" content="off">
    <link rel="stylesheet" href="/assets/css/admin-users.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="header">
    <div class="admin-info">
        <img src="https://img.freepik.com/premium-photo/3d-sales-manager-character-leading-with-animated-ambition_893571-11254.jpg" alt="Admin">
        <strong>Welcome, <?php echo htmlspecialchars($_SESSION["admin"] ?? 'Admin'); ?></strong>
        <span class="badge badge-<?= $_SESSION['admin_role'] ?>">
            <?= ucfirst($_SESSION['admin_role'] ?? 'admin') ?>
        </span>
    </div>
    <nav class="nav-links">
        <a href="/admin">Dashboard</a>
        <a href="/upload">Upload Files</a>
        <?php if ($_SESSION['admin_role'] !== 'moderator'): ?>
            <a href="/users">User List</a>
        <?php endif; ?>
        <a href="/admin-users" class="active">Admin Users</a>
        <a href="logout" class="logout-btn">Logout</a>
    </nav>
</div>
</div>
<div class="container">
    <h1><i class="fas fa-users-cog"></i> Admin User Management</h1>

    <?php if ($add_error): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($add_error) ?></div>
    <?php elseif ($add_success): ?>
        <div class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($add_success) ?></div>
    <?php endif; ?>

    <div class="stats-container">
        <div class="stat-card">
            <h3><i class="fas fa-users"></i> Total Admins</h3>
            <p><?= $result->num_rows ?></p>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-user-clock"></i> Active Sessions</h3>
            <p><?= $conn->query("SELECT COUNT(*) as count FROM admin_sessions")->fetch_assoc()['count'] ?></p>
        </div>
        <?php if ($_SESSION['admin_role'] === 'moderator'): ?>
            <?php 
            // Get moderator-specific stats
            $moderator_stats = $conn->query("
                SELECT 
                    COUNT(*) as total_logins,
                    MAX(login_time) as last_login
                FROM admin_login_logs12 
                WHERE email = '{$_SESSION['admin']}'
            ")->fetch_assoc();
            
            $moderator_actions = $conn->query("
                SELECT 
                    COUNT(*) as total_actions,
                    SUM(action LIKE '%Added%') as added,
                    SUM(action LIKE '%Deleted%') as deleted,
                    SUM(action LIKE '%Reset%') as resets
                FROM admin_activity_logs 
                WHERE admin_id = {$_SESSION['admin_id']}
            ")->fetch_assoc();
            ?>
            <div class="stat-card moderator">
                <h3><i class="fas fa-sign-in-alt"></i> My Logins</h3>
                <p><?= $moderator_stats['total_logins'] ?></p>
                <div style="font-size: 12px; margin-top: 8px;">
                    Last: <?= $moderator_stats['last_login'] ? date('M d, H:i', strtotime($moderator_stats['last_login'])) : 'Never' ?>
                </div>
            </div>
            <div class="stat-card moderator">
                <h3><i class="fas fa-user-shield"></i> My Actions</h3>
                <p><?= $moderator_actions['total_actions'] ?></p>
                <div style="font-size: 12px; margin-top: 8px;">
                    <span>Added: <?= $moderator_actions['added'] ?></span> | 
                    <span>Deleted: <?= $moderator_actions['deleted'] ?></span> | 
                    <span>Resets: <?= $moderator_actions['resets'] ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="stat-card">
                <h3><i class="fas fa-user-shield"></i> Moderator Logins (7d)</h3>
                <p><?= $conn->query("SELECT COUNT(*) as count FROM admin_login_logs12 WHERE email IN (SELECT email FROM admin_users12 WHERE role = 'moderator') AND login_time > NOW() - INTERVAL 7 DAY")->fetch_assoc()['count'] ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-tie"></i> Admin Logins (7d)</h3>
                <p><?= $conn->query("SELECT COUNT(*) as count FROM admin_login_logs12 WHERE email IN (SELECT email FROM admin_users12 WHERE role = 'admin') AND login_time > NOW() - INTERVAL 7 DAY")->fetch_assoc()['count'] ?></p>
            </div>
        <?php endif; ?>
    </div>


    <div class="section">
        <h2><i class="fas fa-user-plus"></i> Add New Admin</h2>
        <?php if ($_SESSION['admin_role'] !== 'moderator'): ?>
            <form method="post" id="adminUserForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="adminUserEmail">Email</label>
                        <input type="email" name="email" id="adminUserEmail" placeholder="Admin Email" required 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                               autocomplete="off" readonly onfocus="this.removeAttribute('readonly')">
                    </div>
                    <div class="form-group">
                        <label for="adminUserPassword">Password</label>
                        <input type="password" name="password" id="adminUserPassword" 
                               placeholder="Password (min 8 characters)" required minlength="8"
                               autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="adminUserRole">Role</label>
                        <select name="role" id="adminUserRole" required>
                            <option value="moderator" <?= (isset($_POST['role']) && $_POST['role'] === 'moderator') ? 'selected' : '' ?>>Moderator</option>
                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                                <option value="superadmin" <?= (isset($_POST['role']) && $_POST['role'] === 'superadmin') ? 'selected' : '' ?>>Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_admin" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Add Admin User
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Moderators don't have permission to add admin users.
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
    <h2><i class="fas fa-users"></i> Admin Users List</h2>
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search admins...">
    </div>
    <table id="adminTable">
        <thead>
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Role</th>
            <th>Created</th>
            <th>Last Login</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <span class="badge badge-<?= $row['role'] ?>">
                            <?= ucfirst($row['role']) ?>
                        </span>
                        <?php if ($_SESSION['admin_id'] != $row['id'] && $_SESSION['admin_role'] === 'admin'): ?>
                            <div class="role-dropdown">
                                <select class="role-select" data-user-id="<?= $row['id'] ?>">
                                    <option value="moderator" <?= $row['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                    <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                    <td>
                        <?= $row['last_login'] ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never' ?>
                    </td>
                    <td>
                        <?php if ($_SESSION['admin_id'] != $row['id'] && $_SESSION['admin_role'] !== 'moderator'): ?>
                            <button class="reset-btn" onclick="openResetModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['email']) ?>')">
                                <i class="fas fa-key"></i> Reset
                            </button>
                            <form method="get" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin and all their related data?')">
                                <input type="hidden" name="delete" value="<?= $row['id'] ?>">
                                <button type="submit" class="delete-btn">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        <?php elseif ($_SESSION['admin_id'] == $row['id']): ?>
                            <span class="tooltip">
                                <i class="fas fa-info-circle"></i> Current user
                                <span class="tooltiptext">You cannot modify your own account from here</span>
                            </span>
                        <?php else: ?>
                            <span class="tooltip">
                                <i class="fas fa-ban"></i> No permission
                                <span class="tooltiptext">You don't have permission to modify this account</span>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No admin users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

    <div class="section">
        <h2><i class="fas fa-sign-in-alt"></i> Admin Login Logs</h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Login Time</th>
                <th>IP Address</th>
                <th>User Agent</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($result_logs && $result_logs->num_rows > 0): ?>
                <?php while ($log = $result_logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['id']) ?></td>
                        <td><?= htmlspecialchars($log['email']) ?></td>
                        <td><?= date('M d, Y H:i', strtotime($log['login_time'])) ?></td>
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td class="user-agent" title="<?= htmlspecialchars($log['user_agent']) ?>">
                            <?= htmlspecialchars($log['user_agent']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No login logs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2><i class="fas fa-history"></i> Recent Admin Activities</h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Timestamp</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($activity_logs && $activity_logs->num_rows > 0): ?>
                <?php while ($activity = $activity_logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($activity['id']) ?></td>
                        <td><?= htmlspecialchars($activity['email']) ?></td>
                        <td><?= htmlspecialchars($activity['action']) ?></td>
                        <td><?= date('M d, Y H:i', strtotime($activity['timestamp'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No activity logs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="/admin" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<!-- Password Reset Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeResetModal()">&times;</span>
        <h2>Reset Password</h2>
        <p>Reset password for: <strong id="resetEmail"></strong></p>
        <form method="post" id="resetForm">
            <input type="hidden" name="admin_id" id="resetAdminId">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" placeholder="Enter new password (min 8 characters)" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" name="reset_password" class="btn-primary">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>
    </div>
</div>
<script ref="/assets/js/admin-users.php"></script>
</body>
</html>