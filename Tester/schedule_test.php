<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = trim($_POST['product_id']);
    $test_type_id = trim($_POST['test_type_id']);
    $scheduled_date = trim($_POST['scheduled_date']);
    $priority = trim($_POST['priority']);
    $notes = trim($_POST['notes']);
    $assigned_to = $_SESSION['user_id'];
    
    // Validate date - should be in the future
    $scheduled_date_timestamp = strtotime($scheduled_date);
    $today_timestamp = strtotime(date('Y-m-d H:i:s'));
    
    if ($scheduled_date_timestamp <= $today_timestamp) {
        echo json_encode(['success' => false, 'message' => 'Scheduled date must be in the future']);
        exit;
    }
    
    // Generate unique Test ID
    $product_code = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $product_id)), 0, 4);
    $product_rev = 'R1';
    $test_code_map = ['1' => 'VLT', '2' => 'INS', '3' => 'TMP', '4' => 'CUR', '5' => 'END'];
    $test_code = $test_code_map[$test_type_id] ?? 'OT';
    $test_roll = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    $test_id = $product_code . $product_rev . $test_code . $test_roll;
    
    // Insert into database
    $sql = "INSERT INTO test_schedule 
        (test_id, product_id, test_type_id, scheduled_date, priority, notes, assigned_to) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param(
            "ssissss",
            $test_id,
            $product_id,
            $test_type_id,
            $scheduled_date,
            $priority,
            $notes,
            $assigned_to
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => "Test scheduled successfully with ID: $test_id",
                'test_id' => $test_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => "Error: Failed to prepare database statement"]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Invalid request method"]);
}
?>