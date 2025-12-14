<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

 $notification_id = $_POST['id'] ?? '';
if (empty($notification_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>