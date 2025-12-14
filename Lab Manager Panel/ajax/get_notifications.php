<?php
// ajax/get_notifications.php
session_start();
require_once '../includes/db_connect.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get recent notifications
$stmt = $conn->prepare("
    SELECT title, message, created_at 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'title' => $row['title'],
        'message' => $row['message'],
        'time' => timeAgo($row['created_at'])
    ];
}

echo json_encode(['notifications' => $notifications]);

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('M j, Y', $time);
}
?>