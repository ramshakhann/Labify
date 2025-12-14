<?php
date_default_timezone_set('Asia/Karachi');

session_start();
require_once 'includes/db_connect.php';

$success_message = null;
$error_message   = null;

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

  $host = 'localhost';
$dbname = 'labify';
$username = 'root';
$password = '';
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

$user_id = $_SESSION['user_id'] ?? null;





try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
$user_role = 'User'; // Default role
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && isset($user['role'])) {
            $user_role = $user['role'];
        }
    } catch (PDOException $e) {
        // Keep default role if query fails
        error_log("Error fetching user role: " . $e->getMessage());
    }}

$user_id = $_SESSION['user_id'] ?? null;
$tested_by_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Unknown';

if (!$user_id && isset($_SESSION['name'])) {
    $username = $_SESSION['name'];
    $user_query = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $user_query->bind_param("s", $username);
    $user_query->execute();
    $user_result = $user_query->get_result();
    if ($user_result->num_rows > 0) {
        $user_row = $user_result->fetch_assoc();
        $user_id = $user_row['id'];
        $_SESSION['user_id'] = $user_id;
    }
    $user_query->close();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
    $product_id = trim($_POST['product_id']);
    $tested_by  = $_SESSION['name'];       
    $test_type_id = trim($_POST['test_type_id']);
    $test_result = trim($_POST['test_result']);
    $remarks = trim($_POST['remarks']);
    $test_date = trim($_POST['test_date']);
    
    // Validate date - should not be in the future
    $test_date_timestamp = strtotime($test_date);
    $today_timestamp = strtotime(date('Y-m-d H:i:s'));
    
    if ($test_date_timestamp > $today_timestamp) {
        $error_message = "Error: Test date cannot be in the future. Please select a date and time up to now.";
    } else {
        // Generate unique Test ID
        $product_code = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $product_id)), 0, 4);
        $product_rev = 'R1';
        $test_code_map = ['1' => 'VLT', '2' => 'INS', '3' => 'TMP', '4' => 'CUR', '5' => 'END'];
        $test_code = $test_code_map[$test_type_id] ?? 'OT';
        $test_roll = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $test_id = $product_code . $product_rev . $test_code . $test_roll;
$status = $_POST['status'];

        $created_at = date('Y-m-d H:i:s');

       // Insert into database
$tester_id = $user_id;           // from session
$tested_by  = $tested_by_name;   // display name

$sql = "INSERT INTO test_results 
    (test_id, product_id, test_type_id, tester_id, tested_by, test_date, result, remarks, status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param(
        "ssisssssss",
        $test_id,
        $product_id,
        $test_type_id,
        $tester_id,     // ✅ now stored
        $tested_by,     // ✅ now stored
        $test_date,
        $test_result,
        $remarks,
        $status,
        $created_at
    );

     if ($stmt->execute()) {
    // Get product details for notification
    $product_stmt = $pdo->prepare("SELECT product_type, revision FROM products WHERE product_id = ?");
    $product_stmt->execute([$product_id]);
    $product_data = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format product info
    $product_info = $product_data ? "Product: {$product_data['product_type']}" : "Product: {$product_id}";
    
    // INSERT NOTIFICATION with user name
    $notif_sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at, created_by) 
                  VALUES (?, ?, ?, ?, 0, NOW(), ?)";
    
    $notif_stmt = $pdo->prepare($notif_sql);
    $notif_title = "Test " . ($test_result == 'Pass' ? 'Passed' : 'Failed');
    $notif_message = "Test {$test_id} completed with result: {$test_result}. {$product_info} by {$_SESSION['full_name']}";
    $notif_type = ($test_result == 'Pass') ? "success" : "error";
    
    $notif_stmt->execute([
        $_SESSION['user_id'],           // user_id
        $notif_title,                  // title  
        $notif_message,                 // message (includes user name)
        $notif_type,                   // type
        $_SESSION['user_id']            // created_by
    ]);
    
    $_SESSION['success_message'] = "Success! Test record submitted with Test ID: <b>" . htmlspecialchars($test_id) . "</b>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
         else {
            $_SESSION['error_message'] = "Error: " . $stmt->error;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error: Failed to prepare database statement.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    }
}



// Fetch real data for dashboard
// Total tests
 $total_tests_result = $conn->query("SELECT COUNT(*) as total FROM test_results");
 $total_tests = $total_tests_result->fetch_assoc()['total'];

// Success rate
 $success_rate_result = $conn->query("SELECT 
    ROUND((COUNT(CASE WHEN result = 'Pass' THEN 1 END) * 100.0 / COUNT(*)), 1) as success_rate 
    FROM test_results WHERE result IS NOT NULL");
 $success_rate = $success_rate_result->fetch_assoc()['success_rate'] ?? 0;

// Pending tests
 $pending_tests_result = $conn->query("SELECT COUNT(*) as pending FROM test_results WHERE status = 'Pending'");
 $pending_tests = $pending_tests_result->fetch_assoc()['pending'];

// In progress tests
 $in_progress_tests_result = $conn->query("SELECT COUNT(*) as in_progress FROM test_results WHERE status = 'In Progress'");
 $in_progress_tests = $in_progress_tests_result->fetch_assoc()['in_progress'];

// Failed tests
 $failed_tests_result = $conn->query("SELECT COUNT(*) as failed FROM test_results WHERE result = 'Fail'");
 $failed_tests = $failed_tests_result->fetch_assoc()['failed'];

// Average test duration
 $avg_duration_result = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, test_date)) as avg_duration FROM test_results WHERE test_date IS NOT NULL");
 $avg_duration = $avg_duration_result->fetch_assoc()['avg_duration'] ?? 0;

// Test results distribution for chart
 $results_distribution = $conn->query("SELECT 
    result, 
    COUNT(*) as count 
    FROM test_results 
    WHERE result IS NOT NULL 
    GROUP BY result");

 $chart_data = [];
 $chart_labels = [];
while ($row = $results_distribution->fetch_assoc()) {
    $chart_labels[] = $row['result'];
    $chart_data[] = $row['count'];
}

// Monthly trends for line chart
 $monthly_trends = $conn->query("SELECT 
    DATE_FORMAT(test_date, '%Y-%m') as month,
    COUNT(*) as test_count,
    COUNT(CASE WHEN result = 'Pass' THEN 1 END) as pass_count,
    COUNT(CASE WHEN result = 'Fail' THEN 1 END) as fail_count
    FROM test_results 
    WHERE test_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(test_date, '%Y-%m')
    ORDER BY month");

 $trend_labels = [];
 $trend_data = [];
 $trend_pass_data = [];
 $trend_fail_data = [];
while ($row = $monthly_trends->fetch_assoc()) {
    $trend_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $trend_data[] = $row['test_count'];
    $trend_pass_data[] = $row['pass_count'];
    $trend_fail_data[] = $row['fail_count'];
}

// Recent tests for history table
$recent_tests = $conn->query("
    SELECT 
        tr.id,
        tr.test_id,
        tr.product_id,
        tr.test_type_id,
        tr.test_date,
        tr.result,
        tr.status,
        tr.tester_id,
        tr.remarks,
        tr.created_at,
        tr.tested_by,
        p.product_type,
        p.revision
    FROM test_results tr
    LEFT JOIN products p ON tr.product_id = p.product_id
    ORDER BY tr.test_date DESC, tr.id DESC
    LIMIT 10
");



// Test types for dropdown
 $test_types = $conn->query("SELECT id, test_name FROM test_types");

// Current active tests for progress tracking
 $active_tests = $conn->query("SELECT 
    tr.test_id,
    tr.product_id,
    tr.test_type_id,
    tr.status,
    tr.test_date,
    tr.tester_id,
    p.product_type,
    p.revision
    FROM test_results tr
    LEFT JOIN products p ON tr.product_id = p.product_id
    WHERE tr.status IN ('Pending', 'In Progress')
    ORDER BY tr.test_date DESC
    LIMIT 3");

// Check if lab_equipment table exists
 $equipment_status = $conn->query("SHOW TABLES LIKE 'lab_equipment'");
if ($equipment_status->num_rows > 0) {
    $equipment_status = $conn->query("SELECT 
        equipment_name,
        status,
        last_maintenance
        FROM lab_equipment 
        ORDER BY status, equipment_name");
} else {
    $equipment_status = false;
}

// Fetch products for autocomplete
 $products_table = $conn->query("SHOW TABLES LIKE 'products'");
if ($products_table->num_rows > 0) {
    $products = $conn->query("SELECT id, product_id, product_type, revision FROM products LIMIT 50");
} else {
    $products = false;
}

// Test schedule for calendar view
 $test_schedule = $conn->query("SELECT 
    tr.test_id,
    tr.product_id,
    tr.test_type_id,
    tr.test_date,
    tr.status,
    tr.tester_id,
    p.product_type,
    p.revision
    FROM test_results tr
    LEFT JOIN products p ON tr.product_id = p.product_id
    WHERE tr.test_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND tr.test_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY tr.test_date ASC
    LIMIT 15");
// Process Schedule Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['schedule_product_id'])) {
    $schedule_product_id = trim($_POST['schedule_product_id']);
    $schedule_test_type_id = trim($_POST['schedule_test_type_id']);
    $scheduled_date = trim($_POST['scheduled_date']);
    $priority = trim($_POST['priority']);
    $notes = trim($_POST['notes']);
    
    $sql = "INSERT INTO test_schedule (product_id, test_type_id, scheduled_date, priority, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sisssi", $schedule_product_id, $schedule_test_type_id, $scheduled_date, $priority, $notes, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Test scheduled successfully!";
        } else {
            $_SESSION['error_message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// Process Edit Schedule Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_schedule_id'])) {
    $schedule_id = trim($_POST['edit_schedule_id']);
    $product_id = trim($_POST['edit_schedule_product_id']);
    $test_type_id = trim($_POST['edit_schedule_test_type_id']);
    $scheduled_date = trim($_POST['edit_scheduled_date']);
    $priority = trim($_POST['edit_priority']);
    $notes = trim($_POST['edit_notes']);
    
    $sql = "UPDATE test_schedule SET product_id = ?, test_type_id = ?, scheduled_date = ?, priority = ?, notes = ? WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sisssi", $product_id, $test_type_id, $scheduled_date, $priority, $notes, $schedule_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Schedule updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating schedule: " . $stmt->error;
        }
        $stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process Start Scheduled Test
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_schedule_id'])) {
    $schedule_id = trim($_POST['start_schedule_id']);
    
    // Get schedule details
    $schedule_query = $conn->prepare("SELECT * FROM test_schedule WHERE id = ?");
    $schedule_query->bind_param("i", $schedule_id);
    $schedule_query->execute();
    $schedule_result = $schedule_query->get_result();
    
    if ($schedule_result->num_rows > 0) {
        $schedule = $schedule_result->fetch_assoc();
        
        // Generate unique Test ID (similar to your existing code)
        $product_id = $schedule['product_id'];
        $test_type_id = $schedule['test_type_id'];
        $product_code = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $product_id)), 0, 4);
        $product_rev = 'R1';
        $test_code_map = ['1' => 'VLT', '2' => 'INS', '3' => 'TMP', '4' => 'CUR', '5' => 'END'];
        $test_code = $test_code_map[$test_type_id] ?? 'OT';
        $test_roll = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $test_id = $product_code . $product_rev . $test_code . $test_roll;
        
        // Insert into test_results
        $test_date = date('Y-m-d H:i:s');
        $created_at = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO test_results 
                (test_id, product_id, test_type_id, tester_id, tested_by, test_date, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'In Progress', ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                "ssissss",
                $test_id,
                $product_id,
                $test_type_id,
                $user_id,
                $tested_by_name,
                $test_date,
                $created_at
            );
            
            if ($stmt->execute()) {
                // Delete from schedule (or mark as completed)
                $delete_sql = "DELETE FROM test_schedule WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $schedule_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $_SESSION['success_message'] = "Test started with ID: <b>" . htmlspecialchars($test_id) . "</b>";
                
                // Return JSON for AJAX
                if (isset($_POST['start_schedule_id'])) {
                    echo json_encode(['success' => true, 'message' => 'Test started successfully', 'test_id' => $test_id]);
                    exit();
                }
            } else {
                $error = "Error starting test: " . $stmt->error;
                if (isset($_POST['start_schedule_id'])) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit();
                }
                $_SESSION['error_message'] = $error;
            }
            $stmt->close();
        }
    } else {
        $error = "Schedule not found";
        if (isset($_POST['start_schedule_id'])) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
        $_SESSION['error_message'] = $error;
    }
    
    $schedule_query->close();
    
    // Redirect if not AJAX
    if (!isset($_POST['start_schedule_id'])) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Process Delete Schedule Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_schedule_id'])) {
    $schedule_id = trim($_POST['delete_schedule_id']);
    
    $sql = "DELETE FROM test_schedule WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $schedule_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Schedule deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting schedule: " . $stmt->error;
        }
        $stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// Test performance by type
 $performance_by_type = $conn->query("SELECT 
    tr.test_type_id,
    COUNT(*) as total_tests,
    COUNT(CASE WHEN tr.result = 'Pass' THEN 1 END) as pass_count,
    COUNT(CASE WHEN tr.result = 'Fail' THEN 1 END) as fail_count,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, tr.created_at, tr.test_date)), 1) as avg_duration
    FROM test_results tr
    GROUP BY tr.test_type_id
    ORDER BY total_tests DESC
    LIMIT 5");

// Top testers
 $top_testers = $conn->query("SELECT 
    tr.tester_id,
    COUNT(*) as test_count,
    COUNT(CASE WHEN result = 'Pass' THEN 1 END) as pass_count,
    ROUND(COUNT(CASE WHEN result = 'Pass' THEN 1 END) * 100.0 / COUNT(*), 1) as success_rate
    FROM test_results tr
    WHERE tr.test_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY tr.tester_id
    ORDER BY test_count DESC
    LIMIT 5");
?>
<?php
// Fetch real equipment data
$equipment_data = [];
$status_counts = [];
$total_equipment = 0;
$operational_count = 0;
$operational_percentage = 0;

// Check if lab_equipment table exists
$table_check = $conn->query("SHOW TABLES LIKE 'lab_equipment'");
if ($table_check && $table_check->num_rows > 0) {
    // Fetch equipment data
$equipment_query = $conn->query("
    SELECT 
        id, 
        equipment_name,
        equipment_type,
        serial_number,
        model,
        manufacturer,
        status,
        DATE_FORMAT(last_maintenance, '%Y-%m-%d') as last_maintenance,
        DATE_FORMAT(next_maintenance, '%Y-%m-%d') as next_maintenance,
        location,
        department,
        notes
    FROM lab_equipment 
    ORDER BY 
        CASE status
            WHEN 'Out of Service' THEN 1
            WHEN 'Maintenance' THEN 2
            WHEN 'Calibration' THEN 3
            WHEN 'Offline' THEN 4
            ELSE 5
        END,
        equipment_name
");
    
    if ($equipment_query) {
        while($equipment = $equipment_query->fetch_assoc()) {
            $equipment_data[] = $equipment;
        }
        $total_equipment = count($equipment_data);
    }
    
    // Count equipment by status
    $equipment_status_count = $conn->query("
        SELECT 
            COALESCE(status, 'Unknown') as status,
            COUNT(*) as count
        FROM lab_equipment 
        GROUP BY COALESCE(status, 'Unknown')
        ORDER BY count DESC
    ");
    
    if ($equipment_status_count) {
        while($status = $equipment_status_count->fetch_assoc()) {
            $status_counts[$status['status']] = $status['count'];
        }
    }
    
    // Calculate operational percentage
    if ($total_equipment > 0) {
        $operational_count = $status_counts['Operational'] ?? 0;
        $operational_percentage = round(($operational_count / $total_equipment) * 100);
    }
} else {
    // Table doesn't exist, show message
    $show_table_creation_prompt = true;
}


// ... keep your existing form processing code above this ...

// =========================================================================
// NEW: Process Add Equipment Form (submitted to the same page)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_equipment_submit'])) {

    // Get form data with proper sanitization
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $equipment_type = trim($_POST['equipment_type'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $status = trim($_POST['status'] ?? 'Operational');
    $last_maintenance = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
    $next_maintenance = !empty($_POST['next_maintenance']) ? $_POST['next_maintenance'] : null;
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');

    // Validate required fields
    if (empty($equipment_name)) {
        $_SESSION['error_message'] = "Validation Error: Equipment name is required.";
    } elseif (empty($equipment_type)) {
        $_SESSION['error_message'] = "Validation Error: Equipment type is required.";
    } else {
        // Check for duplicate serial number (if provided)
        if (!empty($serial_number)) {
            $check_sql = "SELECT id FROM lab_equipment WHERE serial_number = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $serial_number);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error_message'] = "Duplicate Entry: Serial number '{$serial_number}' already exists.";
            }
            $check_stmt->close();
        }
    }

    // If there are no errors, proceed with insertion
    if (empty($_SESSION['error_message'])) {
        try {
            $sql = "INSERT INTO lab_equipment 
                    (equipment_name, equipment_type, model, serial_number, manufacturer, 
                     status, last_maintenance, next_maintenance, location, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssss",
                $equipment_name,
                $equipment_type,
                $model,
                $serial_number,
                $manufacturer,
                $status,
                $last_maintenance,
                $next_maintenance,
                $location,
                $notes
            );
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Success! Equipment '{$equipment_name}' added successfully.";
            } else {
                $_SESSION['error_message'] = "Database Error: Failed to add equipment. " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error_message'] = "System Error: An error occurred. " . $e->getMessage();
        }
    }
    
    // Redirect to the same page to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labify Pro - Advanced Testing Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="./assets/log_test_style.css">
    <link rel="stylesheet" href="./assets/product_style.css">


<style>

        /* User actions styling */
.user-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-avatar {
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
    box-shadow: 0 4px 10px rgba(251, 202, 23, 1);
    flex-shrink: 0;
}

.user-details {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
}

.user-name {
    font-weight: 600;
    font-size: 1rem;
    color: white;
    line-height: 1.2;
}

.user-role {
    font-size: 0.75rem;
    color: rgba(41, 251, 255, 1);
    font-weight: 400;
    text-transform: capitalize;
    margin-top: 2px;
    line-height: 1.2;
}

/* Mobile navigation user profile */
.mobile-user-profile {
    display: flex;
    align-items: center;
    padding: 20px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    margin-bottom: 20px;
}

.mobile-user-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(45deg, #014b66ff, #16f7f0ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.5rem;
    margin-right: 15px;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(4, 97, 125, 1);
}

.mobile-user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
}

.mobile-user-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: white;
    line-height: 1.2;
}

.mobile-user-role {
    font-size: 0.85rem;
    color: rgba(41, 251, 255, 1);
    text-transform: capitalize;
    margin-top: 3px;
    line-height: 1.2;
}

/* Optional: Role badges with vertical layout */
.role-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
.kanban-board {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px; /* Space for scrollbar */
}

/* For the Equipment Status card */
.equipment-grid {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

/* For the Test History table */
.table-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

/* For the Test Results Distribution chart */
.chart-container {
    max-height: 400px;
    overflow-y: auto;
}

/* For the Test Trends chart */
#trendsChart {
    max-height: 350px;
}

/* For the modal content */
.modal-body {
    max-height: 60vh;
    overflow-y: auto;
}
.role-badge.admin {
    background: rgba(100, 255, 218, 0.15);
    color: #64ffda;
    border: 1px solid rgba(100, 255, 218, 0.3);
}

.role-badge.supervisor {
    background: rgba(255, 183, 77, 0.15);
    color: #ffb74d;
    border: 1px solid rgba(255, 183, 77, 0.3);
}

.role-badge.tester {
    background: rgba(79, 195, 247, 0.15);
    color: #4fc3f7;
    border: 1px solid rgba(79, 195, 247, 0.3);
}

.role-badge.manager {
    background: rgba(186, 104, 200, 0.15);
    color: #ba68c8;
    border: 1px solid rgba(186, 104, 200, 0.3);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .user-actions {
        gap: 12px;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
    
    .user-name {
        font-size: 0.9rem;
    }
    
    .user-role {
        font-size: 0.7rem;
    }
    
    .mobile-user-avatar {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
        margin-right: 12px;
    }
    
    .mobile-user-name {
        font-size: 1rem;
    }
    
    .mobile-user-role {
        font-size: 0.8rem;
    }
}


        /* --- SCROLL TO TOP BUTTON --- */
#scrollTopBtn {
    display: none; 
    position: fixed; 
    bottom: 30px;
    right: 30px; 
    z-index: 99; 
    border:2px solid #098fbcff; 
    outline: none; 
          background: linear-gradient(45deg, #041578ff, #05b7b1e4);

    color: #070356ff;
    cursor: pointer;
    padding: 15px;
    border-radius: 50%; 
    font-size: 18px; 
    width: 50px; 
    height: 50px; 
    box-shadow: 0 4px 15px rgba(0, 152, 179, 1);
    transition: all 0.3s ease; 
}

#scrollTopBtn:hover {
    transform: scale(1.1); 
          background: linear-gradient(45deg, #0edbedff, #1c05b7e4);
              box-shadow: 0 4px 15px rgba(3, 241, 229, 1);


}

#scrollTopBtn:active {
    transform: scale(0.95); 
}
/* --- Custom Scrollbar Styling --- */

::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

::-webkit-scrollbar-track {
    background: #1a1a2e; 
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(45deg, #16f7f0ff, #098fbcff); 
    border-radius: 10px;
    border: 2px solid #1a1a2e; 
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(45deg, #7efffb, #0ab8d1); 
}

/* For Firefox */
* {
    scrollbar-width: thin; 
    scrollbar-color: #16f7f0ff #1a1a2e;
}





/* Equipment Status Colors - Add these to your existing CSS */
.status-operational {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-maintenance {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-calibration {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-out-of-service {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-offline {
    background: rgba(107, 114, 128, 0.15);
    color: #9ca3af;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

/* Equipment Status Dots */
.equipment-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
    display: inline-block;
}

.equipment-status-dot.status-operational {
    background-color: #10b981;
}

.equipment-status-dot.status-maintenance {
    background-color: #f59e0b;
}

.equipment-status-dot.status-calibration {
    background-color: #3b82f6;
}

.equipment-status-dot.status-out-of-service {
    background-color: #ef4444;
}

.equipment-status-dot.status-offline {
    background-color: #6b7280;
}

/* Fix for equipment items with spaces in status */
.equipment-status-dot.status-out-of-service {
    background-color: #ef4444 !important;
}

/* Status badges styling */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Make sure the status text is displayed properly */
.equipment-status span {
    font-size: 0.8rem;
    color: #e5e7eb;
}
</style>
</head>
<body>
        <!-- Particles Background -->
    <div id="particles-js"></div>
    
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">⚡</span>
                    <h1>Labify</h1>
                    <!-- Mobile Menu Button -->
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="desktop-nav">
                   <ul>
                        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                        <li><a href="products.php" class="nav-link ">Products</a></li>
                        <li><a href="log_test.php" class="nav-link active">Testing</a></li>
                        <li><a href="searching.php" class="nav-link">Search</a></li>
                        <li><a href="reports.php" class="nav-link">Reports</a></li>
                        <li><a href="user_management.php" class="nav-link">Users</a></li>

                    </ul>
                </nav>
                
                <!-- Desktop User Actions -->
                                  <div class="user-actions">
                                                   <!-- Notification Bell -->
    <?php 
    // Include the fixed notification widget
    if (file_exists('includes/notification_fixed.php')) {
        include_once 'includes/notification_fixed.php';
    } else {
        // Fallback simple bell
        echo '<button class="notification-bell" onclick="alert(\'Notification system loading\')">
                <i class="fas fa-bell"></i>
              </button>';
    }
    ?>
    <div class="user-avatar"><?php echo substr($tested_by_name, 0, 1); ?></div>
    <div class="user-details">
        <div class="user-name"><?php echo htmlspecialchars($tested_by_name); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
    </div>
    <button class="btn btn-primary" onclick="window.location.href='http://localhost/LABIFY/logout.php'">
        LOG OUT
    </button>
</div>
              
            </div>
        </div>
    </header>

    
<!-- Mobile Navigation -->
<div class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-header">
       <div class="mobile-nav-logo">
            <span class="logo-icon">⚡</span>
            <h2>Labify</h2>
        </div>
        <button class="close-mobile-menu" id="closeMobileMenu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Mobile User Profile -->
   <div class="mobile-user-profile">
    <div class="mobile-user-avatar">
        <?php echo substr($tested_by_name, 0, 1); ?>
    </div>
    <div class="mobile-user-info">
        <div class="mobile-user-name"><?php echo htmlspecialchars($tested_by_name); ?></div>
        <div class="mobile-user-role"><?php echo htmlspecialchars($user_role); ?></div>
    </div>
</div>
    
    <!-- Mobile Navigation Menu -->
    <ul class="mobile-nav-menu">
        <li><a href="dashboard.php" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="products.php" data-tab="products"><i class="fas fa-cubes"></i>Products</a></li>
        <li><a href="log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> Testing</a></li>
        <li><a href="searching.php" data-tab="search"><i class="fas fa-search"></i> Search</a></li>
        <li><a href="reports.php" data-tab="reports"><i class="fas fa-chart-bar"></i>Reports</a></li>
        <li><a href="user_management.php"><i class="fas fa-users"></i> User </a></li>
        
    </ul>
    
    <!-- Mobile User Actions -->
    <div class="mobile-nav-actions">
        
    <button class="btn btn-primary" onclick="window.location.href='http://localhost/LABIFY/logout.php'">
        LOG OUT
    </button>
</div>    </div>
</div>

<!-- Overlay -->
<div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
   
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="floating-elements">
            <div class="floating-element" style="width: 100px; height: 100px; top: 10%; left: 10%; animation-delay: 0s;"></div>
            <div class="floating-element" style="width: 150px; height: 150px; top: 60%; left: 80%; animation-delay: 1s;"></div>
            <div class="floating-element" style="width: 80px; height: 80px; top: 80%; left: 20%; animation-delay: 2s;"></div>
            <div class="floating-element" style="width: 120px; height: 120px; top: 30%; left: 70%; animation-delay: 3s;"></div>
        </div>
        <div class="container">
            <h2>Testing</h2>
            <p>Every product is rigorously tested to ensure it meets the highest safety and quality standards.</p>
        </div>
    </section>

    <div class="container">
        <!-- Dashboard Title -->
        <div class="dashboard-title">
            <div>
                <h2>Testing Dashboard</h2>
                <p>Monitor and manage all product testing activities</p>
            </div>
            <div class="dashboard-actions">
                <button class="btn btn-outline" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i>
                    Theme
                </button>
                <button class="btn btn-primary" onclick="refreshData()">
    <i class="fas fa-sync-alt"></i>
    Refresh
</button>


            </div>
        </div>

        <!-- Success/Error Messages -->
       <?php if (!empty($success_message)) { ?>
    <div class="alert alert-success"><?= $success_message ?></div>
<?php } ?>

<?php if (!empty($error_message)) { ?>
    <div class="alert alert-danger"><?= $error_message ?></div>
<?php } ?>

        <!-- Metrics Overview -->
        <div class="dashboard-grid">
            <div class="card metric-card">
                <div class="card-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <div class="metric-value"><?php echo $total_tests; ?></div>
                <div class="metric-label">Total Tests</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>12% from last month</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, ($total_tests/200)*100); ?>%"></div>
                </div>
            </div>

            <div class="card metric-card">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="metric-value"><?php echo $success_rate; ?>%</div>
                <div class="metric-label">Success Rate</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>5% from last month</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $success_rate; ?>%"></div>
                </div>
            </div>

            <div class="card metric-card">
                <div class="card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="metric-value"><?php echo $pending_tests; ?></div>
                <div class="metric-label">Pending Tests</div>
                <div class="metric-change negative">
                    <i class="fas fa-arrow-up"></i>
                    <span>3 more than yesterday</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, ($pending_tests/50)*100); ?>%"></div>
                </div>
            </div>

            <div class="card metric-card">
                <div class="card-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="metric-value"><?php echo $in_progress_tests; ?></div>
                <div class="metric-label">In Progress</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-down"></i>
                    <span>2 less than yesterday</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, ($in_progress_tests/30)*100); ?>%"></div>
                </div>
            </div>

            <div class="card metric-card">
                <div class="card-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="metric-value"><?php echo $failed_tests; ?></div>
                <div class="metric-label">Failed Tests</div>
                <div class="metric-change negative">
                    <i class="fas fa-arrow-up"></i>
                    <span>1 more than last week</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, ($failed_tests/50)*100); ?>%"></div>
                </div>
            </div>

            <div class="card metric-card">
                <div class="card-icon">
                    <i class="fas fa-stopwatch"></i>
                </div>
                <div class="metric-value"><?php echo round($avg_duration, 1); ?>h</div>
                <div class="metric-label">Avg. Test Duration</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-down"></i>
                    <span>0.5h less than last month</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, ($avg_duration/10)*100); ?>%"></div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                    <div class="card-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
                <div class="quick-actions">
                    <div class="action-btn" onclick="document.getElementById('testForm').scrollIntoView({behavior: 'smooth'})">
                        <i class="fas fa-plus"></i>
                        <span>New Test</span>
                    </div>
                    <div class="action-btn" onclick="window.open('reports.php', '_blank')">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </div>
                    <div class="action-btn" onclick="exportData()">
                        <i class="fas fa-download"></i>
                        <span>Export</span>
                    </div>
                    <div class="action-btn" onclick="printDashboard()">
                        <i class="fas fa-print"></i>
                        <span>Print</span>
                    </div>
                    <div class="action-btn" onclick="openScheduleModal()">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                    </div>
                     <div class="action-btn" onclick="window.open('searching.php', '_blank')">
                        <i class="fas fa-search"></i>
                        <span>Search</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Test Entry Form -->
<div class="dashboard">
 <!-- Test Entry Form -->
            <div class="card ">
                <div class="card-header">
                    
                    <h3 class="card-title">New Test Entry</h3>
                    <div class="card-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                </div>
<form id="testForm" method="POST" action="" 
      autocomplete="off" spellcheck="false">
                          <div class="form-grid">
                        <div class="form-group">
                            <label for="product_id">Product ID</label>
                            <input type="text" id="product_id" name="product_id" placeholder="Enter product ID" required list="productSuggestions" autocomplete="off">
                            <datalist id="productSuggestions">
                                <?php if ($products): ?>
                                    <?php while($product = $products->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($product['product_id']); ?>">
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label for="test_type_id">Test Type</label>
                            <select id="test_type_id" name="test_type_id" required>
                                <option value="">Select Test Type</option>
                                <?php while($type = $test_types->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                        <?php echo htmlspecialchars($type['test_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="test_result">Test Result</label>
                            <select id="test_result" name="test_result" required>
                                <option value="">Select Result</option>
                                <option value="Pass">Pass</option>
                                <option value="Fail">Fail</option>

                                <option value="Inconclusive">Inconclusive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="test_date">Test Date</label>
                        <input type="datetime-local" 
                            id="test_date" 
                            name="test_date" 
                            required 
                            max="<?php echo date('Y-m-d\TH:i'); ?>"
                            autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="tested_by">Tested By</label>
                            <input type="text" id="tested_by" name="tested_by"  autocomplete="off" value="<?php echo htmlspecialchars($tested_by_name); ?>" readonly>
                        </div>
                        <div class="form-group">
                        <label for="status">Test Status</label>
                        <select name="status" id="status"  required>
                            <option value="">Select Status </option>
                            <option value="Sent to CPRI">Sent to CPRI</option>
                            <option value="Pending">Pending</option>
                            <option value="Sent for Remake">Sent for Remake</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Fail">Fail</option>
                        </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Enter detailed remarks about testing process..."></textarea>
                        </div>
                        <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i>
                                Submit Test
                            </button>
                            <button type="reset" class="btn btn-outline">
                                <i class="fas fa-redo"></i>
                                Reset Form
                            </button>
                             
                        </div>
                    </div>
                </form>
            </div>

</div>

        <!-- Main Content Grid -->
        <div class="dashboard-grid">
           

            <!-- Test Progress Kanban Board -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Test Progress</h3>
                    <div class="card-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="kanban-board">
                    <div class="kanban-column">
                        <div class="kanban-header">
                            <div class="kanban-title">Pending</div>
                            <div class="kanban-count"><?php echo $pending_tests; ?></div>
                        </div>
                        <?php 
                        // Reset result pointer for active tests
                        $active_tests->data_seek(0);
                        while($active = $active_tests->fetch_assoc()): 
                            if ($active['status'] == 'Pending'): 
                        ?>
                                <div class="kanban-card">
                                    <div class="kanban-card-header">
                                        <div class="kanban-card-title"><?php echo htmlspecialchars($active['test_id']); ?></div>
                                    </div>
                                    <div class="kanban-card-body">
                                        Product: <?php echo htmlspecialchars($active['product_id']); ?><br>
                                        Type: <?php echo htmlspecialchars($active['product_type'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="kanban-card-footer">
                                        <span><?php echo date('M d', strtotime($active['test_date'])); ?></span>
                                        <span><?php echo htmlspecialchars($active['tester_id']); ?></span>
                                    </div>
                                </div>
                            <?php 
                            endif;
                        endwhile; 
                        ?>
                    </div>
                    <div class="kanban-column">
                        <div class="kanban-header">
                            <div class="kanban-title">In Progress</div>
                            <div class="kanban-count"><?php echo $in_progress_tests; ?></div>
                        </div>
                        <?php 
                        // Reset result pointer for active tests
                        $active_tests->data_seek(0);
                        while($active = $active_tests->fetch_assoc()): 
                            if ($active['status'] == 'In Progress'): 
                        ?>
                                <div class="kanban-card">
                                    <div class="kanban-card-header">
                                        <div class="kanban-card-title"><?php echo htmlspecialchars($active['test_id']); ?></div>
                                    </div>
                                    <div class="kanban-card-body">
                                        Product: <?php echo htmlspecialchars($active['product_id']); ?><br>
                                        Type: <?php echo htmlspecialchars($active['product_type'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="kanban-card-footer">
                                        <span>Started <?php echo date('H:i', strtotime($active['test_date'])); ?></span>
                                        <span><?php echo htmlspecialchars($active['tester_id']); ?></span>
                                    </div>
                                </div>
                            <?php 
                            endif;
                        endwhile; 
                        ?>
                    </div>
                    <div class="kanban-column">
                        <div class="kanban-header">
                            <div class="kanban-title">Completed</div>
                            <div class="kanban-count"><?php echo $total_tests - $pending_tests - $in_progress_tests; ?></div>
                        </div>
                        <?php 
                        // Reset result pointer for recent tests
                        $recent_tests->data_seek(0);
                        while($test = $recent_tests->fetch_assoc()): 
                            if ($test['status'] == 'Completed' || $test['status'] == 'Verified'): 
                        ?>
                                <div class="kanban-card">
                                    <div class="kanban-card-header">
                                        <div class="kanban-card-title"><?php echo htmlspecialchars($test['test_id']); ?></div>
                                        <div class="status-badge status-<?php echo strtolower($test['result']) == 'pass' ? 'pass' : 'fail'; ?>">
                                            <?php echo htmlspecialchars($test['result']); ?>
                                        </div>
                                    </div>
                                    <div class="kanban-card-body">
                                        Product: <?php echo htmlspecialchars($test['product_id']); ?><br>
                                        Type: <?php echo htmlspecialchars($test['product_type'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="kanban-card-footer">
                                        <span><?php echo date('M d', strtotime($test['test_date'])); ?></span>
                                        <span><?php echo htmlspecialchars($test['tested_by']); ?></span>
                                    </div>
                                </div>
                            <?php 
                            endif;
                        endwhile; 
                        ?>
                    </div>
                </div>
            </div>

            <!-- Test Results Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Test Results Distribution</h3>
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>

            <!-- Equipment Status -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Equipment Status</h3>
                    <div class="card-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
                <div class="equipment-grid">
                    <?php if ($equipment_status && $equipment_status->num_rows > 0): ?>
                        <?php while($equipment = $equipment_status->fetch_assoc()): ?>
                            <div class="equipment-item">
                                <div class="equipment-icon">
                                    <i class="fas fa-microscope"></i>
                                </div>
                                <div class="equipment-info">
                                    <div class="equipment-name"><?php echo htmlspecialchars($equipment['equipment_name']); ?></div>
                                    <div class="equipment-status">
                                        <div class="equipment-status-dot status-<?php echo strtolower($equipment['status']); ?>"></div>
                                        <span><?php echo htmlspecialchars($equipment['status']); ?></span>
                                    </div>
                                </div>
                                
                            </div>
                        <?php endwhile; ?>

                    <?php endif; ?>
                </div>
            </div>
               <!-- Test Trends Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Test Trends</h3>
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>

            <!-- Test History with Tabs -->
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <h3 class="card-title">Test History</h3>
                    <div class="card-icon">
                        <i class="fas fa-history"></i>
                    </div>
                </div>
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('recent')">Recent Tests</div>
                    <div class="tab" onclick="switchTab('performance')">Performance</div>
                        <div class="tab" onclick="switchTab('scheduled')">Scheduled Tests</div>

                </div>
                <div id="scheduled" class="tab-content">
    <div class="table-container">
        <table id="scheduledTable">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Test Type</th>
                    <th>Scheduled Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
              $all_scheduled = $conn->query("
    SELECT ts.*, tt.test_name 
    FROM test_schedule ts 
    LEFT JOIN test_types tt ON ts.test_type_id = tt.id 
    ORDER BY ts.scheduled_date ASC
");        
                if ($all_scheduled->num_rows > 0): 
                    while($schedule = $all_scheduled->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($schedule['product_id']); ?></td>
<!-- In the scheduled tab, change the Test Type column to: -->
<td><?php echo htmlspecialchars($schedule['test_name']); ?></td>  <td><?php echo date('M j, Y g:i A', strtotime($schedule['scheduled_date'])); ?></td>
                      <td>
    <?php 
    $priority = $schedule['priority'];
    $colors = [
        'low' => 'background: rgba(64, 185, 16, 0.36);
             color: #39f106ff ; border: 1px solid #0cf53fff ;
            border-radius: 9999px;',
        'medium' => 'background: rgba(247, 128, 85, 0.33);
             color: #e9947aff ; border: 1px solid #f1774bff ;
            border-radius: 9999px;',
        'high' => 'background: rgba(227, 206, 18, 0.37);
             color: #f0d20cff ; border: 1px solid #f5f50cff ;
            border-radius: 9999px;',
        'urgent' => 'background: rgba(185, 27, 16, 0.42);
             color: #f00c0cff ; border: 1px solid #f5330cff ;
            border-radius: 9999px;'
    ];
    ?>
    <span style="
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        <?php echo $colors[$priority] ?? $colors['medium']; ?>
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    ">
        <?php echo ucfirst($priority); ?>
    </span>
</td>
                        <td>
                            <span class="status-badge status-scheduled"
style="
     background: rgba(16, 95, 185, 0.42);
             color: #0cd5f0ff !important;
             padding: 0.5rem;
             border: 1px solid #0cbef5ff ;
            border-radius: 9999px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 2px;background: rgba(68, 202, 239, 0.2);
    ">
       Scheduled</span>
                        </td>
                       <!-- In your scheduled table, update the actions column: -->
<td>
     <button class="btn btn-start" onclick="startScheduledTest('<?php echo $schedule['id']; ?>')" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 6px -1px rgba(6, 187, 18, 0.92), 0 2px 4px -1px rgba(255, 255, 255, 0.996)'" 
onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(90, 255, 68, 0.92), 0 2px 4px -1px rgba(0, 0, 0, 0.06)'" style="
    background: linear-gradient(135deg, #0bcd22ff, #000000);
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(68, 255, 118, 0.92), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
             color: #cffad1ff !important;
            border: none !important;
             padding: 0.5rem;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 2px;background: rgba(68, 239, 74, 0.2);
    color: var(--danger-light);">
        <i class="fas fa-play"></i> 
    </button>
  
    <button class="btn-schedule-edit" onclick="openEditScheduleModal(
        '<?php echo $schedule['id']; ?>',
        '<?php echo $schedule['product_id']; ?>',
        '<?php echo $schedule['test_type_id']; ?>',
        '<?php echo $schedule['scheduled_date']; ?>',
        '<?php echo $schedule['priority']; ?>',
        '<?php echo $schedule['notes']; ?>'
    )" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 6px -1px rgba(255, 254, 178, 0.92), 0 2px 4px -1px rgba(255, 255, 255, 0.996)'" 
onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(236, 255, 68, 0.92), 0 2px 4px -1px rgba(0, 0, 0, 0.06)'" style="
    background: linear-gradient(135deg, #debf0eff, #000000);
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(255, 230, 68, 0.92), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
             color: #faf8cfff !important;
            border: none !important;
             padding: 0.5rem;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 2px;background: rgba(228, 239, 68, 0.2);
    color: var(--danger-light);">
        <i class="fas fa-edit"></i>
    </button>
    <button class="btn-schedule-delete" onclick="openDeleteScheduleModal(
        '<?php echo $schedule['id']; ?>',
        '<?php echo $schedule['product_id']; ?>',
        '<?php echo $schedule['test_name']; ?>',
        '<?php echo $schedule['scheduled_date']; ?>'
    )"  onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 6px -1px rgba(255, 178, 178, 0.92), 0 2px 4px -1px rgba(255, 255, 255, 0.996)'" 
onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(255, 68, 68, 0.922), 0 2px 4px -1px rgba(0, 0, 0, 0.06)'" style="
    background: linear-gradient(135deg, #8a0303, #000000);
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(255, 68, 68, 0.922), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
             color: #facfcf !important;
            border: none !important;
             padding: 0.5rem;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 2px;background: rgba(239, 68, 68, 0.2);
    color: var(--danger-light);">
        <i class="fas fa-trash"></i>
    </button>
</td>
                    </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <tr>
                        <td colspan="6" class="no-data">
                            <i class="fas fa-calendar-plus"></i>
                            No tests scheduled. <a href="javascript:void(0)" onclick="openScheduleModal()">Schedule one now</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
                <div id="recent" class="tab-content active">
                    <!-- Search Bar -->
<div class="history-search">
    <input type="text" id="searchInput" placeholder="Search Test ID, Product, Status, Tester...">
    <input type="date" id="dateInput">    

</div><button type="button" id="clearBtn" class="btn btn-primary" >Clear</button>

                    <div class="table-container">
<table id="historyTable">
                            <thead>
                                <tr>
                                    <th>Test ID</th>
                                    <th>Product ID</th>
                                    <th>Product Type</th>
                                    <th>Test Type</th>
                                    <th>Test Date</th>
                                    <th>Result</th>
                                    <th>Status</th>
                                    <th>Tester</th>
                                    <th>Actions</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Reset result pointer for recent tests
                                $recent_tests->data_seek(0);
                                if ($recent_tests->num_rows > 0): 
                                    while($test = $recent_tests->fetch_assoc()): 
                                ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($test['test_id']); ?></td>
                                            <td><?php echo htmlspecialchars($test['product_id']); ?></td>
                                            <td><?php echo htmlspecialchars($test['product_type'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($test['test_type_id']); ?></td>
                                            <td><?php echo htmlspecialchars($test['test_date']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($test['result']) == 'pass' ? 'pass' : (strtolower($test['result']) == 'fail' ? 'fail' : 'pending'); ?>">
                                                    <?php echo htmlspecialchars($test['result']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($test['status']) == 'pending' ? 'pending' : (strtolower($test['status']) == 'in progress' ? 'in-progress' : 'pass'); ?>">
                                                    <?php echo htmlspecialchars($test['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($test['tested_by']); ?></td>





                                            <td>
    <!-- SIMPLE ACTION BUTTONS -->
    <button type="button" class="btn-view" 
            data-testid="<?php echo $test['test_id']; ?>"
            data-productid="<?php echo $test['product_id']; ?>"
            data-testtype="<?php echo $test['test_type_id']; ?>"
            >
        <i class="fas fa-eye"></i>
    </button>

    <button type="button" class="btn-edit" 
            data-testid="<?php echo $test['test_id']; ?>"
            data-status="<?php echo $test['status']; ?>"
            >
        <i class="fas fa-edit"></i>
    </button>

    <button type="button" class="btn-delete" 
            data-testid="<?php echo $test['test_id']; ?>"
            >
        <i class="fas fa-trash"></i>
    </button>
</td>
                                        </tr>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--gray);">No test records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="performance" class="tab-content">
                    <h4 style="margin-bottom: 1rem; color: var(--white);">Test Performance by Type</h4>
                    <div class="performance-table">
                        <?php if ($performance_by_type->num_rows > 0): ?>
                            <?php while($perf = $performance_by_type->fetch_assoc()): ?>
                                <div class="performance-row">
                                    <div class="performance-name">Test Type <?php echo htmlspecialchars($perf['test_type_id']); ?></div>
                                    <div class="performance-stats">
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $perf['total_tests']; ?></div>
                                            <div class="performance-label">Total</div>
                                        </div>
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $perf['pass_count']; ?></div>
                                            <div class="performance-label">Pass</div>
                                        </div>
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $perf['fail_count']; ?></div>
                                            <div class="performance-label">Fail</div>
                                        </div>
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $perf['avg_duration']; ?>h</div>
                                            <div class="performance-label">Avg. Duration</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray); padding: 2rem;">No performance data available</div>
                        <?php endif; ?>
                    </div>
                    
                    <h4 style="margin: 2rem 0 1rem; color: var(--white);">Top Testers (Last 30 Days)</h4>
                    <div class="performance-table">
                        <?php if ($top_testers->num_rows > 0): ?>
                            <?php while($tester = $top_testers->fetch_assoc()): ?>
                                <div class="performance-row">
                                    <div class="performance-name"><?php echo htmlspecialchars($tester['tester_id']); ?></div>
                                    <div class="performance-stats">
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $tester['test_count']; ?></div>
                                            <div class="performance-label">Tests</div>
                                        </div>
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $tester['pass_count']; ?></div>
                                            <div class="performance-label">Pass</div>
                                        </div>
                                        <div class="performance-stat">
                                            <div class="performance-value"><?php echo $tester['success_rate']; ?>%</div>
                                            <div class="performance-label">Success Rate</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray); padding: 2rem;">No tester data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

         
        </div>

<!-- Equipment Status -->
<div class="card" style="margin-bottom:45px">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div>
                <h3 class="card-title">Equipment Status</h3>
                <small style="color: #6b7280; font-size: 0.875rem;">
                    <?php echo $operational_percentage; ?>% Operational 
                    (<?php echo $operational_count; ?>/<?php echo $total_equipment; ?> devices)
                </small>
            </div>
           
            <div class="card-icon">
                
                <i class="fas fa-tools"></i>
            </div>
        </div>
    </div>
    
    <!-- Status Summary -->
    <div style="padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php foreach($status_counts as $status => $count): ?>
                <?php 
                $status_colors = [
                    'Operational' => '#10b981',
                    'Maintenance' => '#f59e0b',
                    'Calibration' => '#3b82f6',
                    'Out of Service' => '#ef4444',
                    'Offline' => '#6b7280'
                ];
                $color = $status_colors[$status] ?? '#6b7280';
                ?>
                
                <div style="display: flex; align-items: center; gap: 5px; padding: 5px 10px; background: rgba(255, 255, 255, 0.05); border-radius: 6px;">
                    <div style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $color; ?>;"></div>
                    <span style="font-size: 12px; color: #f1f5f9;"><?php echo $status; ?></span>
                    <span style="font-size: 12px; font-weight: bold; color: <?php echo $color; ?>;"><?php echo $count; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Equipment List -->
    <div class="equipment-grid" style="max-height: 400px; overflow-y: auto; padding: 10px;">
        <?php if (!empty($equipment_data)): ?>
            <?php foreach($equipment_data as $equipment): ?>
                <div class="equipment-item" 
                     style="cursor: pointer;"
onclick="showEquipmentDetails('<?php echo htmlspecialchars(json_encode($equipment), ENT_QUOTES); ?>')">
                    <div class="equipment-icon">
                        <?php 
                        $icons = [
                            'Electrical Test' => 'fas fa-bolt',
                            'Measurement' => 'fas fa-ruler-combined',
                            'Environmental Test' => 'fas fa-thermometer-half',
                            'Electrical' => 'fas fa-plug',
                            'Inspection' => 'fas fa-microscope',
                            'Mechanical Test' => 'fas fa-cogs'
                        ];
                        $icon = $icons[$equipment['equipment_type']] ?? 'fas fa-tools';
                        ?>
                        <i class="<?php echo $icon; ?>"></i>

                        
                    </div>
                    <div class="equipment-info" style="flex: 1;">
                        <div class="equipment-name"><?php echo htmlspecialchars($equipment['equipment_name']); ?></div>
                        <div class="equipment-meta" style="font-size: 11px; color: #9ca3af; margin-top: 2px;">
                            <?php echo htmlspecialchars($equipment['model']); ?> • 
                            <?php echo htmlspecialchars($equipment['location']); ?>
                        </div>
                        <div class="equipment-status" style="margin-top: 5px;">
                            <div class="equipment-status-dot status-<?php echo strtolower(str_replace(' ', '-', $equipment['status'])); ?>"></div>
                            <span><?php echo htmlspecialchars($equipment['status']); ?></span>
                            <?php if ($equipment['next_maintenance']): ?>
                                <span style="font-size: 10px; color: #9ca3af; margin-left: 8px;">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('M d, Y', strtotime($equipment['next_maintenance'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <i class="fas fa-tools" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>No equipment data found</p>
                <button onclick="addSampleEquipment()" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Add Sample Equipment
                </button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Equipment Actions -->
    <div style="padding: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1); display: flex; gap: 10px;">
        
    </div>
     <button class="btn btn-primary" style="flex: 1;" onclick="openEquipmentModal()">
            <i class="fas fa-plus"></i> Add Equipment
        </button>
        <button class="btn btn-primary" style="flex: 1;" onclick="refreshEquipment()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
</div>
    </div>





                                    <!-- **************MODALS**************** -->

<div id="viewModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
    <div style="background-color: #1f2937; margin: 15% auto; padding: 20px; border: 3px solid #ef4444; border-radius: 10px; width: 80%; max-width: 500px; box-shadow: 0 0 20px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #374151;">
            <h2 style="color: white; margin: 0;">Test Details</h2>
            <span style="color: #9ca3af; font-size: 28px; cursor: pointer; font-weight: bold;" onclick="document.getElementById('viewModal').style.display='none'">&times;</span>
        </div>
        <div style="color: white;">
            <p><strong>Test ID:</strong> <span id="viewTestId">-</span></p>
            <p><strong>Product ID:</strong> <span id="viewProductId">-</span></p>
            <p><strong>Test Type:</strong> <span id="viewTestType">-</span></p>
        </div>
        <div style="margin-top: 20px; text-align: right;">
            <button style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;" onclick="document.getElementById('viewModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Test Record</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form id="editForm">
            <div class="modal-body">
                <input type="hidden" id="editTestId" name="test_id">
                <div class="form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus" name="status" required>
                        <option value="">Select Status</option>
                        <option value="Sent to CPRI">Sent to CPRI</option>
                        <option value="Pending">Pending</option>
                        <option value="Sent for Remake">Sent for Remake</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Fail">Fail</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editResult">Result</label>
                    <select id="editResult" name="result" required>
                        <option value="">Select Result</option>
                        <option value="Pass">Pass</option>
                        <option value="Fail">Fail</option>
                        <option value="Inconclusive">Inconclusive</option>

                    </select>
                </div>
                <div class="form-group full-width">
                    <label for="editRemarks">Remarks</label>
                    <textarea id="editRemarks" name="remarks" placeholder="Update remarks..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Test</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
    <div style="background-color: #1f2937; margin: 15% auto; padding: 20px; border: 3px solid #ef4444; border-radius: 10px; width: 80%; max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #374151;">
            <h2 style="color: white; margin: 0;">Delete Test Record</h2>
            <span style="color: #9ca3af; font-size: 28px; cursor: pointer; font-weight: bold;" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
        </div>
        
        <div style="color: white;">
            <p>Are you sure you want to delete this test record?</p>
            <div style="background: #374151; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <strong>Test ID:</strong> <span id="deleteTestId" style="color: #ef4444; font-weight: bold;">-</span>
            </div>
            <div style="background: #7f1d1d; color: #fecaca; padding: 12px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ef4444;">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Warning:</strong> This action cannot be undone!
            </div>
        </div>
        
        <div style="margin-top: 20px; text-align: right;">
            <button type="button" style="background: #806b6bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
<button type="button" style="background: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;" onclick="handleDelete()">Delete Record</button>        </div>
    </div>
</div>





<!-- Schedule Test Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Schedule New Test</h3>
            <span class="close" onclick="closeModal('scheduleModal')">&times;</span>
        </div>
        <form id="scheduleForm" method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="scheduleProductId">Product ID *</label>
                    <!-- CHANGE: Add schedule_ prefix -->
                    <input type="text" id="scheduleProductId" name="schedule_product_id" required 
                           placeholder="Enter product ID" list="productSuggestions" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="scheduleTestType">Test Type *</label>
                    <!-- CHANGE: Add schedule_ prefix -->
                    <select id="scheduleTestType" name="schedule_test_type_id" required>
                        <option value="">Select Test Type</option>
                        <?php 
                        $test_types->data_seek(0);
                        while($type = $test_types->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                <?php echo htmlspecialchars($type['test_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="scheduleDate">Scheduled Date & Time *</label>
                    <input type="datetime-local" id="scheduleDate" name="scheduled_date" required>
                    <small style="color: #6b7280; font-size: 0.875rem;">Select a future date and time</small>
                </div>
                
                <div class="form-group">
                    <label for="schedulePriority">Priority</label>
                    <select id="schedulePriority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="scheduleNotes">Notes & Instructions</label>
                    <textarea id="scheduleNotes" name="notes" placeholder="Any special instructions, requirements, or notes for this scheduled test..." rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('scheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Schedule Test
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Scheduled Test</h3>
            <span class="close" onclick="closeModal('editScheduleModal')">&times;</span>
        </div>
        <form id="editScheduleForm" method="POST" action="">
            <input type="hidden" id="editScheduleId" name="edit_schedule_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="editScheduleProductId">Product ID *</label>
                    <input type="text" id="editScheduleProductId" name="edit_schedule_product_id" required 
                           placeholder="Enter product ID" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="editScheduleTestType">Test Type *</label>
                    <select id="editScheduleTestType" name="edit_schedule_test_type_id" required>
                        <option value="">Select Test Type</option>
                        <?php 
                        $test_types->data_seek(0);
                        while($type = $test_types->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                <?php echo htmlspecialchars($type['test_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editScheduleDate">Scheduled Date & Time *</label>
                    <input type="datetime-local" id="editScheduleDate" name="edit_scheduled_date" required>
                </div>
                
                <div class="form-group">
                    <label for="editSchedulePriority">Priority</label>
                    <select id="editSchedulePriority" name="edit_priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="editScheduleNotes">Notes & Instructions</label>
                    <textarea id="editScheduleNotes" name="edit_notes" placeholder="Any special instructions..." rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Schedule
                </button>
            </div>
        </form>
    </div>
</div>





<!-- Delete Schedule Modal -->
<div id="deleteScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Scheduled Test</h3>
            <span class="close" onclick="closeModal('deleteScheduleModal')">&times;</span>
        </div>
        <form id="deleteScheduleForm" method="POST" action="">
            <input type="hidden" id="deleteScheduleId" name="delete_schedule_id">
            <div class="modal-body">
                <p>Are you sure you want to delete this scheduled test?</p>
                <div style="background: #374151; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>Product ID:</strong> <span id="deleteProductId" style="color: #ef4444;"></span><br>
                    <strong>Test Type:</strong> <span id="deleteTestType"></span><br>
                    <strong>Scheduled Date:</strong> <span id="deleteScheduledDate"></span>
                </div>
                <div style="background: #7f1d1d; color: #fecaca; padding: 12px; border-radius: 5px; margin: 15px 0;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Schedule
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Equipment Details Modal -->
<div id="equipmentModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="equipmentModalTitle">Equipment Details</h3>
            <span class="close" onclick="closeModal('equipmentModal')">&times;</span>
        </div>
        <div class="modal-body" id="equipmentModalBody">
            <!-- Dynamic content will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('equipmentModal')">
                Close
            </button>
            <button type="button" class="btn btn-primary" onclick="editEquipment()">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>
    </div>
</div>

  <!-- Add Equipment Modal -->
<div id="addEquipmentModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Add New Equipment</h3>
            <span class="close" onclick="closeModal('addEquipmentModal')">&times;</span>
        </div>
        <form id="addEquipmentForm" method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="equipmentName">Equipment Name *</label>
                    <input type="text" id="equipmentName" name="equipment_name" required 
                           value="<?php echo htmlspecialchars($_POST['equipment_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="equipmentType">Equipment Type</label>
                    <select id="equipmentType" name="equipment_type" required>
                        <option value="">Select Type</option>
                        <option value="Electrical Test" <?php echo (($_POST['equipment_type'] ?? '') == 'Electrical Test') ? 'selected' : ''; ?>>Electrical Test</option>
                        <option value="Measurement" <?php echo (($_POST['equipment_type'] ?? '') == 'Measurement') ? 'selected' : ''; ?>>Measurement</option>
                        <option value="Environmental Test" <?php echo (($_POST['equipment_type'] ?? '') == 'Environmental Test') ? 'selected' : ''; ?>>Environmental Test</option>
                        <option value="Inspection" <?php echo (($_POST['equipment_type'] ?? '') == 'Inspection') ? 'selected' : ''; ?>>Inspection</option>
                        <option value="Mechanical Test" <?php echo (($_POST['equipment_type'] ?? '') == 'Mechanical Test') ? 'selected' : ''; ?>>Mechanical Test</option>
                        <option value="General" <?php echo (($_POST['equipment_type'] ?? '') == 'General') ? 'selected' : ''; ?>>General</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <input type="text" id="model" name="model" required value=" <?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="serialNumber">Serial Number</label>
                    <input type="text" id="serialNumber" required name="serial_number" value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="Operational">Operational</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Calibration">Calibration</option>
                        <option value="Out of Service">Out of Service</option>
                        <option value="Offline">Offline</option>
                    </select>
                </div>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label for="lastMaintenance">Last Maintenance</label>
                        <input type="date" id="lastMaintenance" required name="last_maintenance" value="<?php echo htmlspecialchars($_POST['last_maintenance'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="nextMaintenance">Next Maintenance</label>
                        <input type="date" id="nextMaintenance" required name="next_maintenance" value="<?php echo htmlspecialchars($_POST['next_maintenance'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" placeholder="e.g., Lab A">
                </div>
                <div class="form-group full-width">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" required rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addEquipmentModal')">Cancel</button>
                <!-- CHANGED: Added name and value to identify the form submission -->
                <button type="submit" name="add_equipment_submit" value="1" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Equipment
                </button>
            </div>
        </form>
    </div>
</div>

                                <!-- ******************************************************** -->
















<?php 
include('shared1/footer.php');
?>



    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

<script>
            
            
            
            
            // Initialize particles
            if (typeof particlesJS !== 'undefined') {
                particlesJS("particles-js", {
                    particles: {
                        number: { value: 80, density: { enable: true, value_area: 800 } },
                        color: { value: "#64ffda" },
                        shape: { type: "circle" },
                        opacity: { value: 0.5, random: true },
                        size: { value: 3, random: true },
                        line_linked: {
                            enable: true,
                            distance: 150,
                            color: "#64ffda",
                            opacity: 0.2,
                            width: 1
                        },
                        move: {
                            enable: true,
                            speed: 2,
                            direction: "none",
                            random: true,
                            straight: false,
                            out_mode: "out",
                            bounce: false
                        }
                    },
                    interactivity: {
                        detect_on: "canvas",
                        events: {
                            onhover: { enable: true, mode: "repulse" },
                            onclick: { enable: true, mode: "push" },
                            resize: true
                        }
                    }
                });
            }

    // Initialize Charts with Real Data
document.addEventListener('DOMContentLoaded', function() {
     const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
    console.log('DOM loaded - initializing everything');
        const savedTheme = localStorage.getItem('theme') || 'dark';
    const themeBtn = document.querySelector('.btn-outline[onclick*="toggleTheme"]');
    
    if (savedTheme === 'light') {
        document.body.classList.add('light-theme');
        if (themeBtn) {
            const icon = themeBtn.querySelector('i');
            const textSpan = themeBtn.querySelector('span');
            if (icon) icon.className = 'fas fa-sun';
            if (textSpan) textSpan.textContent = 'Light';
        }
    }
    
    // Your existing chart code here...
    const resultsCtx = document.getElementById('resultsChart').getContext('2d');
    const resultsChart = new Chart(resultsCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: [
                    'rgba(248, 34, 34, 0.8)',
                    'rgba(229, 239, 47, 0.97)',
                    'rgba(8, 190, 11, 0.8)',
                    'rgba(6, 182, 212, 0.8)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(6, 182, 212, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#f1f5f9',
                        font: {
                            size: 12
                        },
                        padding: 15
                    }
                }
            }
        }
    });
  mobileMenuBtn.addEventListener('click', function() {
                mobileNav.classList.add('active');
                mobileNavOverlay.classList.add('active');
            });
            
            closeMobileMenu.addEventListener('click', function() {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
            });
            
            mobileNavOverlay.addEventListener('click', function() {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
            });

    // Test Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [
                {
                    label: 'Total Tests',
                    data: <?php echo json_encode($trend_data); ?>,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Passed Tests',
                    data: <?php echo json_encode($trend_pass_data); ?>,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Failed Tests',
                    data: <?php echo json_encode($trend_fail_data); ?>,
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#f1f5f9'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#f1f5f9'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#f1f5f9',
                        font: {
                            size: 12
                        },
                        padding: 15
                    }
                }
            }
        }
    });

    // Initialize modal functionality
    initializeModals();
    
    // Initialize search functionality
    initializeSearch();
});
// REAL DATABASE OPERATIONS - UPDATE AND DELETE
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - initializing database operations');
    
    // Initialize everything
    initializeModals();
    initializeSearch();
});

// MODAL SYSTEM WITH DATABASE OPERATIONS
function initializeModals() {
    console.log('Initializing modals with database operations...');
    
    // Add click listeners to action buttons
    document.addEventListener('click', function(e) {
        // View button
        if (e.target.closest('.btn-view')) {
            const btn = e.target.closest('.btn-view');
            const testId = btn.getAttribute('data-testid');
            const productId = btn.getAttribute('data-productid');
            const testType = btn.getAttribute('data-testtype');
            openViewModal(testId, productId, testType);
        }
        
        // Edit button
        if (e.target.closest('.btn-edit')) {
            const btn = e.target.closest('.btn-edit');
            const testId = btn.getAttribute('data-testid');
            const status = btn.getAttribute('data-status');
            openEditModal(testId, status);
        }
        
        // Delete button
         if (e.target.closest('.btn-delete')) {
        const btn = e.target.closest('.btn-delete');
        const testId = btn.getAttribute('data-testid');
        openDeleteModal(testId);
    }
      if (e.target.closest('.btn-schedule-delete')) {
            const btn = e.target.closest('.btn-schedule-delete');
            // This uses onclick, so no need for data attributes
            return; // Let the onclick handle it
        }
        
        
        // Close buttons
        if (e.target.classList.contains('close')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
    });
    
    // Modal background click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    
    // Edit form submission
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleEditSubmit(e);
        });
    }
}

// MODAL FUNCTIONS
function openViewModal(testId, productId, testType) {
    const modal = document.getElementById('viewModal');
    if (!modal) return;
    
    document.getElementById('viewTestId').textContent = testId || 'N/A';
    document.getElementById('viewProductId').textContent = productId || 'N/A';
    document.getElementById('viewTestType').textContent = testType || 'N/A';
    modal.style.display = 'block';
}

function openEditModal(testId, currentStatus,result) {
    const modal = document.getElementById('editModal');
    if (!modal) return;
    
    // Fill the form with current data
    document.getElementById('editTestId').value = testId || '';
    document.getElementById('editStatus').value = currentStatus || '';
        document.getElementById('editResult').value = result || '';

    // Set default values
   
    
    modal.style.display = 'block';
}

function openDeleteModal(testId) {
    const modal = document.getElementById('deleteModal');
    if (!modal) return;
    
    document.getElementById('deleteTestId').textContent = testId || '';
    modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// REAL DATABASE UPDATE FUNCTION
async function handleEditSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const testId = formData.get('test_id');
    
    console.log('Updating record:', {
        test_id: testId,
        status: formData.get('status'),
        result: formData.get('result'),
        remarks: formData.get('remarks')
    });
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '⏳ Updating Database...';
    submitBtn.disabled = true;
    
    try {
        // Send update request to PHP
        const response = await fetch('update_test.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Database Updated!', result.message);
            
            // Update the table row immediately
// LAST RESORT - RELOAD THE TABLE SECTION
function updateTableRow(testId, newStatus, newResult) {
    console.log('🔄 RELOADING TABLE SECTION');
    
    // Show success message
    showNotification('success', 'Database Updated!', 'Refreshing table data...');
    
    // Store the scroll position
    const scrollPos = window.scrollY;
    
    // Reload only the table section after a short delay
    setTimeout(() => {
        // This will make the browser refetch the data
        window.location.hash = 'reload';
        window.location.reload();
    }, 1000);
    
    // Restore scroll position after reload
    window.addEventListener('load', () => {
        window.scrollTo(0, scrollPos);
    });
}            
            // Close modal
            closeModal('editModal');
            
            console.log('Database update successful for:', testId);
        } else {
            showNotification('error', 'Update Failed!', result.message);
            console.error('Update failed:', result.message);
        }
    } catch (error) {
        console.error('Error updating record:', error);
        showNotification('error', 'Network Error!', 'Failed to connect to server');
    } finally {
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
    

    
}
// REAL DATABASE DELETE FUNCTION
async function handleDelete() {
    const testId = document.getElementById('deleteTestId').textContent.trim();
    
    if (!testId || testId === '-') {
        showNotification('error', 'Delete Error', 'No test ID found');
        return;
    }
    
    console.log('Deleting record from database:', testId);
    
    // Show loading state
    const deleteBtn = document.querySelector('#deleteModal button[onclick*="handleDelete"]');
    if (!deleteBtn) {
        showNotification('error', 'Delete Error', 'Delete button not found');
        return;
    }
    
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    deleteBtn.disabled = true;
    
    try {
        // Prepare form data
        const formData = new FormData();
        formData.append('test_id', testId);
        
        console.log('Sending delete request for:', testId);
        
        // Send delete request to PHP
        const response = await fetch('delete_test.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log('Delete response:', result);
        
        if (result.success) {
            showNotification('success', '✅ Success', result.message);
            
            // Remove from table immediately
            removeTableRow(testId);
            
            // Close modal
            closeModal('deleteModal');
            
            console.log('Database delete successful for:', testId);
        } else {
            showNotification('error', '❌ Delete Failed', result.message || 'Unknown error');
            console.error('Delete failed:', result.message);
        }
    } catch (error) {
        console.error('Error deleting record:', error);
        showNotification('error', '⚠️ Network Error', 'Failed to connect to server. Please check your internet connection.');
    } finally {
        // Reset button
        if (deleteBtn) {
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        }
    }
}

// Helper function to remove table row
function removeTableRow(testId) {
    console.log('Removing row for test:', testId);
    
    const table = document.getElementById('historyTable');
    if (!table) {
        console.error('History table not found');
        return;
    }
    
    const rows = table.getElementsByTagName('tr');
    let rowRemoved = false;
    
    for (let i = 1; i < rows.length; i++) { // Start from 1 to skip header
        const cells = rows[i].getElementsByTagName('td');
        if (cells.length > 0 && cells[0].textContent.trim() === testId) {
            rows[i].remove();
            rowRemoved = true;
            console.log('Table row removed for:', testId);
            break;
        }
    }
    
    if (!rowRemoved) {
        console.warn('Row not found for test:', testId);
        // Refresh the page to sync data
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}
// UPDATE TABLE ROW AFTER SUCCESSFUL DATABASE UPDATE
function updateTableRow(testId, newStatus, newResult) {
    const table = document.getElementById('historyTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        if (cells[0].textContent === testId) {
            // Update status cell (index 6)
            const statusCell = cells[6];
            const statusClass = newStatus.toLowerCase().replace(/ /g, '-');
            statusCell.innerHTML = `<span class="status-badge status-${statusClass}">${newStatus}</span>`;
            
            // Update result cell (index 5)
            const resultCell = cells[5];
            const resultClass = newResult.toLowerCase();
            resultCell.innerHTML = `<span class="status-badge status-${resultClass}">${newResult}</span>`;
            
            // Update data attribute on edit button
            const editButton = cells[8].querySelector('.btn-edit');
            if (editButton) {
                editButton.setAttribute('data-status', newStatus);
            }
            
            console.log('Table row updated for:', testId);
            showNotification('info', 'Table Updated', 'Table refreshed with new data');
            break;
        }
    }
}
// CORRECTED TAB SWITCHING FUNCTION
function switchTab(tabName, event) {
    console.log('Switching to tab:', tabName);
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        console.log('Hiding:', tab.id);
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab content
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.classList.add('active');
        console.log('Showing:', tabName);
    } else {
        console.error('Tab content not found:', tabName);
    }
    
    // Add active class to clicked tab
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // If performance tab, load performance data
    if (tabName === 'performance') {
        loadPerformanceData();
    }
}
// REMOVE TABLE ROW AFTER SUCCESSFUL DATABASE DELETE
function removeTableRow(testId) {
    const table = document.getElementById('historyTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        if (cells[0].textContent === testId) {
            rows[i].remove();
            console.log('Table row removed for:', testId);
            showNotification('info', 'Table Updated', 'Record removed from table');
            break;
        }
    }
}

// NOTIFICATION SYSTEM
function showNotification(type, title, message) {
    console.log('Notification:', type, title, message);
    
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    
    notification.innerHTML = `
        <div style="font-weight: bold; margin-bottom: 5px;">${title}</div>
        <div>${message}</div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// SEARCH FUNCTIONALITY
function initializeSearch() {
    const searchInput = document.getElementById("searchInput");
    const dateInput = document.getElementById("dateInput");
    const clearBtn = document.getElementById("clearBtn");

    if (searchInput && dateInput && clearBtn) {
        searchInput.addEventListener("keyup", filterHistory);
        dateInput.addEventListener("change", filterHistory);
        clearBtn.addEventListener("click", clearSearch);
    }
}

function filterHistory() {
    const searchValue = document.getElementById("searchInput").value.toLowerCase();
    const dateValue = document.getElementById("dateInput").value;

    const table = document.getElementById("historyTable");
    const rows = table.getElementsByTagName("tr");

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName("td");
        let textMatch = false;
        let dateMatch = false;

        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.toLowerCase().includes(searchValue)) {
                textMatch = true;
                break;
            }
        }

        if (!dateValue || cells[4].textContent.startsWith(dateValue)) {
            dateMatch = true;
        }

        rows[i].style.display = (textMatch && dateMatch) ? "" : "none";
    }
}

function clearSearch() {
    document.getElementById("searchInput").value = "";
    document.getElementById("dateInput").value = "";
    filterHistory();
}


// Theme toggle function
function toggleTheme() {
    const body = document.body;
    const themeBtn = event.target.closest('.btn');
    const icon = themeBtn.querySelector('i');
    const textSpan = themeBtn.querySelector('span');
    
    // Toggle between light and dark theme
    if (body.classList.contains('light-theme')) {
        // Switch to dark theme
        body.classList.remove('light-theme');
        icon.className = 'fas fa-moon';
        if (textSpan) textSpan.textContent = 'Dark';
        
        // Save preference
        localStorage.setItem('theme', 'dark');
        showNotification('info', '🌙 Dark Mode', 'Switched to dark theme');
    } else {
        // Switch to light theme
        body.classList.add('light-theme');
        icon.className = 'fas fa-sun';
        if (textSpan) textSpan.textContent = 'Light';
        
        // Save preference
        localStorage.setItem('theme', 'light');
        showNotification('info', '☀️ Light Mode', 'Switched to light theme');
    }
}
function refreshData() {
    const btn = event.target;
    const originalHtml = btn.innerHTML;
    
    showNotification('info', ' Data Synchronization', 
        '• Updating test records database\n• Refreshing analytics dashboard\n• Loading latest equipment status\n• Generating updated reports');
    
    btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Synchronizing...';
    btn.disabled = true;
    
    
}
    
    // SCHEDULE FUNCTIONS
function openScheduleModal() {
    console.log('Opening schedule modal...');
    
    const modal = document.getElementById('scheduleModal');
    if (!modal) {
        showNotification('error', 'Error', 'Schedule modal not found');
        return;
    }
    
    // Set minimum date to current time
    const now = new Date();
    now.setMinutes(now.getMinutes() + 30); // Minimum 30 minutes from now
    const timezoneOffset = now.getTimezoneOffset() * 60000;
    const localISOTime = new Date(now - timezoneOffset).toISOString().slice(0, 16);
    
    document.getElementById('scheduleDate').min = localISOTime;
    document.getElementById('scheduleDate').value = localISOTime;
    
    // Reset form
    document.getElementById('scheduleForm').reset();
    
    modal.style.display = 'block';
}
function openScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
// Simple modal functions
function openScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Simple form submission - let the page reload
function handleScheduleTest(event) {
    event.preventDefault();
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling...';
    submitBtn.disabled = true;
    
    // Let the form submit normally (page will reload)
    event.target.submit();
}
// Edit Schedule Modal
function openEditScheduleModal(id, productId, testTypeId, scheduledDate, priority, notes) {
    document.getElementById('editScheduleId').value = id;
    document.getElementById('editScheduleProductId').value = productId;
    document.getElementById('editScheduleTestType').value = testTypeId;
    document.getElementById('editScheduleDate').value = scheduledDate.replace(' ', 'T');
    document.getElementById('editSchedulePriority').value = priority;
    document.getElementById('editScheduleNotes').value = notes || '';
    
    document.getElementById('editScheduleModal').style.display = 'block';
}

// Delete Schedule Modal
function openDeleteScheduleModal(id, productId, testType, scheduledDate) {
    document.getElementById('deleteScheduleId').value = id;
    document.getElementById('deleteProductId').textContent = productId;
    document.getElementById('deleteTestType').textContent = testType;
    document.getElementById('deleteScheduledDate').textContent = scheduledDate;
    
    document.getElementById('deleteScheduleModal').style.display = 'block';
}
function startScheduledTest(scheduleId) {
    if (confirm('Start this scheduled test now?')) {
        // Show loading
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
        btn.disabled = true;
        
        // Send request to convert schedule to test
        const formData = new FormData();
        formData.append('start_schedule_id', scheduleId);
        
        fetch('log_test.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('success', 'Test Started!', 'Scheduled test converted to active test');
                // Reload page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showNotification('error', 'Failed to Start', result.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Network Error', 'Failed to connect to server');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}



function exportData() {
    // Show export options
    showNotification('info', '📊 Export Options', 
        'Choose export format:<br>' +
        '<button onclick="exportToCSV()" style="margin: 5px; padding: 8px 15px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer;">CSV</button>' +
        '<button onclick="exportToExcel()" style="margin: 5px; padding: 8px 15px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">Excel</button>' +
        '<button onclick="exportToPDF()" style="margin: 5px; padding: 8px 15px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;">PDF</button>' +
        '<button onclick="exportAllData()" style="margin: 5px; padding: 8px 15px; background: #8b5cf6; color: white; border: none; border-radius: 4px; cursor: pointer;">All Data</button>'
    );
}

// Export to CSV
function exportToCSV() {
    showNotification('info', '📥 Exporting', 'Preparing CSV file...');
    
    // Get table data
    const table = document.getElementById('historyTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add headers
    const headers = [];
    table.querySelectorAll('th').forEach(th => {
        headers.push(th.textContent);
    });
    csv.push(headers.join(','));
    
    // Add data rows
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(td => {
            // Remove HTML tags and clean text
            let text = td.textContent || td.innerText;
            text = text.replace(/,/g, ';').replace(/\n/g, ' ');
            rowData.push(`"${text}"`);
        });
        if (rowData.length > 0) {
            csv.push(rowData.join(','));
        }
    });
    
    // Create and download file
    downloadFile(csv.join('\n'), 'test_data.csv', 'text/csv');
    showNotification('success', '✅ Export Complete', 'CSV file downloaded');
}

// Export to Excel (using CSV format)
function exportToExcel() {
    // For simple Excel export, we can use CSV
    exportToCSV();
}

// Export to PDF (using print)
function exportToPDF() {
    showNotification('info', '📄 Exporting', 'Opening print dialog...');
    
    // Create a printable version
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Test Report - ${new Date().toLocaleDateString()}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #333; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #3b82f6; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; }
                .status-pass { background: #10b981; color: white; }
                .status-fail { background: #ef4444; color: white; }
                .status-pending { background: #f59e0b; color: white; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <h1>Test Report - Labify Pro</h1>
            <p>Generated: ${new Date().toLocaleString()}</p>
            ${document.getElementById('historyTable').outerHTML}
            <div class="footer">
                <p>Total Tests: ${document.querySelector('.metric-card .metric-value').textContent}</p>
                <p>Success Rate: ${document.querySelectorAll('.metric-card .metric-value')[1].textContent}</p>
                <p>© ${new Date().getFullYear()} Labify Pro - All rights reserved</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Export all data (including schedule, metrics, etc.)
function exportAllData() {
    showNotification('info', '📦 Exporting All Data', 'Please wait...');
    
    // Create comprehensive report
    const report = {
        exportDate: new Date().toISOString(),
        metrics: {
            totalTests: document.querySelectorAll('.metric-card .metric-value')[0].textContent,
            successRate: document.querySelectorAll('.metric-card .metric-value')[1].textContent,
            pendingTests: document.querySelectorAll('.metric-card .metric-value')[2].textContent,
            inProgress: document.querySelectorAll('.metric-card .metric-value')[3].textContent,
            failedTests: document.querySelectorAll('.metric-card .metric-value')[4].textContent,
            avgDuration: document.querySelectorAll('.metric-card .metric-value')[5].textContent
        },
        recentTests: [],
        scheduledTests: []
    };
    
    // Collect recent tests
    const historyTable = document.getElementById('historyTable');
    const rows = historyTable.querySelectorAll('tr');
    rows.forEach((row, index) => {
        if (index > 0) { // Skip header
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                report.recentTests.push({
                    testId: cells[0].textContent,
                    productId: cells[1].textContent,
                    productType: cells[2].textContent,
                    testType: cells[3].textContent,
                    testDate: cells[4].textContent,
                    result: cells[5].textContent,
                    status: cells[6].textContent,
                    tester: cells[7].textContent
                });
            }
        }
    });
    
    // Convert to JSON and download
    const jsonData = JSON.stringify(report, null, 2);
    downloadFile(jsonData, `labify_export_${Date.now()}.json`, 'application/json');
    showNotification('success', '✅ Export Complete', 'All data exported as JSON');
}

// Helper function to download files
function downloadFile(content, fileName, contentType) {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function printDashboard() {
    showNotification('info', '🖨️ Printing', 'Preparing dashboard for print...');
    
    // Create a print-friendly version
    const printWindow = window.open('', '_blank');
    const printDate = new Date().toLocaleString();
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Labify Pro Dashboard - Print</title>
            <style>
                @media print {
                    @page {
                        size: A4 landscape;
                        margin: 15mm;
                    }
                }
                
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    color: #333;
                    line-height: 1.4;
                    margin: 0;
                    padding: 20px;
                    background: white !important;
                }
                
                .print-header {
                    text-align: center;
                    border-bottom: 3px solid #3b82f6;
                    padding-bottom: 15px;
                    margin-bottom: 25px;
                }
                
                .print-header h1 {
                    color: #1e40af;
                    margin: 0 0 5px 0;
                    font-size: 28px;
                }
                
                .print-header .subtitle {
                    color: #666;
                    font-size: 14px;
                }
                
                .print-meta {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 20px;
                    font-size: 12px;
                    color: #666;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 10px;
                }
                
                .print-section {
                    margin-bottom: 25px;
                    page-break-inside: avoid;
                }
                
                .print-section h2 {
                    background: #3b82f6;
                    color: white;
                    padding: 8px 15px;
                    border-radius: 4px;
                    margin: 0 0 15px 0;
                    font-size: 18px;
                }
                
                .metrics-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 15px;
                    margin-bottom: 20px;
                }
                
                .metric-print {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                }
                
                .metric-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #1e40af;
                    margin: 10px 0;
                }
                
                .metric-label {
                    font-size: 12px;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                    font-size: 12px;
                }
                
                th {
                    background: #f1f5f9;
                    color: #475569;
                    padding: 10px;
                    text-align: left;
                    border-bottom: 2px solid #cbd5e1;
                    font-weight: 600;
                }
                
                td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #e2e8f0;
                }
                
                tr:nth-child(even) {
                    background: #f8fafc;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .status-pass { background: #d1fae5; color: #065f46; }
                .status-fail { background: #fee2e2; color: #991b1b; }
                .status-pending { background: #fef3c7; color: #92400e; }
                .status-in-progress { background: #dbeafe; color: #1e40af; }
                
                .priority-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 12px;
                    font-size: 10px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .priority-low { background: #d1fae5; color: #065f46; }
                .priority-medium { background: #dbeafe; color: #1e40af; }
                .priority-high { background: #fef3c7; color: #92400e; }
                .priority-urgent { background: #fee2e2; color: #991b1b; }
                
                .print-footer {
                    margin-top: 40px;
                    padding-top: 15px;
                    border-top: 2px solid #e2e8f0;
                    text-align: center;
                    font-size: 11px;
                    color: #64748b;
                }
                
                .page-break {
                    page-break-before: always;
                }
                
                .no-print {
                    display: none !important;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>Labify Pro - Testing Dashboard Report</h1>
                <div class="subtitle">Comprehensive Testing Analytics Report</div>
            </div>
            
            <div class="print-meta">
                <div>Generated: ${printDate}</div>
                <div>Report ID: ${Date.now()}</div>
                <div>User: <?php echo htmlspecialchars($tested_by_name); ?></div>
            </div>
            
            <!-- Dashboard Metrics -->
            <div class="print-section">
                <h2>Dashboard Metrics</h2>
                <div class="metrics-grid">
                    <div class="metric-print">
                        <div class="metric-label">Total Tests</div>
                        <div class="metric-value"><?php echo $total_tests; ?></div>
                    </div>
                    <div class="metric-print">
                        <div class="metric-label">Success Rate</div>
                        <div class="metric-value"><?php echo $success_rate; ?>%</div>
                    </div>
                    <div class="metric-print">
                        <div class="metric-label">Pending Tests</div>
                        <div class="metric-value"><?php echo $pending_tests; ?></div>
                    </div>
                    <div class="metric-print">
                        <div class="metric-label">In Progress</div>
                        <div class="metric-value"><?php echo $in_progress_tests; ?></div>
                    </div>
                    <div class="metric-print">
                        <div class="metric-label">Failed Tests</div>
                        <div class="metric-value"><?php echo $failed_tests; ?></div>
                    </div>
                    <div class="metric-print">
                        <div class="metric-label">Avg. Duration</div>
                        <div class="metric-value"><?php echo round($avg_duration, 1); ?>h</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Tests -->
            <div class="print-section">
                <h2>Recent Test History</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Test ID</th>
                            <th>Product ID</th>
                            <th>Test Type</th>
                            <th>Test Date</th>
                            <th>Result</th>
                            <th>Status</th>
                            <th>Tester</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recent_tests->data_seek(0);
                        while($test = $recent_tests->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($test['test_id']); ?></td>
                            <td><?php echo htmlspecialchars($test['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($test['test_type_id']); ?></td>
                            <td><?php echo htmlspecialchars($test['test_date']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($test['result']); ?>">
                                    <?php echo htmlspecialchars($test['result']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $test['status'])); ?>">
                                    <?php echo htmlspecialchars($test['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($test['tested_by']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Scheduled Tests -->
            <div class="print-section">
                <h2>Scheduled Tests</h2>
                <?php 
                $all_scheduled = $conn->query("SELECT * FROM test_schedule ORDER BY scheduled_date ASC");
                if ($all_scheduled->num_rows > 0): 
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Test Type</th>
                            <th>Scheduled Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($schedule = $all_scheduled->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['test_type_id']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($schedule['scheduled_date'])); ?></td>
                            <td>
                                <span class="priority-badge priority-<?php echo $schedule['priority']; ?>">
                                    <?php echo ucfirst($schedule['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-scheduled">
                                    Scheduled
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No tests scheduled</p>
                <?php endif; ?>
            </div>
            
            <!-- Test Distribution Chart (static) -->
            <div class="print-section">
                <h2>Test Results Distribution</h2>
                <div style="display: flex; justify-content: space-around; align-items: center; padding: 20px;">
                    <?php
                    $results_distribution->data_seek(0);
                    $total_tests_dist = 0;
                    while($dist = $results_distribution->fetch_assoc()) {
                        $total_tests_dist += $dist['count'];
                    }
                    $results_distribution->data_seek(0);
                    while($dist = $results_distribution->fetch_assoc()):
                        $percentage = round(($dist['count'] / $total_tests_dist) * 100, 1);
                    ?>
                    <div style="text-align: center; margin: 0 15px;">
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: conic-gradient(
                            <?php 
                            $colors = [
                                'Pass' => '#10b981',
                                'Fail' => '#ef4444', 
                                'Inconclusive' => '#f59e0b'
                            ];
                            echo $colors[$dist['result']] ?? '#6b7280';
                            ?> 0% <?php echo $percentage; ?>%, 
                            #e5e7eb <?php echo $percentage; ?>% 100%
                        ); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            <div style="width: 70px; height: 70px; background: white; border-radius: 50%;"></div>
                        </div>
                        <div style="font-weight: bold; color: #1e40af;"><?php echo $dist['result']; ?></div>
                        <div style="font-size: 12px; color: #666;"><?php echo $dist['count']; ?> tests (<?php echo $percentage; ?>%)</div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="print-footer">
                <p>© <?php echo date('Y'); ?> Labify Pro - Confidential Testing Dashboard Report</p>
                <p>This report is generated automatically and contains sensitive testing information.</p>
                <p>Page 1 of 1</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Auto-print after content loads
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 1000);
}

 // Show the button when user scrolls down 20px from the top
window.onscroll = function() {
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        document.getElementById("scrollTopBtn").style.display = "block";
    } else {
        document.getElementById("scrollTopBtn").style.display = "none";
    }
};
let currentEquipment = null;

// Equipment Functions
function showEquipmentDetails(equipmentJson) {
    try {
        const equipment = JSON.parse(equipmentJson);
        console.log('Showing equipment details:', equipment);
                window.currentEquipment = equipment;

        // Format dates
        const formatDate = (dateStr) => {
            if (!dateStr) return 'Not set';
            return new Date(dateStr).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        };
        // In the showEquipmentDetails function, replace the modalFooter section with this:
const modalFooter = document.querySelector('#equipmentModal .modal-footer');
if (modalFooter) {
    modalFooter.innerHTML = `
        <button type="button" class="btn btn-outline" onclick="closeModal('equipmentModal')">
            Close
        </button>
        <button type="button" class="btn btn-danger" onclick="deleteCurrentEquipment()">
                    <i class="fas fa-trash"></i> Delete
                </button>
        <button type="button" class="btn btn-primary" onclick="editEquipmentById(${equipment.id})">
            <i class="fas fa-edit"></i> Edit Equipment
        </button>
    `;
}
        


// Close modal
function closeEquipmentModal() {
    document.getElementById('equipmentModal').style.display = 'none';
}
        // Create equipment details HTML
        const html = `
            <div style="padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 8px; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div style="font-size: 32px; color: #3b82f6;">
                        <i class="${getEquipmentIcon(equipment.equipment_type)}"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: white;">${equipment.equipment_name}</h4>
                        <p style="margin: 5px 0 0 0; color: #9ca3af;">
                            ${equipment.model || 'No model'} • ${equipment.manufacturer || 'No manufacturer'}
                        </p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <strong>Status:</strong><br>
                        <span class="status-badge" style="background: ${getStatusColor(equipment.status)};">
                            ${equipment.status}
                        </span>
                    </div>
                    <div>
                        <strong>Serial Number:</strong><br>
                        ${equipment.serial_number || 'N/A'}
                    </div>
                    <div>
                        <strong>Type:</strong><br>
                        ${equipment.equipment_type}
                    </div>
                    <div>
                        <strong>Location:</strong><br>
                        ${equipment.location || 'N/A'}
                    </div>
                    <div>
                        <strong>Last Maintenance:</strong><br>
                        ${formatDate(equipment.last_maintenance)}
                    </div>
                    <div>
                        <strong>Next Maintenance:</strong><br>
                        ${formatDate(equipment.next_maintenance)}
                    </div>
                </div>
                
                ${equipment.notes ? `
                <div style="margin-top: 15px;">
                    <strong>Notes:</strong><br>
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 10px; border-radius: 5px; margin-top: 5px;">
                        ${equipment.notes}
                    </div>
                </div>
                ` : ''}
            </div>
            
            <div style="background: rgba(239, 68, 68, 0.1); color: #fecaca; padding: 10px; border-radius: 5px; border-left: 4px solid #ef4444; margin-top: 15px;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Maintenance History:</strong> Last calibrated on ${formatDate(equipment.last_maintenance)}
            </div>
        `;
        
        // Show in modal
        document.getElementById('equipmentModalTitle').textContent = equipment.equipment_name;
        document.getElementById('equipmentModalBody').innerHTML = html;
        document.getElementById('equipmentModal').style.display = 'block';
        
    } catch (error) {
        console.error('Error showing equipment details:', error);
        showNotification('error', 'Error', 'Failed to load equipment details');
    }
}

function getEquipmentIcon(type) {
    const iconMap = {
        'Electrical Test': 'fas fa-bolt',
        'Measurement': 'fas fa-ruler-combined',
        'Environmental Test': 'fas fa-thermometer-half',
        'Inspection': 'fas fa-microscope',
        'Mechanical Test': 'fas fa-cogs',
        'Electrical': 'fas fa-plug',
        'General': 'fas fa-tools'
    };
    return iconMap[type] || 'fas fa-tools';
}

function getStatusColor(status) {
    const colorMap = {
        'Operational': '#10b981',
        'Maintenance': '#f59e0b',
        'Calibration': '#3b82f6',
        'Out of Service': '#ef4444',
        'Offline': '#6b7280'
    };
    return colorMap[status] || '#6b7280';
}

function openEquipmentModal() {
    document.getElementById('addEquipmentModal').style.display = 'block';
}

function refreshEquipment() {
    showNotification('info', 'Refreshing', 'Loading latest equipment data...');
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

function addSampleEquipment() {
    if (confirm('Add sample equipment data to the database?')) {
        fetch('add_sample_equipment.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Success', 'Sample equipment added successfully');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to add sample equipment');
                }
            })
            .catch(error => {
                showNotification('error', 'Error', 'Failed to add sample equipment');
            });
    }
}
// Equipment Edit Function
function editEquipment() {
    // Get the current equipment data from the modal
    const modalBody = document.getElementById('equipmentModalBody');
    const equipmentName = document.getElementById('equipmentModalTitle').textContent;
    
    // Try to extract equipment details from the modal
    let equipmentData = {};
    
    // Extract data from the current modal view (simplified approach)
    const statusBadge = modalBody.querySelector('.status-badge');
    const paragraphs = modalBody.querySelectorAll('div > div > div');
    
    equipmentData.name = equipmentName;
    equipmentData.status = statusBadge ? statusBadge.textContent.trim() : 'Operational';
    
    // For now, we'll just open the add equipment modal with empty fields
    // In a real implementation, you would fetch the full equipment data from the server
    
    showNotification('info', 'Edit Equipment', 
        'Edit functionality would fetch equipment details from server and populate the form. ' +
        'For now, please use the "Add Equipment" button and update manually.');
    
    // Close the details modal
    closeModal('equipmentModal');
    
    // You could open the add equipment modal here
    // openEquipmentModalWithData(equipmentData);
}

// Alternative: Open edit modal with equipment ID
function editEquipmentById(equipmentId) {
    if (!equipmentId) {
        showNotification('error', 'Error', 'No equipment ID provided');
        return;
    }
    
    // Fetch equipment details from server
    fetch(`get_equipment.php?id=${equipmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openEditEquipmentModal(data.equipment);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to load equipment details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'Failed to load equipment details');
        });
}
// Delete current equipment function
function deleteCurrentEquipment() {
    if (!window.currentEquipment || !window.currentEquipment.id) {
        showNotification('error', 'Error', 'No equipment selected for deletion');
        return;
    }
    
    const equipmentId = window.currentEquipment.id;
    const equipmentName = window.currentEquipment.equipment_name;
    
    // Confirm deletion
    if (!confirm(`Are you sure you want to delete "${equipmentName}"? This action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const deleteBtn = document.querySelector('#equipmentModal .modal-footer .btn-danger');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    deleteBtn.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('equipment_id', equipmentId);
    
    // Send delete request
    fetch('delete_equipment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showNotification('success', '✅ Equipment Deleted', result.message);
            closeModal('equipmentModal');
            
            // Remove the equipment item from the grid
            removeEquipmentItem(equipmentId);
            
            // Refresh equipment data
            setTimeout(() => {
                refreshEquipment();
            }, 1000);
        } else {
            showNotification('error', '❌ Delete Failed', result.message || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error deleting equipment:', error);
        showNotification('error', '⚠️ Network Error', 'Failed to connect to server');
    })
    .finally(() => {
        // Reset button
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    });
}

// Helper function to remove equipment item from the grid
function removeEquipmentItem(equipmentId) {
    const equipmentItems = document.querySelectorAll('.equipment-item');
    equipmentItems.forEach(item => {
        if (item.getAttribute('onclick') && item.getAttribute('onclick').includes(equipmentId)) {
            item.remove();
            return;
        }
    });
}
// Open edit modal with equipment data
function openEditEquipmentModal(equipment) {
    console.log('Opening edit modal for:', equipment);
    
    // Create edit modal HTML
    const modalHTML = `
        <div id="editEquipmentModal" class="modal">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3>Edit Equipment: ${equipment.equipment_name}</h3>
                    <span class="close" onclick="closeModal('editEquipmentModal')">&times;</span>
                </div>
                <form id="editEquipmentForm" method="POST" action="update_equipment.php">
                    <input type="hidden" name="equipment_id" value="${equipment.id || ''}">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="editEquipmentName">Equipment Name *</label>
                            <input type="text" id="editEquipmentName" name="equipment_name" 
                                   value="${equipment.equipment_name || ''}" required>
                        </div>
                        <div class="form-group">
                            <label for="editEquipmentType">Equipment Type</label>
                            <select id="editEquipmentType" name="equipment_type" required>
                                <option value="">Select Type</option>
                                <option value="Electrical Test" ${equipment.equipment_type === 'Electrical Test' ? 'selected' : ''}>Electrical Test</option>
                                <option value="Measurement" ${equipment.equipment_type === 'Measurement' ? 'selected' : ''}>Measurement</option>
                                <option value="Environmental Test" ${equipment.equipment_type === 'Environmental Test' ? 'selected' : ''}>Environmental Test</option>
                                <option value="Inspection" ${equipment.equipment_type === 'Inspection' ? 'selected' : ''}>Inspection</option>
                                <option value="Mechanical Test" ${equipment.equipment_type === 'Mechanical Test' ? 'selected' : ''}>Mechanical Test</option>
                                <option value="General" ${!equipment.equipment_type || equipment.equipment_type === 'General' ? 'selected' : ''}>General</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editModel">Model</label>
                            <input type="text" id="editModel" name="model" value="${equipment.model || ''}">
                        </div>
                        <div class="form-group">
                            <label for="editSerialNumber">Serial Number</label>
                            <input type="text" id="editSerialNumber" name="serial_number" value="${equipment.serial_number || ''}">
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Status</label>
                            <select id="editStatus" name="status" required>
                                <option value="Operational" ${equipment.status === 'Operational' ? 'selected' : ''}>Operational</option>
                                <option value="Maintenance" ${equipment.status === 'Maintenance' ? 'selected' : ''}>Maintenance</option>
                                <option value="Calibration" ${equipment.status === 'Calibration' ? 'selected' : ''}>Calibration</option>
                                <option value="Out of Service" ${equipment.status === 'Out of Service' ? 'selected' : ''}>Out of Service</option>
                                <option value="Offline" ${equipment.status === 'Offline' ? 'selected' : ''}>Offline</option>
                            </select>
                        </div>
                        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                            <div class="form-group">
                                <label for="editLastMaintenance">Last Maintenance</label>
                                <input type="date" id="editLastMaintenance" name="last_maintenance" 
                                       value="${equipment.last_maintenance || ''}">
                            </div>
                            <div class="form-group">
                                <label for="editNextMaintenance">Next Maintenance</label>
                                <input type="date" id="editNextMaintenance" name="next_maintenance"
                                       value="${equipment.next_maintenance || ''}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="editLocation">Location</label>
                            <input type="text" id="editLocation" name="location" 
                                   value="${equipment.location || ''}" placeholder="e.g., Lab A">
                        </div>
                        <div class="form-group full-width">
                            <label for="editNotes">Notes</label>
                            <textarea id="editNotes" name="notes" rows="3">${equipment.notes || ''}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editEquipmentModal')">
                            Cancel
                        </button>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Equipment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // Add modal to body
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHTML;
    document.body.appendChild(modalContainer);
    
    // Show the modal
    document.getElementById('editEquipmentModal').style.display = 'block';
    
// EDIT EQUIPMENT FORM SUBMISSION HANDLER
document.addEventListener('submit', function(e) {
    if (e.target.id === 'editEquipmentForm') {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;
        
        // Send update request
        fetch('update_equipment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('success', '✅ Equipment Updated', result.message);
                closeModal('editEquipmentModal');
                
                // Refresh the page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification('error', '❌ Update Failed', result.message || 'Unknown error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error updating equipment:', error);
            showNotification('error', '⚠️ Network Error', 'Failed to connect to server');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }


});
   
}

// Improved refreshEquipment function

// EDIT BUTTON CLICK HANDLER
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-edit')) {
        const btn = e.target.closest('.btn-edit');
        const testId = btn.getAttribute('data-testid');
        const status = btn.getAttribute('data-status');
        const result = btn.getAttribute('data-result') || btn.closest('tr').querySelector('td:nth-child(5) .status-badge')?.textContent.trim();
        
        console.log('Edit clicked:', { testId, status, result });
        
        // Get test details from the row
        const row = btn.closest('tr');
        const cells = row.querySelectorAll('td');
        
        // Populate the edit form
        document.getElementById('editTestId').value = testId;
        document.getElementById('editStatus').value = status;
        document.getElementById('editResult').value = result || 'Pass';
        
        // Try to get remarks if available
        if (cells.length > 8) {
            // Assuming remarks might be in a hidden field or attribute
            const remarks = btn.getAttribute('data-remarks') || '';
            document.getElementById('editRemarks').value = remarks;
        }
        
        // Show the edit modal
        document.getElementById('editModal').style.display = 'block';
    }
});


// When the user clicks on the button, scroll to the top
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
    </script>
<!-- Scroll to Top Button -->
<button onclick="scrollToTop()" id="scrollTopBtn" title="Go to top">
    <i class="fas fa-arrow-up"></i>
</button>
</body>
</html>