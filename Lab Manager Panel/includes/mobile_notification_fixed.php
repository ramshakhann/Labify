<?php
// includes/mobile_notification_fixed.php - SIMPLIFIED MOBILE VERSION

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    return;
}

global $pdo;
$notification_count = 0;

try {
    $count_query = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_query->execute([$user_id]);
    $result = $count_query->fetch(PDO::FETCH_ASSOC);
    $notification_count = $result['count'] ?? 0;
} catch (Exception $e) {
    // Silent fail
    error_log("Mobile notification error: " . $e->getMessage());
}
?>

<!-- Mobile Notification Bell -->
<div class="mobile-notification-wrapper">
    <button class="mobile-notification-bell" id="mobileNotificationBell">
        <i class="fas fa-bell"></i>
        <?php if ($notification_count > 0): ?>
            <span class="mobile-notification-badge">
                <?php echo $notification_count > 9 ? '9+' : $notification_count; ?>
            </span>
        <?php endif; ?>
    </button>
    
    <!-- Mobile Notification Panel -->
    <div class="mobile-notification-panel" id="mobileNotificationPanel">
        <div class="mobile-notification-header">
            <h4>Notifications</h4>
            <button class="close-panel" onclick="closeMobileNotifications()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-notification-content" id="mobileNotificationContent">
            <div class="loading-notifications">
                <i class="fas fa-spinner fa-spin"></i> Loading notifications...
            </div>
        </div>
        <div class="mobile-notification-footer">
            <a href="notifications.php">View All Notifications</a>
            <?php if ($notification_count > 0): ?>
                <button onclick="markAllMobileAsRead()">Mark All Read</button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Overlay -->
    <div class="mobile-notification-overlay" id="mobileNotificationOverlay"></div>
</div>

<style>
/* Mobile Notification Styles */
.mobile-notification-wrapper {
    display: flex;
    align-items: center;
    margin-left: 15px;
}

.mobile-notification-bell {
    position: relative;
    background: rgba(4, 97, 125, 0.2);
    border: none;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    color: rgba(41, 251, 255, 1);
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.mobile-notification-bell:hover {
    background: rgba(4, 97, 125, 0.4);
    transform: scale(1.05);
}

.mobile-notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(45deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #0f172a;
    animation: badge-pulse 2s infinite;
}

@keyframes badge-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Mobile Notification Panel */
.mobile-notification-panel {
    position: fixed;
    top: 0;
    right: -100%;
    width: 90%;
    max-width: 400px;
    height: 100%;
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    z-index: 10000;
    transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    box-shadow: -5px 0 25px rgba(0, 0, 0, 0.5);
}

.mobile-notification-panel.active {
    right: 0;
}

.mobile-notification-header {
    padding: 20px;
    border-bottom: 1px solid #334155;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(30, 41, 59, 0.9);
}

.mobile-notification-header h4 {
    margin: 0;
    color: #f1f5f9;
    font-size: 1.3rem;
    font-weight: 600;
}

.mobile-notification-header .close-panel {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 1.5rem;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.mobile-notification-header .close-panel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

/* Notification Content */
.mobile-notification-content {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
}

.loading-notifications {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
    font-size: 0.9rem;
}

.loading-notifications i {
    font-size: 1.5rem;
    margin-bottom: 15px;
    display: block;
}

/* Notification Items */
.mobile-notification-item {
    padding: 15px;
    margin-bottom: 10px;
    background: rgba(30, 41, 59, 0.7);
    border-radius: 12px;
    border-left: 4px solid;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.mobile-notification-item:hover {
    background: rgba(30, 41, 59, 0.9);
    transform: translateX(-2px);
}

.mobile-notification-item.unread {
    background: rgba(59, 130, 246, 0.15);
    border-left-color: #3b82f6;
}

.mobile-notification-item.read {
    opacity: 0.7;
}

.mobile-notification-item.success {
    border-left-color: #10b981;
}

.mobile-notification-item.warning {
    border-left-color: #f59e0b;
}

.mobile-notification-item.danger {
    border-left-color: #ef4444;
}

.mobile-notification-item .notification-title {
    font-weight: 600;
    color: white;
    font-size: 0.95rem;
    margin-bottom: 5px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.mobile-notification-item .notification-title i {
    font-size: 0.8rem;
    color: #94a3b8;
}

.mobile-notification-item .notification-message {
    color: #cbd5e1;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 8px;
}

.mobile-notification-item .notification-time {
    color: #94a3b8;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
}

.mobile-notification-item .notification-time i {
    margin-right: 5px;
    font-size: 0.7rem;
}

/* Unread dot */
.unread-dot {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 8px;
    height: 8px;
    background: #3b82f6;
    border-radius: 50%;
    animation: dot-pulse 1.5s infinite;
}

@keyframes dot-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

/* Empty State */
.notification-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}

.notification-empty-state i {
    font-size: 3rem;
    margin-bottom: 20px;
    display: block;
    opacity: 0.5;
}

.notification-empty-state h5 {
    color: #cbd5e1;
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.notification-empty-state p {
    font-size: 0.9rem;
    margin: 0;
}

/* Footer */
.mobile-notification-footer {
    padding: 15px 20px;
    border-top: 1px solid #334155;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(30, 41, 59, 0.9);
}

.mobile-notification-footer a {
    color: #60a5fa;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.mobile-notification-footer a:hover {
    text-decoration: underline;
}

.mobile-notification-footer button {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid #3b82f6;
    color: #60a5fa;
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}

.mobile-notification-footer button:hover {
    background: rgba(59, 130, 246, 0.3);
}

/* Overlay */
.mobile-notification-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: none;
    backdrop-filter: blur(3px);
}

.mobile-notification-overlay.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Responsive */
@media (max-width: 480px) {
    .mobile-notification-panel {
        width: 100%;
    }
    
    .mobile-notification-header {
        padding: 15px;
    }
    
    .mobile-notification-item {
        padding: 12px;
    }
}
</style>

<script>
// Mobile Notification JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const mobileBell = document.getElementById('mobileNotificationBell');
    const mobilePanel = document.getElementById('mobileNotificationPanel');
    const mobileOverlay = document.getElementById('mobileNotificationOverlay');
    
    if (mobileBell) {
        mobileBell.addEventListener('click', function(e) {
            e.stopPropagation();
            openMobileNotifications();
        });
    }
    
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', function() {
            closeMobileNotifications();
        });
    }
    
    // Close panel with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMobileNotifications();
        }
    });
});

function openMobileNotifications() {
    const panel = document.getElementById('mobileNotificationPanel');
    const overlay = document.getElementById('mobileNotificationOverlay');
    
    if (panel && overlay) {
        panel.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
        
        // Load notifications
        loadMobileNotifications();
    }
}

function closeMobileNotifications() {
    const panel = document.getElementById('mobileNotificationPanel');
    const overlay = document.getElementById('mobileNotificationOverlay');
    
    if (panel && overlay) {
        panel.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

function loadMobileNotifications() {
    const content = document.getElementById('mobileNotificationContent');
    if (!content) return;
    
    content.innerHTML = `
        <div class="loading-notifications">
            <i class="fas fa-spinner fa-spin"></i>
            Loading notifications...
        </div>
    `;
    
    // AJAX call to get notifications
    fetch('ajax/get_mobile_notifications.php')
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (data.success && data.notifications && data.notifications.length > 0) {
                let html = '';
                
                data.notifications.forEach(notification => {
                    const timeAgo = getTimeAgo(notification.created_at);
                    const isUnread = !notification.is_read;
                    const typeClass = notification.type || 'info';
                    
                    html += `
                        <div class="mobile-notification-item ${isUnread ? 'unread' : 'read'} ${typeClass}" 
                             onclick="markMobileAsRead(${notification.id}, this)">
                            ${isUnread ? '<div class="unread-dot"></div>' : ''}
                            <div class="notification-title">
                                <span>${escapeHtml(notification.title || 'Notification')}</span>
                                <i class="fas fa-${getIconForType(notification.type)}"></i>
                            </div>
                            <div class="notification-message">
                                ${escapeHtml(notification.message)}
                            </div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i>
                                ${timeAgo}
                            </div>
                        </div>
                    `;
                });
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div class="notification-empty-state">
                        <i class="far fa-bell-slash"></i>
                        <h5>No notifications yet</h5>
                        <p>You're all caught up!</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            content.innerHTML = `
                <div class="notification-empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Unable to load notifications</h5>
                    <p>Please try again later</p>
                </div>
            `;
        });
}

function markMobileAsRead(notificationId, element) {
    // Mark as read via AJAX
    fetch('ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && element) {
            // Update UI
            element.classList.remove('unread');
            element.classList.add('read');
            
            // Remove unread dot
            const dot = element.querySelector('.unread-dot');
            if (dot) dot.remove();
            
            // Update badge count
            updateMobileBadge();
        }
    })
    .catch(error => console.error('Error:', error));
    
    // Close panel after click (optional)
    // setTimeout(closeMobileNotifications, 300);
}
function markAllAsRead() {
    console.log('=== MARK ALL READ START ===');
    console.log('Base path:', basePath);
    console.log('Full URL:', basePath + 'ajax/mark_all_notifications_read.php');
    
    // Show loading state
    const markReadBtn = document.querySelector('.mark-read');
    if (markReadBtn) {
        markReadBtn.textContent = 'Marking...';
        markReadBtn.disabled = true;
    }
    
    fetch(basePath + 'ajax/mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => {
        console.log('Response received:', response);
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return response.json();
    })
    .then(data => {
        console.log('=== MARK ALL READ RESPONSE ===');
        console.log('Data:', data);
        
        if (data.success) {
            console.log('Success! Updating UI...');
            
            // Update all notification items to 'read' class
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            console.log('Found unread items:', unreadItems.length);
            
            unreadItems.forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
            
            // Hide "Mark all read" button
            if (markReadBtn) {
                markReadBtn.style.display = 'none';
            }
            
            // Update notification count
            updateNotificationCount();
            
            console.log('UI updated successfully');
        } else {
            console.error('Failed to mark all as read:', data.message);
            alert('Failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('=== MARK ALL READ ERROR ===');
        console.error('Error:', error);
        console.error('Error stack:', error.stack);
        
        // Show error to user
        alert('Error marking notifications as read: ' + error.message);
        
        // Reset button
        if (markReadBtn) {
            markReadBtn.textContent = 'Mark all read';
            markReadBtn.disabled = false;
        }
    })
    .finally(() => {
        // Reset button after 2 seconds
        setTimeout(() => {
            if (markReadBtn) {
                markReadBtn.textContent = 'Mark all read';
                markReadBtn.disabled = false;
            }
        }, 2000);
    });
}

function updateMobileBadge() {
    fetch('ajax/get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.querySelector('.mobile-notification-badge');
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count > 9 ? '9+' : data.count;
                    } else {
                        // Create badge
                        const bell = document.getElementById('mobileNotificationBell');
                        if (bell) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'mobile-notification-badge';
                            newBadge.textContent = data.count > 9 ? '9+' : data.count;
                            bell.appendChild(newBadge);
                        }
                    }
                } else if (badge) {
                    badge.remove();
                }
            }
        });
}

// Helper functions
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString();
}

function getIconForType(type) {
    const icons = {
        'success': 'check-circle',
        'warning': 'exclamation-triangle',
        'danger': 'times-circle',
        'info': 'info-circle'
    };
    return icons[type] || 'bell';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showMobileToast(message, isError = false) {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `mobile-toast ${isError ? 'error' : 'success'}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${isError ? '#dc2626' : '#10b981'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 10001;
        animation: slideUp 0.3s ease;
        font-size: 0.9rem;
        max-width: 80%;
        text-align: center;
    `;
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideDown 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add CSS for toast animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from { transform: translate(-50%, 100%); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }
    
    @keyframes slideDown {
        from { transform: translate(-50%, 0); opacity: 1; }
        to { transform: translate(-50%, 100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>