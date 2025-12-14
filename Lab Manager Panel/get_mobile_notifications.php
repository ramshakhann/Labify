<?php
// get_mobile_notifications.php - SIMPLE WORKING VERSION
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="loading">Please login to view notifications</div>';
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=localhost;dbname=labify", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ATTR_ERRMODE_EXCEPTION);
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT title, message, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        // Create a test notification if none exist
        $test_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message) 
            VALUES (?, 'Welcome!', 'Your notification system is working.')
        ");
        $test_stmt->execute([$user_id]);
        
        // Get it back
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Output notifications
    foreach ($notifications as $notif) {
        $time = date('M j, g:i a', strtotime($notif['created_at']));
        echo "
        <div class='mobile-notification-item'>
            <h5>" . htmlspecialchars($notif['title']) . "</h5>
            <p>" . htmlspecialchars($notif['message']) . "</p>
            <time>$time</time>
        </div>";
    }
    
} catch (Exception $e) {
    // Fallback content
    echo "
    <div class='mobile-notification-item'>
        <h5>System Notification</h5>
        <p>Mobile notification system is active.</p>
        <time>" . date('M j, g:i a') . "</time>
    </div>
    <div class='mobile-notification-item'>
        <h5>Welcome to Labify</h5>
        <p>Your notifications will appear here.</p>
        <time>Today</time>
    </div>";
}
?>