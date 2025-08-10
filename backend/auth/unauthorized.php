<?php
require_once 'functions.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
</head>
<body>
    <div class="container">
        <h1>⛔ Access Denied</h1>
        <p>You don't have permission to access this page.</p>
        <p>Your role: <?= $_SESSION['admin_role'] ?? 'Not logged in' ?></p>
        <a href="/admin">Return to Dashboard</a>
    </div>
</body>
</html>