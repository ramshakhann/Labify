<?php
// =================================================================
// USER PROFILE DATA FETCHER
// =================================================================

// --- DEBUGGING: Turn on error reporting ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 1. AUTHENTICATION & SESSION ---
require_once '../auth.php';

// --- 2. DATABASE CONNECTION ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); // Replace with your DB username
define('DB_PASS', '');     // Replace with your DB password
define('DB_NAME', 'labify');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// --- 3. GET USER ID ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

 $userId = $_GET['id'];

// --- 4. FETCH USER PROFILE DATA ---
try {
    // Get basic user information
    $userSql = "SELECT id, full_name, email, role, created_at FROM users WHERE id = ?";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Get user test statistics
    $statsSql = "
        SELECT 
            COUNT(tr.id) AS total_tests,
            AVG(TIMESTAMPDIFF(MINUTE, tr.start_time, tr.end_time)) AS avg_test_time
        FROM 
            test_results tr
        WHERE 
            tr.tester_id = ?
    ";
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch();
    
    // Get recent tests
    $recentTestsSql = "
        SELECT 
            tr.id, tr.test_name, tr.start_time, tr.end_time,
            TIMESTAMPDIFF(MINUTE, tr.start_time, tr.end_time) AS duration,
            p.name AS product_name
        FROM 
            test_results tr
        LEFT JOIN 
            products p ON tr.product_id = p.id
        WHERE 
            tr.tester_id = ?
        ORDER BY 
            tr.end_time DESC
        LIMIT 10
    ";
    $recentTestsStmt = $pdo->prepare($recentTestsSql);
    $recentTestsStmt->execute([$userId]);
    $recentTests = $recentTestsStmt->fetchAll();
    
    // Prepare response data
    $profileData = [
        'id' => $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'member_since' => date('M j, Y', strtotime($user['created_at'])),
        'total_tests' => (int)$stats['total_tests'],
        'avg_test_time' => $stats['avg_test_time'] ? round($stats['avg_test_time'], 2) : null,
        'recent_tests' => $recentTests
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($profileData);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>