// At the top of users.php
if ($_SESSION['admin_role'] === 'moderator') {
    // Disable user deletion/add for moderators
    if (isset($_GET['delete'])) {
        die("You don't have permission to delete users.");
    }
    
    // Similarly for other sensitive actions
}

// When deleting a regular user, clean up related data:
if (isset($_GET['delete']) && $_SESSION['admin_role'] !== 'moderator') {
    $user_id = intval($_GET['delete']);
    
    $conn->begin_transaction();
    try {
        // Get user email first for logging
        $email = $conn->query("SELECT email FROM users WHERE id = $user_id")->fetch_assoc()['email'];
        
        // Delete user files or set them to orphaned status
        $conn->query("UPDATE files SET user_id = NULL WHERE user_id = $user_id");
        
        // Delete user sessions
        $conn->query("DELETE FROM user_sessions WHERE user_id = $user_id");
        
        // Delete the user
        $conn->query("DELETE FROM users WHERE id = $user_id");
        
        // Log the action
        $admin_id = $_SESSION["admin_id"];
        $action = "Deleted user: $email";
        $conn->query("INSERT INTO admin_activity_logs (admin_id, action) VALUES ($admin_id, '$action')");
        
        $conn->commit();
        header("Location: /users?deleted=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Failed to delete user: " . $e->getMessage());
    }
}