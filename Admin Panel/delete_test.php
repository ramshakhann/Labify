<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit;
}

// Get test_id from POST
$test_id = $_POST['test_id'] ?? '';

if (empty($test_id)) {
    $response['message'] = 'No test ID provided';
    echo json_encode($response);
    exit;
}

try {
    // Delete the test record
    $stmt = $conn->prepare("DELETE FROM test_results WHERE test_id = ?");
    $stmt->bind_param("s", $test_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Test record deleted successfully!';
    } else {
        $response['message'] = 'Error deleting test: ' . $stmt->error;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>