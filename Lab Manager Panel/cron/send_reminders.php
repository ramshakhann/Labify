// Create a cron job file: cron/send_reminders.php
<?php
require_once '../includes/db_connect.php';
require_once '../includes/Notification.php';

$notification = new Notification($conn);

// Find scheduled tests in next 24 hours
$stmt = $conn->prepare("
    SELECT * FROM test_schedule 
    WHERE scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    AND reminder_sent = FALSE
");
$stmt->execute();
$scheduledTests = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($scheduledTests as $schedule) {
    $notification->notifyScheduleReminder($schedule['id']);
    
    // Mark as reminder sent
    $updateStmt = $conn->prepare("UPDATE test_schedule SET reminder_sent = TRUE WHERE id = ?");
    $updateStmt->execute([$schedule['id']]);
}
?>