<?php
$required_files = [
    '/public/index.html',
    '/public/pages/about.html',
    '/public/pages/contact.html',
    '/backend/auth/login.php',
    '/backend/admin/admin.php',
    '/public/pages/error.html'
];

echo "File Existence Check:\n";
echo str_repeat("=", 50) . "\n";

$missing_files = 0;
foreach ($required_files as $file) {
    $full_path = __DIR__ . $file;
    $exists = file_exists($full_path);
    
    if (!$exists) $missing_files++;
    
    echo sprintf("%-30s %-10s %s\n",
        $file,
        $exists ? 'EXISTS' : 'MISSING',
        $exists ? round(filesize($full_path)/1024, 2).' KB' : ''
    );
}

echo str_repeat("=", 50) . "\n";
echo "Missing files: $missing_files/".count($required_files)."\n";
echo str_repeat("=", 50) . "\n";