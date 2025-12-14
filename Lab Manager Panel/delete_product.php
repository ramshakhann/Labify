<?php
require_once 'includes/db_connect.php';

 $response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
    $product_id = $conn->real_escape_string($_POST['product_id']);
    
    // First, delete related test results (if you have foreign key constraints, you might need to handle this differently)
    $conn->query("DELETE FROM test_results WHERE product_id = '$product_id'");

    // Then, delete the product
    $sql = "DELETE FROM products WHERE product_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $product_id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Product deleted successfully.";
        } else {
            $response['message'] = "Error deleting product.";
        }
        $stmt->close();
    }
} else {
    $response['message'] = "Invalid request.";
}

 $conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>