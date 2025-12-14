<?php
session_start();

 $host = 'localhost';
 $dbname = 'labify';
 $username = 'root';
 $password = '';
 $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 ");
    
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true, 
        'message' => "Marked {$affected} notifications as read",
        'count' => 0
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>