<?php
// Routes to test with expected status codes
$routes_to_test = [
    '/' => 200,
    '/about' => 200,
    '/contact' => 200,
    '/login' => 200,
    '/admin' => 200,
    '/nonexistent' => 404
];

echo "Testing Routes:\n";
echo str_repeat("=", 50) . "\n";
echo sprintf("%-20s %-10s %-10s %s\n", "Route", "Expected", "Actual", "Result");
echo str_repeat("-", 50) . "\n";

$passed = 0;
foreach ($routes_to_test as $route => $expected_status) {
    // Set up test environment
    $_SERVER['REQUEST_URI'] = $route;
    $_GET['test_mode'] = true;
    
    // Capture output
    ob_start();
    include 'router.php';
    $actual_status = http_response_code();
    ob_end_clean();
    
    // Determine test result
    $result = ($actual_status === $expected_status) ? 'PASS' : 'FAIL';
    if ($result === 'PASS') $passed++;
    
    echo sprintf("%-20s %-10d %-10d %s\n", 
        $route, 
        $expected_status, 
        $actual_status, 
        $result
    );
}

echo str_repeat("=", 50) . "\n";
echo "Test Results: $passed/".count($routes_to_test)." passed\n";
echo str_repeat("=", 50) . "\n";