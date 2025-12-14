<?php
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

 $response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {
    $equipmentId = $_POST['equipment_id'];
    
    try {
        // Check if equipment exists
        $checkStmt = $conn->prepare("SELECT id FROM lab_equipment WHERE id = ?");
        $checkStmt->bind_param("i", $equipmentId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = "Equipment not found";
        } else {
            // Delete the equipment
            $deleteStmt = $conn->prepare("DELETE FROM lab_equipment WHERE id = ?");
            $deleteStmt->bind_param("i", $equipmentId);
            
            if ($deleteStmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Equipment deleted successfully";
            } else {
                $response['message'] = "Failed to delete equipment: " . $deleteStmt->error;
            }
            $deleteStmt->close();
        }
        $checkStmt->close();
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
} else {
    $response['message'] = "Invalid request";
}

echo json_encode($response);
?>