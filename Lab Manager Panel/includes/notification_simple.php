<?php
// includes/notification_simple.php - GUARANTEED WORKING

echo "<!-- NOTIFICATION FILE STARTED -->";

// 1. Check if session variables exist
if (!isset($_SESSION)) {
    echo "<!-- No session array -->";
    // Try to start session
    if (session_status() == PHP_SESSION_NONE) {
        @session_start();
    }
}

// 2. Check for user_id
$user_id = $_SESSION['user_id'] ?? null;
echo "<!-- User ID in notification file: " . ($user_id ?? 'NULL') . " -->";

// 3. Check for connection
if (!isset($conn)) {
    echo "<!-- No conn in notification file -->";
    // Try global
    if (isset($GLOBALS['conn'])) {
        $conn = $GLOBALS['conn'];
        echo "<!-- Got conn from GLOBALS -->";
    } else {
        echo "<!-- No DB connection available -->";
        // Show fallback bell anyway
        showFallbackBell();
        return;
    }
}

// 4. Get count
// 4. Get count
$unreadCount = 0;
if ($user_id && isset($conn)) {
    // Check connection type and handle accordingly
    if ($conn instanceof PDO) {
        // PDO connection
        $sql = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $unreadCount = $row['cnt'] ?? 0;
        echo "<!-- PDO query executed -->";
    } elseif ($conn instanceof mysqli) {
        // MySQLi connection
        $sql = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $unreadCount = $row['cnt'] ?? 0;
            $result->free();
        }
        echo "<!-- MySQLi query executed -->";
    } else {
        echo "<!-- Unknown connection type -->";
    }
    echo "<!-- Unread count: $unreadCount -->";
}

// 5. Show the bell
showNotificationBell($unreadCount);

function showFallbackBell() {
    ?>
    <div class="notification-bell" id="notificationBell" onclick="alert('Fallback bell clicked')">
        <i class="fas fa-bell"></i>
    </div>
    <?php
}

function showNotificationBell($count) {
    ?>
    <!-- Simple Notification Bell -->
    <div class="notification-wrapper">
        <div class="notification-bell" id="notificationBell" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <?php if ($count > 0): ?>
                <span class="notification-badge" id="notificationCount">
                    <?php echo $count > 9 ? '9+' : $count; ?>
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Simple Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
                <h4>Notifications</h4>
                <?php if ($count > 0): ?>
                    <button onclick="markAllAsRead()">Mark all read</button>
                <?php endif; ?>
            </div>
            <div class="notification-list">
                <div class="notification-empty">
                    <i class="far fa-bell"></i>
                    <p>Loading notifications...</p>
                </div>
            </div>
            <div class="notification-footer">
                <a href="notifications.php">View All</a>
            </div>
        </div>
    </div>

    <script>
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('show');
        
        if (dropdown.classList.contains('show')) {
            loadNotifications();
        }
    }

    function loadNotifications() {
        fetch('ajax/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                const list = document.querySelector('.notification-list');
                if (data.notifications && data.notifications.length > 0) {
                    list.innerHTML = '';
                    data.notifications.forEach(notif => {
                        list.innerHTML += `
                            <div class="notification-item" style="padding: 10px; border-bottom: 1px solid #334155;">
                                <strong>${notif.title}</strong>
                                <p style="margin: 5px 0; font-size: 0.9rem;">${notif.message}</p>
                                <small style="color: #94a3b8;">${notif.time}</small>
                            </div>
                        `;
                    });
                }
            });
    }

    function markAllAsRead() {
        fetch('ajax/mark_all_read.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('notificationCount').style.display = 'none';
                    location.reload();
                }
            });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const bell = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
    </script>
    <?php
}
?>




<style>
.notification-wrapper {
    position: relative;
    display: inline-block;
}

.notification-bell {
    position: relative;
    cursor: pointer;
    padding: 10px;
    font-size: 1.2rem;
    color: rgba(41, 251, 255, 1);
    background: rgba(4, 58, 125, 0.9);
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    margin-left: 10px;
}

.notification-bell:hover {
    background: rgba(4, 97, 125, 0.4);
    transform: scale(1.1);
    box-shadow: 0 0 15px rgba(41, 251, 255, 0.3);
}

.notification-badge {
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
    border: 2px solid #1f2937;
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
    background: rgba(15, 23, 42, 0.98);
    border: 1px solid #334155;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    z-index: 1000;
    display: none;
    margin-top: 10px;
}

.notification-dropdown.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-header {
    padding: 15px;
    border-bottom: 1px solid #334155;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h4 {
    margin: 0;
    color: #f1f5f9;
    font-size: 1rem;
}

.notification-header button {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
}

.notification-empty {
    text-align: center;
    padding: 20px;
    color: #94a3b8;
}

.notification-empty i {
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

.notification-footer {
    padding: 10px 15px;
    border-top: 1px solid #334155;
    text-align: center;
}

.notification-footer a {
    color: #60a5fa;
    text-decoration: none;
}

@media (max-width: 768px) {
    .notification-dropdown {
        position: fixed;
        top: 70px;
        right: 10px;
        left: 10px;
        width: auto;
    }
}
</style>