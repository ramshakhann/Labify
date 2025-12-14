<?php
// Include database connection
require_once '../auth.php';

 $host = 'localhost';
 $dbname = 'labify';
 $username = 'root';
 $password = '';
 $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

// Set response header
header('Content-Type: application/json');

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $testId = $_POST['testId'] ?? '';
    
    // Validate input
    if (empty($testId)) {
        echo json_encode(['success' => false, 'message' => 'Test ID is required']);
        exit;
    }
    
    try {
        // Check if test type is being used in test results
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM test_results WHERE test_type_id = ?");
        $stmt->execute([$testId]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete test type that is in use']);
            exit;
        }
        
        // Delete test type
        $stmt = $pdo->prepare("DELETE FROM test_types WHERE id = ?");
        $stmt->execute([$testId]);
        
        echo json_encode(['success' => true, 'message' => 'Test type deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>