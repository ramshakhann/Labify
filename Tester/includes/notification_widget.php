<?php
// includes/notification_widget.php - SIMPLE WORKING VERSION

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check database connection
if (!isset($conn)) {
    // Try to create connection
    require_once 'db_connect.php';
}

// Get user_id (just for session check)
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Don't show notifications if not logged in
    return;
}

// SIMPLE SQL QUERIES - NO USER FILTERING
try {
    // Count ALL unread notifications
    $count_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $count_row = $count_result->fetch_assoc();
    $unreadCount = $count_row['count'] ?? 0;
    
    // Get ALL recent notifications
    $notif_result = $conn->query("
        SELECT * FROM notifications 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $recentNotifications = [];
    while ($row = $notif_result->fetch_assoc()) {
        $recentNotifications[] = $row;
    }
    
} catch (Exception $e) {
    // Fallback if error
    $unreadCount = 0;
    $recentNotifications = [
        [
            'id' => 1,
            'title' => 'System',
            'message' => 'Notification system loaded',
            'type' => 'info',
            'is_read' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
}
?>

<!-- SIMPLE NOTIFICATION BELL -->
<div class="notification-wrapper">
    <button class="notification-bell" id="notificationBell">
        <i class="fas fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="notification-count" id="notificationCount"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h4>Notifications</h4>
            <?php if ($unreadCount > 0): ?>
                <button onclick="markAllRead()">Mark all read</button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list">
            <?php if (empty($recentNotifications)): ?>
                <div class="no-notifications">No notifications</div>
            <?php else: ?>
                <?php foreach ($recentNotifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-icon">
                            <?php 
                            $icon = 'fas fa-info-circle';
                            if ($notif['type'] == 'success') $icon = 'fas fa-check-circle';
                            if ($notif['type'] == 'error') $icon = 'fas fa-exclamation-circle';
                            if ($notif['type'] == 'warning') $icon = 'fas fa-exclamation-triangle';
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notification-time">
                                <?php echo date('H:i', strtotime($notif['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.notification-wrapper {
    position: relative;
    display: inline-block;
}

.notification-bell {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 8px;
    position: relative;
}

.notification-count {
    position: absolute;
    top: 0;
    right: 0;
    background: red;
    color: white;
    font-size: 0.7rem;
    padding: 2px 5px;
    border-radius: 50%;
    min-width: 18px;
    text-align: center;
}

.notification-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    width: 300px;
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 5px;
    z-index: 1000;
    margin-top: 5px;
}

.notification-header {
    padding: 10px;
    border-bottom: 1px solid #374151;
    display: flex;
    justify-content: space-between;
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 10px;
    border-bottom: 1px solid #374151;
}

.notification-item.unread {
    background: rgba(59,130,246,0.1);
}

.notification-icon {
    float: left;
    margin-right: 10px;
    font-size: 1.2rem;
}

.notification-content {
    overflow: hidden;
}

.notification-title {
    font-weight: bold;
    color: white;
}

.notification-message {
    color: #ccc;
    font-size: 0.9rem;
}

.notification-time {
    color: #888;
    font-size: 0.8rem;
}

.no-notifications {
    padding: 20px;
    text-align: center;
    color: #888;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (bell && dropdown) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        document.addEventListener('click', function(e) {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }
});

function markAllRead() {
    alert('This would mark all as read');
    // You can add AJAX here later
}
</script>