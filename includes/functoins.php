<?php
// Authentication and permission functions
function hasPermission($requiredRole) {
    if (!isset($_SESSION['admin_role'])) return false;
    
    $roleHierarchy = [
        'superadmin' => 3,
        'admin' => 2,
        'moderator' => 1
    ];
    
    $userRole = $_SESSION['admin_role'];
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

function redirectIfNotLoggedIn() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: /login");
        exit();
    }
}

function redirectIfNotAuthorized($requiredRole) {
    redirectIfNotLoggedIn();
    if (!hasPermission($requiredRole)) {
        header("Location: /unauthorized");
        exit();
    }
}

// CSRF protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Logging
function logAdminAction($admin_id, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
    $stmt->close();
}
?>