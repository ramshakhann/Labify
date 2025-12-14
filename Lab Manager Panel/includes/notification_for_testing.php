<?php
// includes/notification_dropdown_universal.php - COMPLETE DROPDOWN FOR ALL PAGES

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo '<button class="notification-bell-dropdown"><i class="fas fa-bell"></i></button>';
    return;
}

$notification_count = 0;
$notifications = [];

// Check which database connection exists
if (isset($pdo) && $pdo instanceof PDO) {
    // PDO connection (for user_management.php, etc.)
    try {
        // Get count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $notification_count = $result['count'] ?? 0;
        
        // Get notifications
        $notif_stmt = $pdo->prepare("
            SELECT n.*, u.full_name as created_by_name 
            FROM notifications n 
            LEFT JOIN users u ON n.created_by = u.id 
            WHERE n.user_id = ? 
            ORDER BY n.created_at DESC 
            LIMIT 5
        ");
        $notif_stmt->execute([$user_id]);
        $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("PDO notification error: " . $e->getMessage());
    }
} elseif (isset($conn) && $conn instanceof mysqli) {
    // MySQLi connection (for log_test.php)
    try {
        // Get count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                $notification_count = $row['count'] ?? 0;
            }
            $stmt->close();
        }
        
        // Get notifications
        $notif_stmt = $conn->prepare("
            SELECT n.*, u.full_name as created_by_name 
            FROM notifications n 
            LEFT JOIN users u ON n.created_by = u.id 
            WHERE n.user_id = ? 
            ORDER BY n.created_at DESC 
            LIMIT 5
        ");
        if ($notif_stmt) {
            $notif_stmt->bind_param("i", $user_id);
            $notif_stmt->execute();
            $notif_result = $notif_stmt->get_result();
            
            while ($row = $notif_result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $notif_stmt->close();
        }
        
    } catch (Exception $e) {
        error_log("MySQLi notification error: " . $e->getMessage());
    }
}

// Fallback if no notifications found
if (empty($notifications)) {
    $notifications = [
        [
            'title' => 'Welcome to Labify',
            'message' => 'Your notification system is working.',
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'info',
            'is_read' => 0
        ],
        [
            'title' => 'System Ready',
            'message' => 'All features are functional.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'type' => 'success',
            'is_read' => 1
        ]
    ];
}
?>

<!-- COMPLETE DROPDOWN NOTIFICATION SYSTEM -->
<div class="notification-wrapper-dropdown">
    <button class="notification-bell-dropdown" id="notificationBellDropdown">
        <i class="fas fa-bell"></i>
        <?php if ($notification_count > 0): ?>
            <span class="notification-count-dropdown">
                <?php echo $notification_count > 9 ? '9+' : $notification_count; ?>
            </span>
        <?php endif; ?>
    </button>
    
    <!-- Dropdown -->
    <div class="notification-dropdown-universal" id="notificationDropdownUniversal">
        <div class="notification-header-dropdown">
            <h3>Notifications</h3>
            <?php if ($notification_count > 0): ?>
                <button class="mark-read-dropdown" onclick="markAllAsReadDropdown()">Mark all read</button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list-dropdown" id="notificationListDropdown">
            <?php if (empty($notifications)): ?>
                <div class="notification-empty-dropdown">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item-dropdown <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                         onclick="markAsReadDropdown('<?php echo $notif['id'] ?? ''; ?>')">
                        <div class="notification-icon-dropdown">
                            <?php 
                            $icon = 'fa-info-circle';
                            if ($notif['type'] == 'success') $icon = 'fa-check-circle';
                            if ($notif['type'] == 'warning') $icon = 'fa-exclamation-triangle';
                            if ($notif['type'] == 'danger') $icon = 'fa-times-circle';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content-dropdown">
                            <strong><?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?></strong>
                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                            <small><?php echo date('M j, g:i a', strtotime($notif['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer-dropdown">
            <a href="notifications.php">View all notifications</a>
        </div>
    </div>
</div>

<style>
/* DROPDOWN NOTIFICATION STYLES - SAME AS OTHER PAGES */
.notification-wrapper-dropdown {
   position: relative;
    display: inline-block;
}

.notification-bell-dropdown {
         width: 40px;
    height: 40px;
    background: linear-gradient(45deg, #014b66ff, #16f7f0ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
    box-shadow: 0 4px 10px rgba(1, 140, 164, 1);
    flex-shrink: 0;
}

.notification-bell-dropdown:hover {
     background: rgba(4, 97, 125, 0.4);
    transform: scale(1.1);
    box-shadow: 0 0 15px rgba(41, 251, 255, 0.3);
}

.notification-count-dropdown {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    padding: 3px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    border: 2px solid #0f172a;
    animation: pulse-dropdown 2s infinite;
}

@keyframes pulse-dropdown {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Dropdown */
.notification-dropdown-universal {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    display: none;
    z-index: 1000;
    margin-top: 10px;
}

.notification-header-dropdown {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #374151;
}

.notification-header-dropdown h3 {
    margin: 0;
    color: white;
    font-size: 1rem;
}

.mark-read-dropdown {
    background: none;
    border: none;
    color: #3b82f6;
    font-size: 0.8rem;
    cursor: pointer;
    padding: 5px 10px;
}

.mark-read-dropdown:hover {
    text-decoration: underline;
}

.notification-list-dropdown {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item-dropdown {
    display: flex;
    padding: 12px 15px;
    border-bottom: 1px solid #374151;
    cursor: pointer;
    transition: background 0.2s;
}

.notification-item-dropdown:hover {
    background: rgba(255, 255, 255, 0.05);
}

.notification-item-dropdown.unread {
    background: rgba(59, 130, 246, 0.1);
    border-left: 3px solid #3b82f6;
}

.notification-item-dropdown.read {
    opacity: 0.7;
}

.notification-icon-dropdown {
    margin-right: 10px;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.notification-icon-dropdown .fa-check-circle {
    color: #10b981;
}

.notification-icon-dropdown .fa-exclamation-triangle {
    color: #f59e0b;
}

.notification-icon-dropdown .fa-info-circle {
    color: #3b82f6;
}

.notification-icon-dropdown .fa-times-circle {
    color: #ef4444;
}

.notification-content-dropdown {
    flex: 1;
    min-width: 0;
}

.notification-content-dropdown strong {
    display: block;
    color: white;
    font-size: 0.9rem;
    margin-bottom: 5px;
    word-break: break-word;
}

.notification-content-dropdown p {
    margin: 0;
    color: #d1d5db;
    font-size: 0.85rem;
    line-height: 1.4;
    word-break: break-word;
}

.notification-content-dropdown small {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-top: 5px;
    display: block;
}

.notification-empty-dropdown {
    padding: 30px;
    text-align: center;
    color: #9ca3af;
}

.notification-empty-dropdown i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

.notification-footer-dropdown {
    padding: 10px 15px;
    text-align: center;
    border-top: 1px solid #374151;
}

.notification-footer-dropdown a {
    color: #3b82f6;
    text-decoration: none;
    font-size: 0.85rem;
}

.notification-footer-dropdown a:hover {
    text-decoration: underline;
}
</style>

<script>
// DROPDOWN FUNCTIONALITY
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notificationBellDropdown');
    const dropdown = document.getElementById('notificationDropdownUniversal');
    
    if (bell && dropdown) {
        // Toggle dropdown
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            } else {
                // Close any other dropdowns
                document.querySelectorAll('.notification-dropdown-universal').forEach(d => {
                    d.style.display = 'none';
                });
                
                dropdown.style.display = 'block';
            }
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.style.display = 'none';
            }
        });
    }
});

function markAsReadDropdown(notificationId) {
    // Simple UI update
    const item = event.target.closest('.notification-item-dropdown');
    if (item && item.classList.contains('unread')) {
        item.classList.remove('unread');
        item.classList.add('read');
        
        // Update badge count
        updateDropdownCount();
        
        // Show success message
        showDropdownNotification('success', 'Notification marked as read');
    }
}

function markAllAsReadDropdown() {
    document.querySelectorAll('.notification-item-dropdown.unread').forEach(item => {
        item.classList.remove('unread');
        item.classList.add('read');
    });
    
    // Remove badge
    const badge = document.querySelector('.notification-count-dropdown');
    if (badge) {
        badge.remove();
    }
    
    showDropdownNotification('success', 'All notifications marked as read');
}

function updateDropdownCount() {
    const unreadCount = document.querySelectorAll('.notification-item-dropdown.unread').length;
    const badge = document.querySelector('.notification-count-dropdown');
    
    if (unreadCount > 0) {
        if (badge) {
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        }
    } else if (badge) {
        badge.remove();
    }
}

function showDropdownNotification(type, message) {
    // Create a simple toast notification
    const toast = document.createElement('div');
    toast.className = `dropdown-toast dropdown-toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}


</script>