<?php
// update_equipment.php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
$equipment_name = isset($_POST['equipment_name']) ? trim($_POST['equipment_name']) : '';
$equipment_type = isset($_POST['equipment_type']) ? trim($_POST['equipment_type']) : '';
$model = isset($_POST['model']) ? trim($_POST['model']) : '';
$serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Operational';
$last_maintenance = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
$next_maintenance = !empty($_POST['next_maintenance']) ? $_POST['next_maintenance'] : null;
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if (empty($equipment_id) || empty($equipment_name)) {
    echo json_encode(['success' => false, 'message' => 'Equipment ID and name are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        UPDATE lab_equipment 
        SET 
            equipment_name = ?,
            equipment_type = ?,
            model = ?,
            serial_number = ?,
            status = ?,
            last_maintenance = ?,
            next_maintenance = ?,
            location = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "sssssssssi",
        $equipment_name,
        $equipment_type,
        $model,
        $serial_number,
        $status,
        $last_maintenance,
        $next_maintenance,
        $location,
        $notes,
        $equipment_id
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Equipment updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update equipment: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>