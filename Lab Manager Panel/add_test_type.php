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
    $testCode = $_POST['testCode'] ?? '';
    $testName = $_POST['testName'] ?? '';
    
    // Validate input
    if (empty($testCode) || empty($testName)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit;
    }
    
    try {
        // Check if test code already exists
        $stmt = $pdo->prepare("SELECT id FROM test_types WHERE test_code = ?");
        $stmt->execute([$testCode]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Test code already exists']);
            exit;
        }
        
        // Insert new test type
        $stmt = $pdo->prepare("INSERT INTO test_types (test_code, test_name) VALUES (?, ?)");
        $stmt->execute([$testCode, $testName]);
        
        echo json_encode(['success' => true, 'message' => 'Test type added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>