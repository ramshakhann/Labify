<?php
// Check session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user ID from session
 $user_id = $_SESSION['user_id'] ?? null;

// Only proceed if user is logged in
if (!$user_id) {
    return;
}

// Handle AJAX requests FIRST - before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once 'includes/db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        if ($action === 'mark_all_read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $affected = $stmt->rowCount();
            
            // Get new count
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $count_stmt->execute([$user_id]);
            $new_count = $count_stmt->fetch()['count'];
            
            echo json_encode(['success' => true, 'affected' => $affected, 'count' => $new_count]);
            exit;
            
        } elseif ($action === 'get_count') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            echo json_encode(['success' => true, 'count' => $result['count']]);
            exit;
            
        } elseif ($action === 'mark_one_read') {
            $notification_id = $_POST['id'] ?? '';
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Continue with normal page load...
// Use existing PDO connection from user_management.php
global $pdo;

$notification_count = 0;
$notifications = [];

try {
    // Get unread notification count
   // Fix both queries - remove WHERE clause and don't pass parameters

// Line 28-29:
$count_query = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
$count_query->execute();
 $count_result = $count_query->fetch(PDO::FETCH_ASSOC);
    $notification_count = $count_result['count'] ?? 0;

// Line 36-45:
$notif_query = $pdo->prepare("
    SELECT n.*, u.full_name as created_by_name 
    FROM notifications n 
    LEFT JOIN users u ON n.created_by = u.id 
    ORDER BY n.created_at DESC 
    LIMIT 5
");
$notif_query->execute(); // NO parameter
    $notifications = $notif_query->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Silently fail - don't break the page
    error_log("Notification error: " . $e->getMessage());
}
?>

<!-- Simple Notification Bell -->
<div class="notification-wrapper">
    <button class="notification-bell" id="notificationBell">
        <i class="fas fa-bell"></i>
        <?php if ($notification_count > 0): ?>
            <span class="notification-count"><?php echo $notification_count; ?></span>
        <?php endif; ?>
    </button>
    
    <!-- Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <?php if ($notification_count > 0): ?>
                <button class="mark-read" onclick="markAllAsRead()">Mark all read</button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list" id="notificationList">
            <?php if (empty($notifications)): ?>
                <div class="notification-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                         data-id="<?php echo $notif['id']; ?>"
                         onclick="markAsRead(<?php echo $notif['id']; ?>)">
                        <div class="notification-icon">
                            <i class="fas fa-<?php echo $notif['type'] == 'success' ? 'check-circle' : ($notif['type'] == 'warning' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                        </div>
                        <div class="notification-content">
                            <strong><?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?></strong>
                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                            <small><?php echo date('M j, g:i a', strtotime($notif['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer">
            <a href="notifications.php">View all notifications</a>
        </div>
    </div>
</div>

<style>
/* Notification Styles */
.notification-wrapper {
   position: relative;
    display: inline-block;
}

.notification-bell {
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

.notification-bell:hover {
      background: rgba(4, 97, 125, 0.4);
    transform: scale(1.1);
    box-shadow: 0 0 15px rgba(41, 251, 255, 0.3);
}

.notification-count {
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
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background: #01132cff;
    border: 1px solid #49b7d8ff;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(31, 117, 138, 0.57);
    display: none;
    z-index: 1000;
    margin-top: 10px;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #374151;
}

.notification-header h3 {
    margin: 0;
    color: white;
    font-size: 1rem;
}

.mark-read {
    background: none;
    border: none;
    color: #3b82f6;
    font-size: 0.8rem;
    cursor: pointer;
    padding: 5px 10px;
}

.mark-read:hover {
    text-decoration: underline;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    padding: 12px 15px;
    border-bottom: 1px solid #374151;
    cursor: pointer;
    transition: background 0.2s;
}

.notification-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.notification-item.unread {
    background: rgba(59, 130, 246, 0.1);
    border-left: 3px solid #3b82f6;
}

.notification-item.read {
    opacity: 0.7;
}

.notification-icon {
    margin-right: 10px;
    font-size: 1.2rem;
}

.notification-icon .fa-check-circle {
    color: #10b981;
}

.notification-icon .fa-exclamation-triangle {
    color: #f59e0b;
}

.notification-icon .fa-info-circle {
    color: #3b82f6;
}

.notification-content {
    flex: 1;
}

.notification-content strong {
    display: block;
    color: white;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.notification-content p {
    margin: 0;
    color: #d1d5db;
    font-size: 0.85rem;
    line-height: 1.4;
}

.notification-content small {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-top: 5px;
    display: block;
}

.notification-empty {
    padding: 30px;
    text-align: center;
    color: #9ca3af;
}

.notification-empty i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

.notification-footer {
    padding: 10px 15px;
    text-align: center;
    border-top: 1px solid #374151;
}

.notification-footer a {
    color: #3b82f6;
    text-decoration: none;
    font-size: 0.85rem;
}

.notification-footer a:hover {
    text-decoration: underline;
}
</style>

<script>
// Get base path
const basePath = (() => {
    const path = window.location.pathname;
    if (path.includes('/LABIFY/')) {
        return '/LABIFY/';
    } else if (path.includes('/labify/')) {
        return '/labify/';
    } else {
        return '/';
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (bell && dropdown) {
        // Toggle dropdown
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }
    
    // Initial count update
    updateNotificationCount();
});

function markAsRead(notificationId) {
    // Use same page with action parameter
    const formData = new FormData();
    formData.append('action', 'mark_one_read');
    formData.append('id', notificationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                updateNotificationCount();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsRead() {
    console.log('Mark all read clicked');
    
    // Show loading state
    const markReadBtn = document.querySelector('.mark-read');
    if (markReadBtn) {
        markReadBtn.textContent = 'Marking...';
        markReadBtn.disabled = true;
    }
    
    // Use same page with action parameter
    const formData = new FormData();
    formData.append('action', 'mark_all_read');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);
        
        // Check content type
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // Get text to see what we received
            return response.text().then(text => {
                console.error('Received non-JSON response:', text.substring(0, 200));
                throw new Error('Server returned HTML instead of JSON');
            });
        }
    })
    .then(data => {
        console.log('Mark all read response:', data);
        
        if (data && data.success) {
            // Update all notification items to 'read' class
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
            
            // Update count with the new count from server
            updateNotificationCountWithCount(data.count);
            
            // Hide "Mark all read" button
            if (markReadBtn) {
                markReadBtn.style.display = 'none';
            }
            
            console.log('Success! New count:', data.count);
        } else {
            console.error('Failed to mark all as read:', data ? data.message : 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error marking all as read:', error);
    })
    .finally(() => {
        // Reset button
        if (markReadBtn) {
            markReadBtn.textContent = 'Mark all read';
            markReadBtn.disabled = false;
        }
    });
}

function updateNotificationCount() {
    // Fetch updated count from same page
    const formData = new FormData();
    formData.append('action', 'get_count');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Count response:', data);
        
        if (data.success) {
            updateNotificationCountWithCount(data.count);
        }
    })
    .catch(error => {
        console.error('Count fetch error:', error);
    });
}

function updateNotificationCountWithCount(newCount) {
    console.log('Updating count to:', newCount);
    
    const countElement = document.querySelector('.notification-count');
    const bell = document.getElementById('notificationBell');
    
    if (newCount > 0) {
        if (!countElement) {
            const newCountElement = document.createElement('span');
            newCountElement.className = 'notification-count';
            newCountElement.textContent = newCount;
            bell.appendChild(newCountElement);
        } else {
            countElement.textContent = newCount;
            countElement.style.display = 'flex';
        }
    } else {
        // Remove count element if no unread notifications
        if (countElement) {
            countElement.remove();
        }
        
        // Also hide the "Mark all read" button if it exists
        const markReadBtn = document.querySelector('.mark-read');
        if (markReadBtn) {
            markReadBtn.style.display = 'none';
        }
    }
}
</script>
