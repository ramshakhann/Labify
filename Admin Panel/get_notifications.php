<?php
session_start();
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get notifications for this user
$query = "SELECT * FROM notifications 
          WHERE user_id = ? 
          ORDER BY is_read ASC, created_at DESC 
          LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['type'],
        'is_read' => (bool)$row['is_read'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications
]);
?>