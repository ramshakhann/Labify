<?php 
include "../auth.php";
  require_once 'includes/db_connect.php';

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

// Database connection
$host = 'localhost';
$dbname = 'labify';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'] ?? null;
$tested_by_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Unknown';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'getReportData':
            echo json_encode(getReportData($pdo));
            exit;
            
        case 'exportReport':
            echo json_encode(exportReport($pdo, $_POST));
            exit;
            
        case 'deleteTest':
            echo json_encode(deleteTest($pdo, $_POST['test_id']));
            exit;
            
        case 'updateTestStatus':
            echo json_encode(updateTestStatus($pdo, $_POST));
            exit;
    }
}

// Handle GET requests for data loading
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getReportData') {
    header('Content-Type: application/json');
    echo json_encode(getReportData($pdo));
    exit;
}

function getReportData($pdo) {
    try {
        $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['endDate'] ?? date('Y-m-d');
        $productId = $_GET['productId'] ?? '';
        $testTypeId = $_GET['testTypeId'] ?? '';
        $result = $_GET['result'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Build SQL with prepared statements for security
        $sql = "
            SELECT tr.*, p.product_type, tt.test_code, tt.test_name, u.full_name as tester_name
            FROM test_results tr 
            JOIN products p ON tr.product_id = p.product_id 
            JOIN test_types tt ON tr.test_type_id = tt.id 
            LEFT JOIN users u ON tr.tester_id = u.id 
            WHERE tr.test_date BETWEEN :startDate AND :endDate
        ";
        
        $params = [
            ':startDate' => $startDate,
            ':endDate' => $endDate
        ];
        
        // Add conditions dynamically
        $conditions = [];
        
        if (!empty($productId)) {
            $conditions[] = "tr.product_id = :productId";
            $params[':productId'] = $productId;
        }
        
        if (!empty($testTypeId)) {
            $conditions[] = "tr.test_type_id = :testTypeId";
            $params[':testTypeId'] = $testTypeId;
        }
        
        if (!empty($result)) {
            $conditions[] = "tr.result = :result";
            $params[':result'] = $result;
        }
        
        if (!empty($status)) {
            $conditions[] = "tr.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($search)) {
            $conditions[] = "(tr.test_id LIKE :search OR tr.product_id LIKE :search OR p.product_type LIKE :search OR u.full_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY tr.test_date DESC LIMIT 1000";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $data,
            'total' => count($data)
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error fetching report data: ' . $e->getMessage()
        ];
    }
}

function exportReport($pdo, $params) {
    try {
        $format = $params['format'] ?? 'excel';
        $data = getReportData($pdo);
        
        if (!$data['success']) {
            return $data;
        }
        
        $exportData = $data['data'];
        $filename = 'labify_report_' . date('Y-m-d_H-i-s') . '.' . ($format === 'excel' ? 'xls' : 'csv');
        
        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo "Test ID\tProduct ID\tProduct Type\tTest Type\tTest Date\tResult\tStatus\tTested By\tRemarks\n";
            
            foreach ($exportData as $row) {
                echo implode("\t", [
                    $row['test_id'] ?? '',
                    $row['product_id'] ?? '',
                    $row['product_type'] ?? '',
                    ($row['test_code'] ?? '') . ' - ' . ($row['test_name'] ?? ''),
                    $row['test_date'] ?? '',
                    $row['result'] ?? '',
                    $row['status'] ?? '',
                    $row['tester_name'] ?? ($row['tested_by'] ?? 'N/A'),
                    $row['remarks'] ?? ''
                ]) . "\n";
            }
            exit;
            
        } else { // CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Test ID', 'Product ID', 'Product Type', 'Test Type', 'Test Date', 'Result', 'Status', 'Tested By', 'Remarks']);
            
            foreach ($exportData as $row) {
                fputcsv($output, [
                    $row['test_id'] ?? '',
                    $row['product_id'] ?? '',
                    $row['product_type'] ?? '',
                    ($row['test_code'] ?? '') . ' - ' . ($row['test_name'] ?? ''),
                    $row['test_date'] ?? '',
                    $row['result'] ?? '',
                    $row['status'] ?? '',
                    $row['tester_name'] ?? ($row['tested_by'] ?? 'N/A'),
                    $row['remarks'] ?? ''
                ]);
            }
            
            fclose($output);
            exit;
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Export error: ' . $e->getMessage()
        ];
    }
}

function deleteTest($pdo, $testId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM test_results WHERE test_id = ?");
        $stmt->execute([$testId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Test deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Test not found'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateTestStatus($pdo, $params) {
    try {
        $stmt = $pdo->prepare("UPDATE test_results SET status = ?, remarks = ? WHERE test_id = ?");
        $stmt->execute([$params['status'], $params['remarks'], $params['test_id']]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Test not found'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labify | Advanced Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="./assets/log_test_style.css">
    <link rel="stylesheet" href="./assets/product_style.css">
    <link rel="stylesheet" href="./assets/detailed_report.css">

    <style>
        /* Advanced Reports Specific Styles */
        .advanced-filters {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 0.875rem;
        }
        
        .report-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 0.5rem;
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }
        
        .report-tab {
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 10px;
            white-space: nowrap;
            font-weight: 500;
        }
        
        .report-tab.active {
            background: linear-gradient(45deg, #4a6bff, #3a5be8);
            color: white;
            box-shadow: 0 4px 15px rgba(74, 107, 255, 0.3);
        }
        
        .report-tab:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #64ffda;
            margin-bottom: 0.5rem;
            text-shadow: 0 0 20px rgba(100, 255, 218, 0.5);
        }
        
        .stat-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            margin-top: 2rem;
        }
        
        .chart-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
        }
        
        .chart-controls {
            display: flex;
            gap: 0.5rem;
        }
        
        .chart-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            color: white;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .chart-control:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .chart-container {
            height: 350px;
            position: relative;
        }
        
        .data-table {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table-wrapper {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: rgba(74, 107, 255, 0.1);
            color: #64ffda;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
        }
        
        .data-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .action-btn {
            background: linear-gradient(45deg, #4a6bff, #3a5be8);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(74, 107, 255, 0.3);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 107, 255, 0.4);
        }
        
        .action-btn.secondary {
            background: linear-gradient(45deg, #6c757d, #5a6268);
        }
        
        .action-btn.danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }
        
        .quick-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .quick-filter {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }
        
        .quick-filter:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .quick-filter.active {
            background: linear-gradient(45deg, #4a6bff, #3a5be8);
            color: white;
            border-color: #4a6bff;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #64ffda;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(45deg, #4a6bff, #3a5be8);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .cpri-timeline {
            position: relative;
            padding-left: 2rem;
            margin-top: 2rem;
        }
        
        .cpri-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background: linear-gradient(180deg, #64ffda, #4a6bff);
        }
        
        .cpri-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .cpri-item::before {
            content: '';
            position: absolute;
            left: -2px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #64ffda;
            border: 2px solid #0f172a;
        }
        
        .cpri-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .cpri-content:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
            border-color: rgba(100, 255, 218, 0.3);
        }
        
        /* CPRI Status Colors */
        .status-submitted { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .status-pending { background: rgba(156, 163, 175, 0.2); color: #9CA3AF; }
        .status-in-progress { background: rgba(59, 130, 246, 0.2); color: #3B82F6; }
        .status-remake { background: rgba(251, 146, 60, 0.2); color: #FB923C; }
        .status-review { background: rgba(147, 51, 234, 0.2); color: #9333EA; }
        .status-awaiting-docs { background: rgba(239, 68, 68, 0.2); color: #EF4444; }
        
        /* Tab Content Management */
        .report-content {
            display: none;
        }
        
        .report-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .filter-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .action-btn {
                flex: 1;
                min-width: 120px;
            }
        }


        /* Responsive Input Fields Fix */
.advanced-filters {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    overflow: hidden; /* Prevent overflow */
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem; /* Increased gap for better spacing */
    margin-bottom: 1rem;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0; /* Important for flex children */
}

.filter-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-input {
    width: 100%;
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: white;
    font-size: 0.95rem;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.3s ease;
    box-sizing: border-box; /* Include padding in width */
    max-width: 100%; /* Prevent overflow */
    -webkit-appearance: none; /* Remove default styling on iOS */
}

.search-input:focus {
    outline: none;
    border-color: #64ffda;
    box-shadow: 0 0 0 3px rgba(100, 255, 218, 0.2);
    background: rgba(255, 255, 255, 0.12);
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* Date inputs specifically */


/* Select dropdowns */
.search-input[type="select"],
.search-input select {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M2 4.5L6 8.5L10 4.5' stroke='%2364ffda' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 12px;
    padding-right: 2.5rem; /* Space for dropdown arrow */
    appearance: none; /* Remove default arrow */
    -webkit-appearance: none;
    -moz-appearance: none;
}

/* Date range container fix */
.date-range-container {
    display: flex;
    gap: 0.75rem;
    width: 100%;
}

.date-range-container .search-input {
    flex: 1;
    min-width: 120px; /* Minimum width for date inputs */
}

/* Quick filters responsive */
.quick-filters {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    padding: 0.5rem 0;
}

.quick-filter {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 0.6rem 1.2rem;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    white-space: nowrap;
    flex-shrink: 0; /* Prevent shrinking */
}

.quick-filter:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateY(-1px);
}

.quick-filter.active {
    background: linear-gradient(45deg, #4a6bff, #3a5be8);
    color: white;
    border-color: #4a6bff;
    box-shadow: 0 4px 12px rgba(74, 107, 255, 0.3);
}

/* Mobile-specific fixes */
@media (max-width: 768px) {
    .advanced-filters {
        padding: 1.2rem;
        border-radius: 12px;
        margin-left: -0.5rem;
        margin-right: -0.5rem;
        border-left: none;
        border-right: none;
        border-radius: 0;
        background: rgba(255, 255, 255, 0.03);
    }
    
    .filter-row {
        grid-template-columns: 1fr;
        gap: 1.2rem;
    }
    
    .filter-item {
        width: 100%;
        max-width: 100%;
    }
    
    .search-input {
        padding: 0.8rem 0.9rem;
        font-size: 1rem; /* Larger font for mobile readability */
        min-height: 48px; /* Better touch target */
    }
    
    .date-range-container {
        flex-direction: column;
        gap: 0.8rem;
    }
    
    .date-range-container .search-input {
        width: 100%;
    }
    
    .quick-filters {
        gap: 0.4rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        scrollbar-width: none; /* Hide scrollbar */
    }
    
    .quick-filters::-webkit-scrollbar {
        display: none; /* Hide scrollbar */
    }
    
    .quick-filter {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        flex: 0 0 auto; /* Don't grow, don't shrink */
    }
    
    /* Prevent zoom on iOS when focusing inputs */
    @media screen and (max-width: 768px) {
        .search-input {
            font-size: 16px !important; /* Prevents iOS zoom */
        }
    }
}

@media (max-width: 480px) {
    .advanced-filters {
        padding: 1rem;
    }
    
    .filter-row {
        gap: 1rem;
    }
    
    .search-input {
        padding: 0.75rem 0.8rem;
        min-height: 46px;
        border-radius: 8px;
    }
    
    .quick-filter {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }
    
    .filter-label {
        font-size: 0.85rem;
    }
}

/* Fix for iOS Safari */
@supports (-webkit-touch-callout: none) {
    .search-input {
        font-size: 16px; /* Prevents zoom on focus in iOS */
    }
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
    .search-input {
        background: rgba(0, 0, 0, 0.3);
        border-color: rgba(255, 255, 255, 0.15);
    }
    
    .search-input:focus {
        background: rgba(0, 0, 0, 0.4);
    }
}

/* Loading state for inputs */
.search-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: rgba(255, 255, 255, 0.05);
}

/* Validation states */
.search-input:invalid {
    border-color: rgba(244, 67, 54, 0.5);
}

.search-input:valid {
    border-color: rgba(76, 168, 175, 0.5);
}

/* Autofill styles */
.search-input:-webkit-autofill,
.search-input:-webkit-autofill:hover,
.search-input:-webkit-autofill:focus {
    -webkit-text-fill-color: white;
    -webkit-box-shadow: 0 0 0px 1000px rgba(74, 107, 255, 0.1) inset;
    transition: background-color 5000s ease-in-out 0s;
    border: 1px solid rgba(100, 255, 218, 0.3);
}

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
background: linear-gradient(135deg, rgba(14, 210, 231, 0.975), rgba(3, 61, 118, 0.4));
    border-radius: 5px;
    border: 2px solid rgba(0, 0, 0, 0.1);
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, rgba(100, 221, 255, 0.6), rgba(74, 222, 255, 0.6));
}

/* For Firefox */
* {
    scrollbar-width: thin; 
    scrollbar-color: #16f7f0ff #1a1a2e;
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
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <nav class="desktop-nav">
                    
                    <ul>
                        <li><a href="log_test.php" class="nav-link ">Testing</a></li>
                        <li><a href="products.php" class="nav-link ">Products</a></li>
                        <li><a href="searching.php" class="nav-link">Search</a></li>
                        <li><a href="reports.php" class="nav-link active ">Reports</a></li>
                    </ul>
                </nav>
                
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
        <li><a href="log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> Testing</a></li>
        <li><a href="products.php" data-tab="products"><i class="fas fa-cubes"></i>Products</a></li>
        <li><a href="searching.php" data-tab="search"><i class="fas fa-search"></i> Search</a></li>
        <li><a href="reports.php" class="active" data-tab="reports"><i class="fas fa-chart-bar"></i>Reports</a></li>
        
    </ul>
        
        <div class="mobile-nav-actions">
            <button class="btn btn-primary" onclick="window.location.href='http://localhost/LABIFY/logout.php'">
                LOG OUT
            </button>
        </div>
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
            <h2>Advanced Laboratory Reports</h2>
            <p>Generate comprehensive reports with advanced filtering, analytics, and export capabilities for your laboratory testing data.</p>
            <button class="btn btn-primary" id="exploreFeatures">Explore Features</button>
        </div>
    </section>

    <!-- Main Reports Section -->
    <section class="dashboard">
        <div class="container">
            <!-- Report Tabs -->
            <div class="report-tabs">
                <div class="report-tab active" data-tab="overview">Overview</div>
                <div class="report-tab" data-tab="detailed">Detailed Report</div>
                <div class="report-tab" data-tab="analytics">Analytics</div>
                <div class="report-tab" data-tab="cpri">CPRI Tracking</div>
            </div>

            <!-- Advanced Filters -->
            <div class="advanced-filters">
                <div class="filter-row">
                   <!-- Update your date range section -->
<div class="filter-item">
    <label class="filter-label">Date Range</label>
    <div class="date-range-container">
        <input type="date" id="startDate" class="search-input" 
               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
               placeholder="Start Date"
               aria-label="Start Date">
        <input type="date" id="endDate" class="search-input" 
               value="<?php echo date('Y-m-d'); ?>" 
               placeholder="End Date"
               aria-label="End Date">
    </div>
</div>
                    <div class="filter-item">
                        <label class="filter-label">Product</label>
                        <select id="productFilter" class="search-input">
                            <option value="">All Products</option>
                            <?php
                            $products = $pdo->query("SELECT DISTINCT product_id, product_type FROM products ORDER BY product_type")->fetchAll();
                            foreach ($products as $product) {
                                echo "<option value='{$product['product_id']}'>{$product['product_id']} - {$product['product_type']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Test Type</label>
                        <select id="testTypeFilter" class="search-input">
                            <option value="">All Types</option>
                            <?php
                            $test_types = $pdo->query("SELECT id, test_code, test_name FROM test_types ORDER BY test_code")->fetchAll();
                            foreach ($test_types as $type) {
                                echo "<option value='{$type['id']}'>{$type['test_code']} - {$type['test_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Result</label>
                        <select id="resultFilter" class="search-input">
                            <option value="">All Results</option>
                            <option value="Pass">Pass</option>
                            <option value="Fail">Fail</option>
                            <option value="Inconclusive">Inconclusive</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-item">
                        <label class="filter-label">Status</label>
                        <select id="statusFilter" class="search-input">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Sent to CPRI">Sent to CPRI</option>
                            <option value="Sent for Remake">Sent for Remake</option>
                            <option value="In Review">In Review</option>
                            <option value="Awaiting Docs">Awaiting Docs</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Quick Search</label>
                        <input type="text" id="quickSearch" class="search-input" placeholder="Search by Test ID, Product, or Tester...">
                    </div>
                </div>
            </div>

            <!-- Quick Filters -->
            <div class="quick-filters">
                <button class="quick-filter" onclick="setQuickFilter('today')">Today</button>
                <button class="quick-filter" onclick="setQuickFilter('week')">This Week</button>
                <button class="quick-filter" onclick="setQuickFilter('month')">This Month</button>
                <button class="quick-filter" onclick="setQuickFilter('quarter')">This Quarter</button>
                <button class="quick-filter" onclick="setQuickFilter('failed')">Failed Tests</button>
                <button class="quick-filter" onclick="setQuickFilter('cpri')">CPRI Pending</button>
            </div>

            <!-- Overview Tab Content -->
            <div id="overview" class="report-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="totalTests">0</div>
                        <div class="stat-label">Total Tests</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="passedTests">0</div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="failedTests">0</div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="passRate">0%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="avgTestTime">0h</div>
                        <div class="stat-label">Avg Test Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="cpriPendingCount">0</div>
                        <div class="stat-label">CPRI Pending</div>
                    </div>
                </div>

                <div class="chart-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Test Results Trend</h3>
                            <div class="chart-controls">
                                <button class="chart-control" onclick="changeChartPeriod('7d')">7D</button>
                                <button class="chart-control" onclick="changeChartPeriod('30d')">30D</button>
                                <button class="chart-control" onclick="changeChartPeriod('90d')">90D</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Product Distribution</h3>
                            <div class="chart-controls">
                                <button class="chart-control" onclick="toggleChartType()">Switch View</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Report Tab Content -->
<div id="detailed" class="report-content">
    <div class="detailed-report-container">
        <div class="detailed-table-wrapper">
            <table class="detailed-results-table" id="resultsTable">
                <thead>
                    <tr>
                        <th class="col-test-id">Test ID</th>
                        <th class="col-product">Product</th>
                        <th class="col-test-type">Test Type</th>
                        <th class="col-date">Date</th>
                        <th class="col-result">Result</th>
                        <th class="col-status">Status</th>
                        <th class="col-tester">Tested By</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Data will be populated here -->
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <div class="table-info">
                <i class="fas fa-database"></i>
                <span>Showing <span id="tableCount">0</span> of <span id="tableTotal">0</span> test results</span>
            </div>
            <div class="table-actions">
                <button class="action-btn" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="action-btn" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button class="action-btn secondary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

            <!-- Analytics Tab Content -->
            <div id="analytics" class="report-content">
                <div class="chart-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Test Performance by Type</h3>
                            <div class="chart-controls">
                                <button class="chart-control" onclick="updateAnalyticsChart('type')">By Type</button>
                                <button class="chart-control" onclick="updateAnalyticsChart('tester')">By Tester</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Monthly Test Volume</h3>
                            <div class="chart-controls">
                                <button class="chart-control" onclick="updateVolumeChart('bar')">Bar</button>
                                <button class="chart-control" onclick="updateVolumeChart('line')">Line</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="volumeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CPRI Tracking Tab Content -->
            <div id="cpri" class="report-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="criSubmitted">0</div>
                        <div class="stat-label">Submitted to CPRI</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="criPending">0</div>
                        <div class="stat-label">CPRI Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="criInProgress">0</div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="criRemake">0</div>
                        <div class="stat-label">Sent for Remake</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="criInReview">0</div>
                        <div class="stat-label">In Review</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="criAwaitingDocs">0</div>
                        <div class="stat-label">Awaiting Docs</div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">CPRI Status Breakdown</h3>
                        <button class="chart-control" onclick="refreshCPRIData()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="criChart"></canvas>
                    </div>
                </div>

                <div class="cpri-timeline" id="criTimeline">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include('./shared1/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Global variables
        let reportData = [];
        let currentTab = 'overview';
        let charts = {};
        let currentChartPeriod = '30d';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabSystem();
            initializeMobileMenu();
            initializeParticles();
            setupEventListeners();
            loadReportData();
            
            // Initialize charts with empty data
            initializeCharts();
        });

        // Load report data via AJAX
        function loadReportData() {
            showLoading();
            
            const startDate = document.getElementById('startDate').value || new Date().toISOString().split('T')[0];
            const endDate = document.getElementById('endDate').value || new Date().toISOString().split('T')[0];
            const productId = document.getElementById('productFilter').value;
            const testTypeId = document.getElementById('testTypeFilter').value;
            const result = document.getElementById('resultFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('quickSearch').value;
            
            // Build query parameters
            const params = new URLSearchParams({
                action: 'getReportData',
                startDate: startDate,
                endDate: endDate
            });
            
            if (productId) params.append('productId', productId);
            if (testTypeId) params.append('testTypeId', testTypeId);
            if (result) params.append('result', result);
            if (status) params.append('status', status);
            if (search) params.append('search', search);
            
            // Load data from PHP
            fetch('reports.php?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data && !data.success) {
                    if (Array.isArray(data)) {
                        reportData = data;
                    } else {
                        throw new Error('Invalid data format');
                    }
                } else if (data && data.success) {
                    reportData = data.data || [];
                } else {
                    reportData = [];
                }
                
                updateUI();
                hideLoading();
            })
            .catch(error => {
                console.error('Fetch error:', error);
                hideLoading();
                showNotification('Error loading report data. Please try again.', 'error');
                reportData = [];
                updateUI();
            });
        }

        // Update UI with loaded data
        function updateUI() {
            updateDashboardStats();
            updateDetailedTable();
            updateCharts();
            updateCPRITimeline();
        }

        // Update dashboard statistics
        function updateDashboardStats() {
            const totalTests = reportData.length;
            const passedTests = reportData.filter(t => t.result === 'Pass').length;
            const failedTests = reportData.filter(t => t.result === 'Fail').length;
            const passRate = totalTests > 0 ? Math.round((passedTests / totalTests) * 100) : 0;
            
            // CPRI related tests
            const criSubmitted = reportData.filter(t => t.status === 'Sent to CPRI').length;
            const criPending = reportData.filter(t => t.status === 'Pending').length;
            const criInProgress = reportData.filter(t => t.status === 'In Progress').length;
            const criRemake = reportData.filter(t => t.status === 'Sent for Remake').length;
            const criInReview = reportData.filter(t => t.status === 'In Review').length;
            const criAwaitingDocs = reportData.filter(t => t.status === 'Awaiting Docs').length;
            
            // Calculate average test duration
            let totalDuration = 0;
            let durationCount = 0;
            
            reportData.forEach(test => {
                if (test.created_at && test.test_date) {
                    const start = new Date(test.created_at);
                    const end = new Date(test.test_date);
                    const duration = (end - start) / (1000 * 60 * 60);
                    if (duration > 0) {
                        totalDuration += duration;
                        durationCount++;
                    }
                }
            });
            
            const avgDuration = durationCount > 0 ? totalDuration / durationCount : 0;
            const hours = Math.floor(avgDuration);
            const minutes = Math.round((avgDuration - hours) * 60);
            const avgTimeText = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
            
            // Update stats cards
            document.getElementById('totalTests').textContent = totalTests;
            document.getElementById('passedTests').textContent = passedTests;
            document.getElementById('failedTests').textContent = failedTests;
            document.getElementById('passRate').textContent = passRate + '%';
            document.getElementById('cpriPendingCount').textContent = criPending;
            document.getElementById('avgTestTime').textContent = avgTimeText;
            
            // Update CPRI stats
            document.getElementById('criSubmitted').textContent = criSubmitted;
            document.getElementById('criPending').textContent = criPending;
            document.getElementById('criInProgress').textContent = criInProgress;
            document.getElementById('criRemake').textContent = criRemake;
            document.getElementById('criInReview').textContent = criInReview;
            document.getElementById('criAwaitingDocs').textContent = criAwaitingDocs;
        }

        // Update detailed table
function updateDetailedTable() {
    const tableBody = document.getElementById('tableBody');
    tableBody.innerHTML = '';
    
    if (reportData.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="table-empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Test Results Found</h3>
                        <p>Try adjusting your filters or search criteria</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    reportData.forEach((test, index) => {
        const row = document.createElement('tr');
        row.style.animationDelay = `${index * 0.05}s`;
        
        // Get result class
        let resultClass = 'badge-inconclusive';
        if (test.result === 'Pass') resultClass = 'badge-pass';
        if (test.result === 'Fail') resultClass = 'badge-fail';
        
        // Get status class
        let statusClass = 'status-pending';
        const status = test.status?.toLowerCase() || '';
        if (status.includes('progress')) statusClass = 'status-in-progress';
        if (status.includes('complete')) statusClass = 'status-completed';
        if (status.includes('sent to cpri')) statusClass = 'status-sent-to-cpri';
        if (status.includes('sent for remake')) statusClass = 'status-sent-for-remake';
        if (status.includes('in review')) statusClass = 'status-in-review';
        if (status.includes('awaiting docs')) statusClass = 'status-awaiting-docs';
        
        row.innerHTML = `
            <td class="col-test-id"><strong>${test.test_id || 'N/A'}</strong></td>
            <td class="col-product">${test.product_id || 'N/A'} - ${test.product_type || 'N/A'}</td>
            <td class="col-test-type">${test.test_code || 'N/A'} - ${test.test_name || 'N/A'}</td>
            <td class="col-date">${formatDate(test.test_date)}</td>
            <td class="col-result"><span class="badge ${resultClass}">${test.result || 'N/A'}</span></td>
            <td class="col-status"><span class="status-badge ${statusClass}">${test.status || 'N/A'}</span></td>
            <td class="col-tester">${test.tester_name || test.tested_by || 'N/A'}</td>
          
        `;
        tableBody.appendChild(row);
    });
    
    // Update footer info
    document.getElementById('tableCount').textContent = reportData.length;
    document.getElementById('tableTotal').textContent = reportData.length;
}

        // Initialize all charts
        function initializeCharts() {
            // Trend Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                charts.trend = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Passed Tests',
                                data: [],
                                borderColor: 'rgba(76, 175, 80, 0.8)',
                                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Failed Tests',
                                data: [],
                                borderColor: 'rgba(244, 67, 54, 0.8)',
                                backgroundColor: 'rgba(244, 67, 54, 0.2)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: getChartOptions()
                });
            }

            // Distribution Chart
            const distCtx = document.getElementById('distributionChart');
            if (distCtx) {
                charts.distribution = new Chart(distCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                'rgba(99, 102, 241, 0.8)',
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(251, 146, 60, 0.8)',
                                'rgba(147, 51, 234, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: 'rgba(255, 255, 255, 0.8)' }
                            }
                        }
                    }
                });
            }

            // Performance Chart
            const perfCtx = document.getElementById('performanceChart');
            if (perfCtx) {
                charts.performance = new Chart(perfCtx, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Number of Tests',
                            data: [],
                            backgroundColor: 'rgba(59, 130, 246, 0.8)'
                        }]
                    },
                    options: getChartOptions()
                });
            }

            // Volume Chart
            const volumeCtx = document.getElementById('volumeChart');
            if (volumeCtx) {
                charts.volume = new Chart(volumeCtx, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Tests Per Month',
                            data: [],
                            backgroundColor: 'rgba(147, 51, 234, 0.8)'
                        }]
                    },
                    options: getChartOptions()
                });
            }

            // CPRI Chart
            const criCtx = document.getElementById('criChart');
            if (criCtx) {
                charts.cri = new Chart(criCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Submitted to CPRI', 'Pending', 'In Progress', 'Sent for Remake', 'In Review', 'Awaiting Docs'],
                        datasets: [{
                            data: [0, 0, 0, 0, 0, 0],
                            backgroundColor: [
                                'rgba(255, 193, 7, 0.8)',
                                'rgba(156, 163, 175, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(251, 146, 60, 0.8)',
                                'rgba(147, 51, 234, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: 'rgba(255, 255, 255, 0.8)' }
                            }
                        }
                    }
                });
            }
        }

        function getChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: 'rgba(255, 255, 255, 0.8)' }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: 'rgba(255, 255, 255, 0.8)' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        ticks: { color: 'rgba(255, 255, 255, 0.8)' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        beginAtZero: true
                    }
                }
            };
        }

        // Update charts with actual data
        function updateCharts() {
            if (reportData.length === 0) {
                Object.values(charts).forEach(chart => {
                    if (chart) {
                        chart.data.labels = [];
                        chart.data.datasets.forEach(dataset => {
                            dataset.data = [];
                        });
                        chart.update();
                    }
                });
                return;
            }

            // Prepare trend data (daily)
            const dailyData = {};
            reportData.forEach(test => {
                const date = test.test_date ? test.test_date.split(' ')[0] : 'Unknown';
                if (!dailyData[date]) {
                    dailyData[date] = { pass: 0, fail: 0 };
                }
                if (test.result === 'Pass') dailyData[date].pass++;
                if (test.result === 'Fail') dailyData[date].fail++;
            });
            
            const sortedDates = Object.keys(dailyData).sort();
            
            // Update trend chart
            if (charts.trend) {
                charts.trend.data.labels = sortedDates;
                charts.trend.data.datasets[0].data = sortedDates.map(date => dailyData[date].pass || 0);
                charts.trend.data.datasets[1].data = sortedDates.map(date => dailyData[date].fail || 0);
                charts.trend.update();
            }

            // Prepare product distribution data
            const productData = {};
            reportData.forEach(test => {
                const product = test.product_type || test.product_id || 'Unknown';
                productData[product] = (productData[product] || 0) + 1;
            });
            
            // Update distribution chart
            if (charts.distribution) {
                charts.distribution.data.labels = Object.keys(productData);
                charts.distribution.data.datasets[0].data = Object.values(productData);
                charts.distribution.update();
            }

            // Prepare test type performance data
            const testTypeData = {};
            reportData.forEach(test => {
                const testType = test.test_name || test.test_code || 'Unknown';
                testTypeData[testType] = (testTypeData[testType] || 0) + 1;
            });
            
            // Update performance chart
            if (charts.performance) {
                const sortedTypes = Object.entries(testTypeData)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 10);
                
                charts.performance.data.labels = sortedTypes.map(t => t[0]);
                charts.performance.data.datasets[0].data = sortedTypes.map(t => t[1]);
                charts.performance.update();
            }

            // Prepare monthly volume data
            const monthlyData = {};
            reportData.forEach(test => {
                if (test.test_date) {
                    const month = test.test_date.substring(0, 7);
                    monthlyData[month] = (monthlyData[month] || 0) + 1;
                }
            });
            
            // Update volume chart
            if (charts.volume) {
                const sortedMonths = Object.keys(monthlyData).sort();
                charts.volume.data.labels = sortedMonths.map(m => {
                    const date = new Date(m + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                });
                charts.volume.data.datasets[0].data = sortedMonths.map(m => monthlyData[m]);
                charts.volume.update();
            }

            // Update CPRI chart
            if (charts.cri) {
                const criSubmitted = reportData.filter(t => t.status === 'Sent to CPRI').length;
                const criPending = reportData.filter(t => t.status === 'Pending').length;
                const criInProgress = reportData.filter(t => t.status === 'In Progress').length;
                const criRemake = reportData.filter(t => t.status === 'Sent for Remake').length;
                const criInReview = reportData.filter(t => t.status === 'In Review').length;
                const criAwaitingDocs = reportData.filter(t => t.status === 'Awaiting Docs').length;
                
                charts.cri.data.datasets[0].data = [
                    Math.max(0, criSubmitted),
                    Math.max(0, criPending),
                    Math.max(0, criInProgress),
                    Math.max(0, criRemake),
                    Math.max(0, criInReview),
                    Math.max(0, criAwaitingDocs)
                ];
                charts.cri.update();
            }
        }

        // Update CPRI Timeline
        function updateCPRITimeline() {
            const timeline = document.getElementById('criTimeline');
            if (!timeline) return;
            
            // Filter CPRI related tests
            const cpriTests = reportData.filter(t => 
                t.status && (
                    t.status.includes('CPRI') || 
                    t.status.includes('Pending') ||
                    t.status.includes('In Progress') ||
                    t.status.includes('Sent for Remake') ||
                    t.status.includes('In Review') ||
                    t.status.includes('Awaiting Docs')
                )
            ).slice(0, 10);
            
            if (cpriTests.length === 0) {
                timeline.innerHTML = `
                    <div class="cpri-item">
                        <div class="cpri-content">
                            <p style="text-align: center; color: rgba(255, 255, 255, 0.5);">
                                <i class="fas fa-info-circle"></i> No CPRI or pending tests found
                            </p>
                        </div>
                    </div>
                `;
                return;
            }
            
            timeline.innerHTML = '';
            
            cpriTests.forEach(test => {
                let statusColor = '#64ffda';
                if (test.status === 'Pending') statusColor = '#9CA3AF';
                else if (test.status === 'In Progress') statusColor = '#3B82F6';
                else if (test.status === 'Sent for Remake') statusColor = '#FB923C';
                else if (test.status === 'In Review') statusColor = '#9333EA';
                else if (test.status === 'Awaiting Docs') statusColor = '#EF4444';
                else if (test.status === 'Sent to CPRI') statusColor = '#F59E0B';
                
                const item = document.createElement('div');
                item.className = 'cpri-item';
                item.innerHTML = `
                    <div class="cpri-content">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <strong style="color: #64ffda;">${test.test_id || 'N/A'}</strong><br>
                                <small style="color: #aaa;">${test.product_id || 'N/A'} - ${test.product_type || 'N/A'}</small><br>
                                <small>Test: ${test.test_name || 'N/A'}</small>
                            </div>
                            <div style="text-align: right;">
                                <span class="priority-badge" style="background: ${statusColor}20; color: ${statusColor};">
                                    ${test.status || 'N/A'}
                                </span>
                            </div>
                        </div>
                        <div style="margin-top: 10px; font-size: 0.85rem; color: #888;">
                            <div style="display: flex; justify-content: space-between;">
                                <span>Submitted: ${formatDate(test.created_at)}</span>
                                <span>Last Updated: ${formatDate(test.test_date)}</span>
                            </div>
                            ${test.remarks ? `<div style="margin-top: 5px; color: #aaa;"><i>${test.remarks}</i></div>` : ''}
                        </div>
                    </div>
                `;
                timeline.appendChild(item);
            });
        }

        // Setup event listeners
        function setupEventListeners() {
            // Filter change listeners
            ['startDate', 'endDate', 'productFilter', 'testTypeFilter', 'resultFilter', 'statusFilter'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('change', loadReportData);
                }
            });
            
            // Quick search with debounce
            const quickSearch = document.getElementById('quickSearch');
            if (quickSearch) {
                quickSearch.addEventListener('keyup', debounce(function() {
                    const searchTerm = quickSearch.value.toLowerCase();
                    if (searchTerm) {
                        const filteredData = reportData.filter(test => 
                            (test.test_id && test.test_id.toLowerCase().includes(searchTerm)) ||
                            (test.product_id && test.product_id.toLowerCase().includes(searchTerm)) ||
                            (test.product_type && test.product_type.toLowerCase().includes(searchTerm)) ||
                            (test.tester_name && test.tester_name.toLowerCase().includes(searchTerm)) ||
                            (test.tested_by && test.tested_by.toLowerCase().includes(searchTerm))
                        );
                        
                        const tableBody = document.getElementById('tableBody');
                        tableBody.innerHTML = '';
                        
                        if (filteredData.length === 0) {
                            tableBody.innerHTML = `
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.5);">
                                        <i class="fas fa-search"></i> No results found for "${searchTerm}"
                                    </td>
                                </tr>
                            `;
                        } else {
                            filteredData.forEach(test => {
                                let duration = 'N/A';
                                if (test.created_at && test.test_date) {
                                    const start = new Date(test.created_at);
                                    const end = new Date(test.test_date);
                                    const diff = (end - start) / (1000 * 60 * 60);
                                    const hours = Math.floor(diff);
                                    const minutes = Math.round((diff - hours) * 60);
                                    duration = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
                                }
                                
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td><strong>${test.test_id || 'N/A'}</strong></td>
                                    <td>${test.product_id || 'N/A'} - ${test.product_type || 'N/A'}</td>
                                    <td>${test.test_code || 'N/A'} - ${test.test_name || 'N/A'}</td>
                                    <td>${formatDate(test.test_date)}</td>
                                    <td><span class="priority-badge ${getResultClass(test.result)}">${test.result || 'N/A'}</span></td>
                                    <td><span class="priority-badge ${getStatusClass(test.status)}">${test.status || 'N/A'}</span></td>
                                    <td>${test.tester_name || test.tested_by || 'N/A'}</td>
                                  
                                `;
                                tableBody.appendChild(row);
                            });
                        }
                    } else {
                        loadReportData();
                    }
                }, 500));
            }
            
            // Tab switching
            document.querySelectorAll('.report-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    switchTab(this.getAttribute('data-tab'));
                });
            });
            
            // Explore features button
            const exploreBtn = document.getElementById('exploreFeatures');
            if (exploreBtn) {
                exploreBtn.addEventListener('click', function() {
                    document.querySelector('.dashboard').scrollIntoView({ behavior: 'smooth' });
                });
            }
        }

        // Tab switching
        function switchTab(tabName) {
            currentTab = tabName;
            
            // Update tab styles
            document.querySelectorAll('.report-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            
            // Update content visibility
            document.querySelectorAll('.report-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            
            // Load specific data for tab
            if (tabName === 'cpri') {
                updateCPRITimeline();
            }
        }

        // Quick filter functions
        function setQuickFilter(filter) {
            const today = new Date();
            let startDate, endDate;
            
            switch (filter) {
                case 'today':
                    startDate = today.toISOString().split('T')[0];
                    endDate = today.toISOString().split('T')[0];
                    break;
                case 'week':
                    startDate = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    endDate = today.toISOString().split('T')[0];
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
                    break;
                case 'quarter':
                    const quarter = Math.floor(today.getMonth() / 3);
                    startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
                    endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0).toISOString().split('T')[0];
                    break;
                case 'failed':
                    document.getElementById('resultFilter').value = 'Fail';
                    loadReportData();
                    return;
                case 'cpri':
                    document.getElementById('statusFilter').value = 'Sent to CPRI';
                    loadReportData();
                    return;
            }
            
            document.getElementById('startDate').value = startDate;
            document.getElementById('endDate').value = endDate;
            
            loadReportData();
        }

        // Chart control functions
        function changeChartPeriod(period) {
            currentChartPeriod = period;
            
            const today = new Date();
            let startDate;
            
            switch (period) {
                case '7d':
                    startDate = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    break;
                case '30d':
                    startDate = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    break;
                case '90d':
                    startDate = new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    break;
            }
            
            document.getElementById('startDate').value = startDate;
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            
            loadReportData();
        }
        
        function toggleChartType() {
            const chart = charts.distribution;
            if (chart) {
                chart.config.type = chart.config.type === 'doughnut' ? 'pie' : 'doughnut';
                chart.update();
            }
        }
        
        function updateAnalyticsChart(type) {
            showNotification('Chart Updated: Showing analytics by ' + type, 'info');
        }
        
        function updateVolumeChart(type) {
            const chart = charts.volume;
            if (chart) {
                chart.config.type = type === 'bar' ? 'bar' : 'line';
                chart.update();
            }
        }

        // Action functions
        function editTest(testId) {
            showNotification('Edit functionality for test ' + testId + ' would open here', 'info');
        }
        
        function deleteTest(testId) {
            if (confirm('Are you sure you want to delete this test result? This action cannot be undone.')) {
                showLoading();
                
                const formData = new FormData();
                formData.append('action', 'deleteTest');
                formData.append('test_id', testId);
                
                fetch('reports.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        loadReportData();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showNotification('Network error', 'error');
                });
            }
        }
        
        function exportReport(format) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const productId = document.getElementById('productFilter').value;
            const testTypeId = document.getElementById('testTypeFilter').value;
            const result = document.getElementById('resultFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'reports.php';
            form.style.display = 'none';
            
            const fields = {
                action: 'exportReport',
                format: format,
                startDate: startDate,
                endDate: endDate,
                productId: productId || '',
                testTypeId: testTypeId || '',
                result: result || '',
                status: status || ''
            };
            
            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
       function printReport() {
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Get the current active tab content
    const activeContent = document.querySelector('.report-content.active');
    
    // Get all the data from the current table
    const table = document.getElementById('resultsTable');
    const tableRows = table.querySelectorAll('tbody tr');
    
    // Build HTML for printing
    let printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Labify - Test Report</title>
            <style>
                @media print {
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .print-header { 
                        text-align: center; 
                        margin-bottom: 30px; 
                        border-bottom: 2px solid #333; 
                        padding-bottom: 20px;
                    }
                    .print-header h1 { 
                        color: #2c3e50; 
                        margin: 0; 
                        font-size: 24px; 
                    }
                    .print-header .subtitle { 
                        color: #7f8c8d; 
                        margin: 5px 0 20px 0; 
                        font-size: 14px; 
                    }
                    .print-info {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 20px;
                        font-size: 12px;
                        color: #555;
                    }
                    .print-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                        font-size: 12px;
                    }
                    .print-table th {
                        background-color: #f5f5f5;
                        color: #333;
                        text-align: left;
                        padding: 10px;
                        border: 1px solid #ddd;
                        font-weight: bold;
                    }
                    .print-table td {
                        padding: 8px 10px;
                        border: 1px solid #ddd;
                    }
                    .print-table tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    .print-footer {
                        margin-top: 40px;
                        text-align: center;
                        font-size: 11px;
                        color: #777;
                        border-top: 1px solid #ddd;
                        padding-top: 20px;
                    }
                    .badge {
                        display: inline-block;
                        padding: 3px 8px;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: bold;
                        text-transform: uppercase;
                    }
                    .badge-pass { background-color: #d4edda; color: #155724; }
                    .badge-fail { background-color: #f8d7da; color: #721c24; }
                    .badge-pending { background-color: #fff3cd; color: #856404; }
                    .no-print { display: none !important; }
                    .page-break { page-break-before: always; }
                }
                @media screen {
                    body { background: white; color: black; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>Labify - Test Report</h1>
                <div class="subtitle">Advanced Laboratory Testing System</div>
                <div class="print-info">
                    <div>
                        <strong>Generated:</strong> ${new Date().toLocaleString()}<br>
                        <strong>Total Tests:</strong> ${reportData.length}
                    </div>
                    <div>
                        <strong>Date Range:</strong> ${document.getElementById('startDate').value} to ${document.getElementById('endDate').value}<br>
                        <strong>User:</strong> <?php echo htmlspecialchars($tested_by_name); ?>
                    </div>
                </div>
            </div>
    `;
    
    // Check which tab is active and print appropriate content
    if (currentTab === 'detailed') {
        // Print table view
        if (tableRows.length > 0) {
            printContent += `
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Test ID</th>
                            <th>Product</th>
                            <th>Test Type</th>
                            <th>Date</th>
                            <th>Result</th>
                            <th>Status</th>
                            <th>Tested By</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            tableRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 7) {
                    const testId = cells[0].textContent.trim();
                    const product = cells[1].textContent.trim();
                    const testType = cells[2].textContent.trim();
                    const date = cells[3].textContent.trim();
                    const result = cells[4].querySelector('.priority-badge')?.textContent.trim() || cells[4].textContent.trim();
                    const status = cells[5].querySelector('.priority-badge')?.textContent.trim() || cells[5].textContent.trim();
                    const tester = cells[6].textContent.trim();
                    
                    // Determine badge class based on result
                    let resultClass = 'badge-pending';
                    if (result.toLowerCase().includes('pass')) resultClass = 'badge-pass';
                    if (result.toLowerCase().includes('fail')) resultClass = 'badge-fail';
                    
                    printContent += `
                        <tr>
                            <td><strong>${testId}</strong></td>
                            <td>${product}</td>
                            <td>${testType}</td>
                            <td>${date}</td>
                            <td><span class="badge ${resultClass}">${result}</span></td>
                            <td>${status}</td>
                            <td>${tester}</td>
                        </tr>
                    `;
                }
            });
            
            printContent += `
                    </tbody>
                </table>
            `;
        } else {
            printContent += `<p style="text-align: center; color: #777; font-style: italic;">No data available for printing</p>`;
        }
    } else if (currentTab === 'overview') {
        // Print overview statistics and charts
        const totalTests = document.getElementById('totalTests').textContent;
        const passedTests = document.getElementById('passedTests').textContent;
        const failedTests = document.getElementById('failedTests').textContent;
        const passRate = document.getElementById('passRate').textContent;
        
        printContent += `
            <div style="margin-bottom: 30px;">
                <h2 style="color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">Report Summary</h2>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #3498db;">${totalTests}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Total Tests</div>
                    </div>
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #27ae60;">${passedTests}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Passed Tests</div>
                    </div>
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #e74c3c;">${failedTests}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Failed Tests</div>
                    </div>
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #9b59b6;">${passRate}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Success Rate</div>
                    </div>
                </div>
            </div>
            
            <div style="margin: 30px 0;">
                <h2 style="color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">Filter Criteria</h2>
                <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                    <tr>
                        <td style="padding: 8px; border: 1px solid #eee; width: 30%;"><strong>Date Range</strong></td>
                        <td style="padding: 8px; border: 1px solid #eee;">${document.getElementById('startDate').value} to ${document.getElementById('endDate').value}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #eee;"><strong>Product Filter</strong></td>
                        <td style="padding: 8px; border: 1px solid #eee;">${document.getElementById('productFilter').value || 'All Products'}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #eee;"><strong>Test Type</strong></td>
                        <td style="padding: 8px; border: 1px solid #eee;">${document.getElementById('testTypeFilter').value || 'All Types'}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #eee;"><strong>Result Filter</strong></td>
                        <td style="padding: 8px; border: 1px solid #eee;">${document.getElementById('resultFilter').value || 'All Results'}</td>
                    </tr>
                </table>
            </div>
        `;
    } else if (currentTab === 'cpri') {
        // Print CPRI status
        const cpriSubmitted = document.getElementById('criSubmitted').textContent;
        const cpriPending = document.getElementById('criPending').textContent;
        const cpriInProgress = document.getElementById('criInProgress').textContent;
        const cpriRemake = document.getElementById('criRemake').textContent;
        
        printContent += `
            <div style="margin-bottom: 30px;">
                <h2 style="color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">CPRI Status Summary</h2>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #f39c12;">${cpriSubmitted}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Submitted to CPRI</div>
                    </div>
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #95a5a6;">${cpriPending}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Pending</div>
                    </div>
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #3498db;">${cpriInProgress}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">In Progress</div>
                    </div>
                    <div style="text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #e67e22;">${cpriRemake}</div>
                        <div style="color: #7f8c8d; font-size: 14px;">Sent for Remake</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Add footer
    printContent += `
            <div class="print-footer">
                <p>Generated by Labify Laboratory Management System</p>
                <p>Page 1 of 1 &bull; ${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
            </div>
        </body>
        </html>
    `;
    
    // Write content to print window
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Wait for content to load and trigger print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            printWindow.onafterprint = function() {
                printWindow.close();
            };
        }, 500);
    };
}
        
        function refreshCPRIData() {
            showNotification('Refreshing CPRI data...', 'info');
            loadReportData();
        }

        // Utility functions
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function getResultClass(result) {
            const classes = {
                'Pass': 'priority-low',
                'Fail': 'priority-high',
                'Inconclusive': 'priority-medium'
            };
            return classes[result] || 'priority-medium';
        }
        
        function getStatusClass(status) {
            if (!status) return 'priority-medium';
            
            const lowerStatus = status.toLowerCase();
            if (lowerStatus.includes('pass') || lowerStatus.includes('complete')) {
                return 'priority-low';
            } else if (lowerStatus.includes('fail')) {
                return 'priority-high';
            } else if (lowerStatus.includes('pending') || lowerStatus.includes('progress')) {
                return 'priority-medium';
            } else if (lowerStatus.includes('sent to cpri')) {
                return 'status-submitted';
            } else if (lowerStatus.includes('sent for remake')) {
                return 'status-remake';
            } else if (lowerStatus.includes('in review')) {
                return 'status-review';
            } else if (lowerStatus.includes('awaiting docs')) {
                return 'status-awaiting-docs';
            } else {
                return 'priority-medium';
            }
        }
        
        // Loading and notification functions
        function showLoading() {
            let overlay = document.getElementById('loadingOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loadingOverlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = '<div class="loading-spinner"></div>';
                document.body.appendChild(overlay);
            }
            overlay.style.display = 'flex';
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        function showNotification(message, type = 'info') {
            const existing = document.querySelectorAll('.notification');
            existing.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                    <div>${message}</div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Utility debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Initialize mobile menu
        function initializeMobileMenu() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            const mobileNav = document.getElementById('mobileNav');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileNav.classList.add('active');
                    mobileNavOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            }
            
            if (closeMobileMenu) {
                closeMobileMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileNav.classList.remove('active');
                    mobileNavOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                });
            }
            
            if (mobileNavOverlay) {
                mobileNavOverlay.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileNav.classList.remove('active');
                    mobileNavOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                });
            }
        }
        
        // Initialize particles
        function initializeParticles() {
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
        }
        
        // Initialize tab system
       // Replace the initializeTabSystem function with this:
function initializeTabSystem() {
    // Only initialize tab switching for report tabs
    const desktopNavLinks = document.querySelectorAll('.desktop-nav a');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-menu a');
    
    // Remove any existing click handlers that might be preventing navigation
    desktopNavLinks.forEach(link => {
        // Clone the link to remove event listeners
        const newLink = link.cloneNode(true);
        link.parentNode.replaceChild(newLink, link);
    });
    
    mobileNavLinks.forEach(link => {
        // Clone the link to remove event listeners
        const newLink = link.cloneNode(true);
        link.parentNode.replaceChild(newLink, link);
    });
}
 // Show the button when user scrolls down 20px from the top
window.onscroll = function() {
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        document.getElementById("scrollTopBtn").style.display = "block";
    } else {
        document.getElementById("scrollTopBtn").style.display = "none";
    }
};

// When the user clicks on the button, scroll to the top
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
    </script>
<!-- Scroll to Top Button -->
<button onclick="scrollToTop()" id="scrollTopBtn" title="Go to top">
    <i class="fas fa-arrow-up"></i>
</button></body>
</html>