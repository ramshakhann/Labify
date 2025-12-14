<?php
// Prevent any HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start session
session_start();

// Include database with absolute path
require_once dirname(__DIR__) . '/includes/db_connect.php';

// Set JSON header BEFORE any output
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Debug log
error_log("Mark all notifications read called from: " . $_SERVER['REQUEST_URI']);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    
    $affected = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'affected' => $affected]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>