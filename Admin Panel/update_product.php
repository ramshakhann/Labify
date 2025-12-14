<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
 $test_id = $_POST['test_id'] ?? '';
 $product_id = $_POST['product_id'] ?? '';
 $type = $_POST['type'] ?? '';
 $revision = $_POST['revision'] ?? '';
 $created_at = $_POST['created_at'] ?? '';
 $test_result = $_POST['result'] ?? ''; // Renamed to avoid conflict
 $status = $_POST['status'] ?? '';
 $remarks = $_POST['remarks'] ?? '';

 $response = ['success' => false, 'message' => ''];

if (empty($test_id) && empty($product_id)) {
    $response['message'] = 'No test ID or product ID provided';
    echo json_encode($response);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Update test_results if we have a valid test_id
    if (!empty($test_id) && $test_id !== 'product_only') {
        // First, get the product_id if not provided
        if (empty($product_id)) {
            $stmt = $conn->prepare("SELECT product_id FROM test_results WHERE test_id = ?");
            $stmt->bind_param("s", $test_id); // FIXED: Changed from "i" to "s"
            $stmt->execute();
            $result_data = $stmt->get_result();
            
            if ($result_data->num_rows === 0) {
                $response['message'] = 'Test not found';
                echo json_encode($response);
                exit;
            }
            
            $test_data = $result_data->fetch_assoc();
            $product_id = $test_data['product_id'];
            $stmt->close();
        }
        
        // Update test_results - FIXED: Changed bind type to "s" for test_id
        $update_test = $conn->prepare("UPDATE test_results SET result = ?, status = ?, remarks = ? WHERE test_id = ?");
        $update_test->bind_param("ssss", $test_result, $status, $remarks, $test_id);
        
        if (!$update_test->execute()) {
            throw new Exception("Error updating test: " . $update_test->error);
        }
        $update_test->close();
    }
    
    // Update the product table if we have product data
    if (!empty($product_id) && !empty($type)) {
        $update_product = $conn->prepare("UPDATE products SET product_type = ?, revision = ?, created_at = ? WHERE product_id = ?");
        $update_product->bind_param("ssss", $type, $revision, $created_at, $product_id);
        
        if (!$update_product->execute()) {
            throw new Exception("Error updating product: " . $update_product->error);
        }
        $update_product->close();
    }
    
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Update successful';
    
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Database error: " . $e->getMessage();
}

 $conn->close();
echo json_encode($response);
?>