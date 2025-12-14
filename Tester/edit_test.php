<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json'); // Set header for JSON response

 $response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    
    $id = $_POST['id'];
    $result = $_POST['result'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];

    $sql = "UPDATE test_results SET result = ?, status = ?, remarks = ? WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssi", $result, $status, $remarks, $id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Test record updated successfully!";
        } else {
            $response['message'] = "Error: Could not update the record. " . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = "Error: Failed to prepare the update statement.";
    }
} else {
    $response['message'] = "Error: Invalid request.";
}

echo json_encode($response);
?>