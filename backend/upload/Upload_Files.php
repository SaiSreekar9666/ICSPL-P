<?php
session_start();

// Auto logout logic
$timeout_duration = 1200; // 20 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: backend/login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check login
if (!isset($_SESSION["admin"])) {
    header("Location: login");
    exit();
}
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: /logout");
    exit();
}

$message = "";

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $index = $_POST['file_index'];
    $jsonFile = 'blog_data.json';
    
    if (file_exists($jsonFile)) {
        $allBlogs = json_decode(file_get_contents($jsonFile), true) ?: [];
        
        if (isset($allBlogs[$index])) {
            // Delete associated image if exists
            if (!empty($allBlogs[$index]['image'])) {
                @unlink($allBlogs[$index]['image']);
            }
            
            array_splice($allBlogs, $index, 1);
            file_put_contents($jsonFile, json_encode($allBlogs, JSON_PRETTY_PRINT));
            $message = "✅ File deleted successfully!";
        }
    }
}

// Handle file upload
if (isset($_POST['upload']) && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Create uploads directory if it doesn't exist
    if (!file_exists('uploads')) {
        mkdir('uploads', 0755, true);
    }
    
    // Supported file types
    $supportedTypes = ['txt', 'md', 'json', 'docx', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls'];
    
    if (!in_array($ext, $supportedTypes)) {
        $message = "❌ Unsupported file type.";
    } else {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $imagePath = '';
        
        // Handle image uploads
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imagePath = 'uploads/' . uniqid() . '.' . $ext;
            move_uploaded_file($file, $imagePath);
        }
        
        $blog = [
            "title" => $title,
            "timestamp" => date('Y-m-d H:i:s'),
            "file_type" => $ext,
            "original_filename" => $filename,
            "image" => $imagePath
        ];
        
        $jsonFile = 'blog_data.json';
        $allBlogs = [];
        
        if (file_exists($jsonFile)) {
            $allBlogs = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($allBlogs)) $allBlogs = [];
        }
        
        array_unshift($allBlogs, $blog);
        file_put_contents($jsonFile, json_encode($allBlogs, JSON_PRETTY_PRINT));
        $message = "✅ File uploaded successfully!";
        
        // Clear the message after 3 seconds
        echo '<script>
            setTimeout(function() {
                var msg = document.querySelector(".message");
                if (msg) msg.style.display = "none";
            }, 3000);
        </script>';
    }
}

// Read existing files for display
$allBlogs = [];
if (file_exists('blog_data.json')) {
    $allBlogs = json_decode(file_get_contents('blog_data.json'), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Management - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            padding-bottom: 50px;
        }
        .header {
            background-color: #1e293b;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #334155;
            flex-wrap: wrap;
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .admin-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #94a3b8;
        }
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #f8fafc;
            text-decoration: none;
            font-weight: 600;
            position: relative;
            padding: 10px 6px;
            transition: color 0.3s ease;
        }
        .nav-links a::after {
            content: "";
            position: absolute;
            width: 0%;
            height: 3px;
            bottom: 0;
            left: 0;
            background-color: #3b82f6;
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        .nav-links a:hover {
            color: #38bdf8;
        }
        .nav-links a:hover::after {
            width: 100%;
        }
        .nav-links .logout-btn {
            background-color: #ef4444;
            color: white !important;
            padding: 10px 16px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .nav-links .logout-btn:hover {
            background-color: #dc2626;
        }
        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .upload-container {
            background-color: #1e293b;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.5);
            margin-bottom: 40px;
        }
        h1, h2 {
            color: #e2e8f0;
            margin-bottom: 25px;
        }
        h1 {
            font-size: 32px;
            text-align: center;
        }
        h2 {
            font-size: 24px;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }
        input[type="file"] {
            background-color: #0f172a;
            color: #f8fafc;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #334155;
            width: 100%;
        }
        input[type="submit"], button[type="submit"] {
            background-color: #3b82f6;
            color: white;
            font-weight: 600;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 16px;
            width: 100%;
        }
        input[type="submit"]:hover, button[type="submit"]:hover {
            background-color: #2563eb;
        }
        .message {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
            transition: opacity 0.5s ease;
        }
        .success {
            background-color: #14532d;
            color: #4ade80;
        }
        .error {
            background-color: #7f1d1d;
            color: #f87171;
        }
        .file-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .file-item {
            background-color: #1e293b;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            position: relative;
        }
        .file-item:hover {
            transform: translateY(-5px);
        }
        .file-item h3 {
            color: #38bdf8;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .file-item p {
            margin-bottom: 8px;
            color: #94a3b8;
        }
        .file-item strong {
            color: #e2e8f0;
        }
        .file-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
        }
        .file-actions button {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s;
        }
        .file-actions button:hover {
            color: #f8fafc;
        }
        .file-actions .delete-btn:hover {
            color: #ef4444;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            background-color: #1e293b;
            border-radius: 12px;
            color: #94a3b8;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
            .nav-links {
                gap: 15px;
            }
            .file-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="admin-info">
        <img src="https://img.freepik.com/premium-photo/3d-sales-manager-character-leading-with-animated-ambition_893571-11254.jpg" alt="Admin">
        <strong>Welcome, <?php echo htmlspecialchars($_SESSION["admin"] ?? 'Admin'); ?></strong>
    </div>
    <nav class="nav-links">
        <a href="/admin">Dashboard</a>
        <a href="/upload">Uploaded Files</a>
        <a href="/users">User List</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </nav>
</div>

<div class="main-container">
    <h1>File Management</h1>
    
    <div class="upload-container">
        <h2>📁 Upload a File</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required accept=".txt,.md,.json,.docx,.pdf,.jpg,.jpeg,.png,.gif,.xlsx,.xls" />
            <input type="submit" name="upload" value="Upload">
        </form>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="file-list-container">
        <h2>📂 Uploaded Files</h2>
        <?php if (empty($allBlogs)): ?>
            <div class="empty-state">
                <p>No files uploaded yet.</p>
            </div>
        <?php else: ?>
            <div class="file-list">
                <?php foreach ($allBlogs as $index => $blog): ?>
                    <div class="file-item">
                        <div class="file-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="file_index" value="<?= $index ?>">
                                <button type="submit" name="delete_file" class="delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        <h3><?= htmlspecialchars($blog['original_filename'] ?? 'Untitled') ?></h3>
                        <p><strong>Type:</strong> <?= strtoupper($blog['file_type'] ?? 'UNKNOWN') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>