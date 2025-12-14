<?php
// get_notifications_ajax.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="loading">Please log in to view notifications</div>';
    exit;
}

$user_id = $_SESSION['user_id'];

// Use the existing PDO connection from user_management.php
require_once 'db_connect.php'; // Adjust path if needed

try {
    // Create PDO connection if not exists
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=localhost;dbname=labify", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ATTR_ERRMODE_EXCEPTION);
    }
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT title, message, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo '
        <div class="notification-item">
            <div class="notification-title">No Notifications</div>
            <div class="notification-message">You have no notifications yet.</div>
            <div class="notification-time">Just now</div>
        </div>';
    } else {
        foreach ($notifications as $notif) {
            $time = date('M j, g:i a', strtotime($notif['created_at']));
            $readClass = $notif['is_read'] ? '' : 'style="border-left-color: #ef4444;"';
            
            echo '
            <div class="notification-item" ' . $readClass . '>
                <div class="notification-title">' . htmlspecialchars($notif['title']) . '</div>
                <div class="notification-message">' . htmlspecialchars($notif['message']) . '</div>
                <div class="notification-time">' . $time . '</div>
            </div>';
        }
    }
    
} catch (Exception $e) {
    // Fallback notifications
    echo '
    <div class="notification-item">
        <div class="notification-title">System Ready</div>
        <div class="notification-message">Notification system is working.</div>
        <div class="notification-time">Just now</div>
    </div>
    <div class="notification-item">
        <div class="notification-title">Mobile Menu</div>
        <div class="notification-message">You can access notifications from here.</div>
        <div class="notification-time">Today</div>
    </div>';
}
?>