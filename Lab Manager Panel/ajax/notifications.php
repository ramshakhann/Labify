<?php
// ajax/notifications.php - SIMPLIFIED VERSION
session_start();
require_once '../includes/db_connect.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            if (isset($_POST['notification_id'])) {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? ");
                $stmt->bind_param("ii", $_POST['notification_id'], $user_id);
                $success = $stmt->execute();
                echo json_encode(['success' => $success]);
            }
            break;
            
        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE is_read = FALSE");
            $stmt->bind_param("i", $user_id);
            $success = $stmt->execute();
            echo json_encode(['success' => $success]);
            break;
            
        case 'get_unread_count':
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE  is_read = FALSE");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            echo json_encode(['count' => $row['count'] ?? 0]);
            break;
    }
    exit();
}

// GET request - get notifications
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $unreadOnly = isset($_GET['unread']) && $_GET['unread'] == 'true';
    
    $where = $unreadOnly ? "AND n.is_read = FALSE" : "";
    
    $stmt = $conn->prepare("
        SELECT n.*, 
               u.username as created_by_name,
               u.full_name as created_by_fullname
        FROM notifications n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE n.user_id = ? 
        AND (n.expires_at IS NULL OR n.expires_at > NOW())
        $where
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE  is_read = FALSE");
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $countRow['count'] ?? 0
    ]);
}
?>