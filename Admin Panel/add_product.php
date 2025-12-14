<?php
// This script handles the AJAX request for adding a new product.

// Start the session and include the database connection
session_start();
require_once 'includes/db_connect.php';

// Initialize a response array
 $response = ['success' => false, 'message' => '', 'product' => null];

// Check if the request is a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize and validate input
    $product_id = trim($_POST["product_id"]);
    $product_type = trim($_POST["product_type"]);
    $revision = trim($_POST["revision"]);
    $created_at = $_POST['created_at'];

    if (empty($product_id) || !preg_match('/^[A-Za-z0-9]{10}$/', $product_id)) {
        $response['message'] = "Product ID must be exactly 10 letters or digits.";
    } elseif (empty($product_type)) {
        $response['message'] = "Product Type is required.";
    } elseif (empty($revision)) {
        $response['message'] = "Revision is required.";
    } elseif (empty($created_at)) {
        $response['message'] = "Manufacture Date is required.";
    } else {
        // All checks passed, prepare to insert into the database
        $sql = "INSERT INTO products (product_id, product_type, revision, created_at) VALUES (?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $product_id, $product_type, $revision, $created_at);
            
            if ($stmt->execute()) {
                // Success!
                $response['success'] = true;
                $response['message'] = "Product added successfully!";
                // Send back the new product data to add to the table
                $response['product'] = [
                    'product_id' => $product_id,
                    'product_type' => $product_type,
                    'revision' => $revision,
                    'created_at' => date('Y-m-d H:i:s', strtotime($created_at)) // Format for display
                ];
            } else {
                // Handle database errors
                if ($conn->errno == 1062) {
                    $response['message'] = 'Duplicate Product ID. This product already exists.';
                } else {
                    $response['message'] = "Database error: Could not add product. " . $stmt->error;
                }
            }
            $stmt->close();
        } else {
            $response['message'] = "Database error: Could not prepare the statement.";
        }
    }
} else {
    $response['message'] = "Invalid request method.";
}

// Close the database connection
 $conn->close();

// Send the response back as JSON. This is the crucial part for AJAX.
header('Content-Type: application/json');
echo json_encode($response);
?>