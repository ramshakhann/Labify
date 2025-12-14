<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['test_id'])) {
    $test_id = trim($_POST['test_id']);
    
    // Delete the test record from database
    $sql = "DELETE FROM test_results WHERE test_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $test_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Test record deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>