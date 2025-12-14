<?php
// ajax/get_mobile_notifications.php
session_start();
require_once '../db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    // Get notifications
    $stmt = $conn->prepare("
        SELECT n.*, 
               u.full_name as created_by_name,
               DATE_FORMAT(n.created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM notifications n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT ?
    ");
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'icon' => $row['icon'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'created_by_name' => $row['created_by_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => count($notifications)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading notifications',
        'error' => $e->getMessage()
    ]);
}
?>