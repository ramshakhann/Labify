<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['test_id'])) {
    $test_id = trim($_POST['test_id']);
    $status = trim($_POST['status']);
    $result = trim($_POST['result']);
    $remarks = trim($_POST['remarks']);
    
    // Update the test record in database
    $sql = "UPDATE test_results SET status = ?, result = ?, remarks = ? WHERE test_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssss", $status, $result, $remarks, $test_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Test record updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>