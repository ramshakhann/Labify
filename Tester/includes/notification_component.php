<?php
// Simple notification component that works independently
global $pdo;
 $user_id = $_SESSION['user_id'] ?? null;

// Get notification count
 $notification_count = 0;
if ($user_id && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $notification_count = $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting notification count: " . $e->getMessage());
    }
}
?>

<div class="notification-wrapper" id="notificationBell">
    <div class="bell-container" onclick="toggleNotificationDropdown()">
        <i class="fas fa-bell"></i>
        <?php if ($notification_count > 0): ?>
            <span class="notification-badge"><?php echo $notification_count; ?></span>
        <?php endif; ?>
    </div>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button onclick="markAllAsRead()">Mark all as read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <div class="notification-item loading">
                <i class="fas fa-spinner fa-spin"></i> Loading notifications...
            </div>
        </div>
        <div class="notification-footer">
            <a href="notifications.php">View all notifications</a>
        </div>
    </div>
</div>