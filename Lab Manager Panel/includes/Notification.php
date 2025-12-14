<?php
// includes/Notification.php - CORRECTED MYSQLI VERSION
class Notification {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Create a new notification
     */
    public function create($userId, $title, $message, $type = 'info', $icon = null, $link = null, $createdBy = null) {
        // Default icons based on type
        if (!$icon) {
            $icons = [
                'info' => 'fas fa-info-circle',
                'success' => 'fas fa-check-circle',
                'warning' => 'fas fa-exclamation-triangle',
                'danger' => 'fas fa-times-circle',
                'assignment' => 'fas fa-tasks'
            ];
            $icon = $icons[$type] ?? 'fas fa-bell';
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, icon, link, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isssssi", $userId, $title, $message, $type, $icon, $link, $createdBy);
        return $stmt->execute();
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $limit = 10, $unreadOnly = false) {
         $whereClause = $onlyUnread ? "WHERE is_read = 0" : "";
        $query = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        if (!$userId) {
            return 0;
        }
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
         
            is_read = FALSE
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
    
    /**
     * Simple test completion notification
     */
    public function notifyTestCompletion($testId, $testerId, $result) {
        // Get test info
        $stmt = $this->conn->prepare("SELECT test_id, product_id FROM test_results WHERE test_id = ?");
        $stmt->bind_param("s", $testId);
        $stmt->execute();
        $result_set = $stmt->get_result();
        $test = $result_set->fetch_assoc();
        
        if (!$test) {
            error_log("Test not found: $testId");
            return false;
        }
        
        $type = ($result == 'Pass') ? 'success' : 'danger';
        $title = "Test {$result} - {$test['test_id']}";
        $message = "Test {$test['test_id']} for product {$test['product_id']} completed with result: {$result}";
        
        return $this->create($testerId, $title, $message, $type);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            user_id = ?
        ");
        $stmt->bind_param("ii", $notificationId, $userId);
        return $stmt->execute();
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            is_read = FALSE
        ");
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }
}
?>