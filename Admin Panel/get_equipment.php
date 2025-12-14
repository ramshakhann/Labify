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

$equipment_id = $_GET['id'] ?? $_POST['id'] ?? '';

if (empty($equipment_id)) {
    $response['message'] = 'No equipment ID provided';
    echo json_encode($response);
    exit;
}

try {
    // Fetch equipment details
    $stmt = $conn->prepare("
        SELECT 
            id,
            equipment_name,
            equipment_type,
            serial_number,
            model,
            manufacturer,
            status,
            DATE_FORMAT(last_maintenance, '%Y-%m-%d') as last_maintenance,
            DATE_FORMAT(next_maintenance, '%Y-%m-%d') as next_maintenance,
            location,
            department,
            notes
        FROM lab_equipment 
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['success'] = true;
        $response['equipment'] = $result->fetch_assoc();
    } else {
        $response['message'] = 'Equipment not found';
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>