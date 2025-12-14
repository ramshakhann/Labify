<?php
// includes/notification_mobile.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($conn)) {
    return;
}

$user_id = $_SESSION['user_id'];
// Get unread count
$unreadCount = 0;

try {
    // Check connection type
    if ($conn instanceof PDO) {
        // For PDO connection
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = $row['count'] ?? 0;
        }
    } elseif ($conn instanceof mysqli) {
        // For MySQLi connection (your current code)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                $unreadCount = $row['count'] ?? 0;
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Notification count error: " . $e->getMessage());
    $unreadCount = 0;
}
?>

<!-- Mobile Notification Bell -->
<div class="mobile-notification-bell" onclick="openMobileNotifications()">
    <i class="fas fa-bell"></i>
    <?php if ($unreadCount > 0): ?>
        <span class="mobile-notification-badge">
            <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
        </span>
    <?php endif; ?>
</div>

<!-- Mobile Notification Panel -->
<div class="mobile-notification-panel" id="mobileNotificationPanel">
    <div class="mobile-notification-header">
        <h4>Notifications</h4>
        <button onclick="closeMobileNotifications()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="mobile-notification-content" id="mobileNotificationContent">
        <!-- Notifications will load here -->
        <div class="loading">Loading...</div>
    </div>
    <div class="mobile-notification-footer">
        <a href="notifications.php">View All Notifications</a>
    </div>
</div>

<style>
/* Mobile Notification Styles */
.mobile-notification-bell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 45px;
    height: 45px;
    background: rgba(4, 97, 125, 0.2);
    border-radius: 50%;
    margin-left: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mobile-notification-bell:hover {
    background: rgba(4, 97, 125, 0.4);
    transform: scale(1.05);
}

.mobile-notification-bell i {
    font-size: 1.3rem;
    color: rgba(41, 251, 255, 1);
}

.mobile-notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: linear-gradient(45deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #0f172a;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Mobile Notification Panel */
.mobile-notification-panel {
    position: fixed;
    top: 0;
    right: -100%;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    z-index: 9999;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}

.mobile-notification-panel.open {
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
    font-size: 1.2rem;
}

.mobile-notification-header button {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 1.5rem;
    cursor: pointer;
}

.mobile-notification-content {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
}

.mobile-notification-item {
    padding: 15px;
    margin-bottom: 10px;
    background: rgba(30, 41, 59, 0.7);
    border-radius: 10px;
    border-left: 4px solid #3b82f6;
}

.mobile-notification-item.unread {
    background: rgba(59, 130, 246, 0.1);
    border-left-color: #ef4444;
}

.mobile-notification-item h5 {
    margin: 0 0 8px 0;
    color: #f1f5f9;
    font-size: 0.95rem;
}

.mobile-notification-item p {
    margin: 0 0 5px 0;
    color: #cbd5e1;
    font-size: 0.85rem;
    line-height: 1.4;
}

.mobile-notification-item time {
    color: #94a3b8;
    font-size: 0.75rem;
}

.mobile-notification-footer {
    padding: 15px;
    border-top: 1px solid #334155;
    text-align: center;
    background: rgba(30, 41, 59, 0.9);
}

.mobile-notification-footer a {
    color: #60a5fa;
    text-decoration: none;
    font-size: 0.9rem;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #94a3b8;
}

/* Overlay */
.mobile-notification-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9998;
}

.mobile-notification-overlay.show {
    display: block;
}
</style>

<script>
function openMobileNotifications() {
    document.getElementById('mobileNotificationPanel').classList.add('open');
    document.getElementById('mobileNotificationOverlay').classList.add('show');
    loadMobileNotifications();
}

function closeMobileNotifications() {
    document.getElementById('mobileNotificationPanel').classList.remove('open');
    document.getElementById('mobileNotificationOverlay').classList.remove('show');
}

function loadMobileNotifications() {
    const content = document.getElementById('mobileNotificationContent');
    content.innerHTML = '<div class="loading">Loading notifications...</div>';
    
    fetch('ajax/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                let html = '';
                data.notifications.forEach(notif => {
                    html += `
                        <div class="mobile-notification-item">
                            <h5>${notif.title}</h5>
                            <p>${notif.message}</p>
                            <time>${notif.time}</time>
                        </div>
                    `;
                });
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="loading">No notifications</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="loading">Error loading notifications</div>';
        });
}

// Close when clicking overlay
document.getElementById('mobileNotificationOverlay').addEventListener('click', closeMobileNotifications);
</script>

<!-- Overlay -->
<div class="mobile-notification-overlay" id="mobileNotificationOverlay"></div>