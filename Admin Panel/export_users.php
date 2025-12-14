<?php
// =================================================================
// USER DATA EXPORT
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

// --- 3. GET EXPORT TYPE ---
 $exportType = isset($_GET['type']) ? $_GET['type'] : 'basic';

// --- 4. FETCH DATA BASED ON EXPORT TYPE ---
try {
    switch ($exportType) {
        case 'basic':
            // Basic user information
            $sql = "SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC";
            $stmt = $pdo->query($sql);
            $users = $stmt->fetchAll();
            
            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['ID', 'Name', 'Email', 'Role', 'Member Since'];
            
            foreach ($users as $user) {
                $csvData[] = [
                    $user['id'],
                    $user['full_name'],
                    $user['email'],
                    ucfirst($user['role']),
                    date('Y-m-d', strtotime($user['created_at']))
                ];
            }
            
            $filename = 'users_basic_' . date('Y-m-d') . '.csv';
            break;
            
        case 'detailed':
            // Detailed user information with performance metrics
            $sql = "
                SELECT 
                    u.id, u.full_name, u.email, u.role, u.created_at,
                    COUNT(tr.id) AS total_tests,
                    AVG(TIMESTAMPDIFF(MINUTE, tr.start_time, tr.end_time)) AS avg_test_time,
                    MAX(tr.end_time) AS last_test_date
                FROM 
                    users u
                LEFT JOIN 
                    test_results tr ON u.id = tr.tester_id
                GROUP BY 
                    u.id, u.full_name, u.email, u.role, u.created_at
                ORDER BY 
                    u.created_at DESC
            ";
            $stmt = $pdo->query($sql);
            $users = $stmt->fetchAll();
            
            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['ID', 'Name', 'Email', 'Role', 'Member Since', 'Total Tests', 'Avg Test Time (min)', 'Last Test Date'];
            
            foreach ($users as $user) {
                $csvData[] = [
                    $user['id'],
                    $user['full_name'],
                    $user['email'],
                    ucfirst($user['role']),
                    date('Y-m-d', strtotime($user['created_at'])),
                    $user['total_tests'],
                    $user['avg_test_time'] ? round($user['avg_test_time'], 2) : 'N/A',
                    $user['last_test_date'] ? date('Y-m-d', strtotime($user['last_test_date'])) : 'N/A'
                ];
            }
            
            $filename = 'users_detailed_' . date('Y-m-d') . '.csv';
            break;
            
        case 'activities':
            // User activities
            $sql = "
                SELECT 
                    u.id, u.full_name, u.email, u.role,
                    tr.id AS test_id, tr.test_name, tr.start_time, tr.end_time,
                    TIMESTAMPDIFF(MINUTE, tr.start_time, tr.end_time) AS duration,
                    p.name AS product_name
                FROM 
                    users u
                LEFT JOIN 
                    test_results tr ON u.id = tr.tester_id
                LEFT JOIN 
                    products p ON tr.product_id = p.id
                ORDER BY 
                    u.id, tr.end_time DESC
            ";
            $stmt = $pdo->query($sql);
            $activities = $stmt->fetchAll();
            
            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['User ID', 'Name', 'Email', 'Role', 'Test ID', 'Test Name', 'Product', 'Start Time', 'End Time', 'Duration (min)'];
            
            foreach ($activities as $activity) {
                $csvData[] = [
                    $activity['id'],
                    $activity['full_name'],
                    $activity['email'],
                    ucfirst($activity['role']),
                    $activity['test_id'],
                    $activity['test_name'],
                    $activity['product_name'] ?? 'N/A',
                    $activity['start_time'] ? date('Y-m-d H:i:s', strtotime($activity['start_time'])) : 'N/A',
                    $activity['end_time'] ? date('Y-m-d H:i:s', strtotime($activity['end_time'])) : 'N/A',
                    $activity['duration'] ? $activity['duration'] : 'N/A'
                ];
            }
            
            $filename = 'user_activities_' . date('Y-m-d') . '.csv';
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
    // --- 5. GENERATE AND DOWNLOAD CSV ---
    // Set headers to force download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 encoding
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write CSV data
    foreach ($csvData as $row) {
        fputcsv($output, $row);
    }
    
    // Close output stream
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>