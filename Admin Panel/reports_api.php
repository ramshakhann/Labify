<?php
date_default_timezone_set('Asia/Karachi');
session_start();
require_once 'includes/db_connect.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

// Handle different report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'getReportData':
            echo json_encode(getReportData($conn));
            break;
            
        case 'exportReport':
            echo json_encode(exportReport($conn, $_POST));
            break;
            
        case 'deleteTest':
            echo json_encode(deleteTest($conn, $_POST['test_id']));
            break;
            
        case 'updateTestStatus':
            echo json_encode(updateTestStatus($conn, $_POST));
            break;
            
        case 'getReportStats':
            echo json_encode(getReportStats($conn));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// For GET requests (data loading)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'getReportData':
            echo json_encode(getReportData($conn));
            break;
            
        case 'getReportStats':
            echo json_encode(getReportStats($conn));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Main report data function
function getReportData($conn) {
    try {
        // Get filter parameters with proper escaping
        $startDate = $conn->real_escape_string($_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days')));
        $endDate = $conn->real_escape_string($_GET['endDate'] ?? date('Y-m-d'));
        $productId = isset($_GET['productId']) ? $conn->real_escape_string($_GET['productId']) : null;
        $testTypeId = isset($_GET['testTypeId']) ? intval($_GET['testTypeId']) : null;
        $result = isset($_GET['result']) ? $conn->real_escape_string($_GET['result']) : null;
        $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : null;
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : null;
        
        // Build the SQL query with prepared statements for security
        $sql = "
            SELECT 
                tr.id,
                tr.test_id,
                tr.product_id,
                tr.test_type_id,
                tr.test_date,
                tr.result,
                tr.status,
                tr.tester_id,
                tr.tested_by,
                tr.remarks,
                tr.created_at,
                p.product_type,
                p.revision,
                tt.test_code,
                tt.test_name,
                u.username as tester_username,
                u.full_name as tester_full_name
            FROM test_results tr
            LEFT JOIN products p ON tr.product_id = p.product_id
            LEFT JOIN test_types tt ON tr.test_type_id = tt.id
            LEFT JOIN users u ON tr.tester_id = u.id
            WHERE tr.test_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        $types = "ss";
        
        // Add optional filters
        if ($productId) {
            $sql .= " AND tr.product_id = ?";
            $params[] = $productId;
            $types .= "s";
        }
        
        if ($testTypeId) {
            $sql .= " AND tr.test_type_id = ?";
            $params[] = $testTypeId;
            $types .= "i";
        }
        
        if ($result) {
            $sql .= " AND tr.result = ?";
            $params[] = $result;
            $types .= "s";
        }
        
        if ($status) {
            $sql .= " AND tr.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($search) {
            $sql .= " AND (
                tr.test_id LIKE ? OR 
                tr.product_id LIKE ? OR 
                p.product_type LIKE ? OR 
                tr.tested_by LIKE ? OR 
                tr.remarks LIKE ?
            )";
            $searchTerm = "%{$search}%";
            for ($i = 0; $i < 5; $i++) {
                $params[] = $searchTerm;
                $types .= "s";
            }
        }
        
        $sql .= " ORDER BY tr.test_date DESC, tr.id DESC LIMIT 1000";
        
        // Prepare and execute with prepared statement
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("SQL prepare error: " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate duration
            $duration = 'N/A';
            if ($row['created_at'] && $row['test_date']) {
                $start = new DateTime($row['created_at']);
                $end = new DateTime($row['test_date']);
                $interval = $start->diff($end);
                $duration = $interval->format('%h hours %i minutes');
            }
            
            $data[] = [
                'id' => $row['id'],
                'test_id' => $row['test_id'],
                'product_id' => $row['product_id'],
                'product_type' => $row['product_type'] ?? 'N/A',
                'revision' => $row['revision'] ?? 'N/A',
                'test_type_id' => $row['test_type_id'],
                'test_code' => $row['test_code'] ?? 'N/A',
                'test_name' => $row['test_name'] ?? 'N/A',
                'test_date' => $row['test_date'],
                'result' => $row['result'],
                'status' => $row['status'],
                'tester_id' => $row['tester_id'],
                'tested_by' => $row['tested_by'],
                'tester_full_name' => $row['tester_full_name'] ?? $row['tested_by'],
                'remarks' => $row['remarks'],
                'created_at' => $row['created_at'],
                'duration' => $duration
            ];
        }
        
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $data,
            'total' => count($data)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error fetching report data: ' . $e->getMessage()
        ];
    }
}

// Get comprehensive report statistics
function getReportStats($conn) {
    try {
        // Get filter parameters
        $startDate = $conn->real_escape_string($_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days')));
        $endDate = $conn->real_escape_string($_GET['endDate'] ?? date('Y-m-d'));
        $productId = isset($_GET['productId']) ? $conn->real_escape_string($_GET['productId']) : null;
        
        // Build where clause
        $whereClause = "WHERE test_date BETWEEN '$startDate' AND '$endDate'";
        if ($productId) {
            $whereClause .= " AND product_id = '$productId'";
        }
        
        // Total tests
        $totalResult = $conn->query("SELECT COUNT(*) as total FROM test_results $whereClause");
        $totalTests = $totalResult->fetch_assoc()['total'] ?? 0;
        
        // Pass/Fail counts
        $resultStats = $conn->query("
            SELECT 
                COUNT(CASE WHEN result = 'Pass' THEN 1 END) as pass_count,
                COUNT(CASE WHEN result = 'Fail' THEN 1 END) as fail_count,
                COUNT(CASE WHEN result = 'Inconclusive' THEN 1 END) as inconclusive_count
            FROM test_results $whereClause
        ");
        $resultRow = $resultStats->fetch_assoc();
        $passCount = $resultRow['pass_count'] ?? 0;
        $failCount = $resultRow['fail_count'] ?? 0;
        $inconclusiveCount = $resultRow['inconclusive_count'] ?? 0;
        
        // Pass rate
        $passRate = $totalTests > 0 ? round(($passCount / $totalTests) * 100, 1) : 0;
        
        // Status breakdown
        $statusResult = $conn->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM test_results $whereClause
            GROUP BY status
            ORDER BY count DESC
        ");
        
        $statusBreakdown = [];
        while ($row = $statusResult->fetch_assoc()) {
            $statusBreakdown[] = [
                'status' => $row['status'],
                'count' => $row['count']
            ];
        }
        
        // CPRI related stats
        $criStats = $conn->query("
            SELECT 
                COUNT(CASE WHEN status = 'Sent to CPRI' THEN 1 END) as sent_to_cpri,
                COUNT(CASE WHEN status = 'Approved by CPRI' THEN 1 END) as approved_by_cpri,
                COUNT(CASE WHEN status = 'Rejected by CPRI' THEN 1 END) as rejected_by_cpri
            FROM test_results $whereClause
        ");
        $criRow = $criStats->fetch_assoc();
        
        // Average test duration
        $avgDuration = $conn->query("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, created_at, test_date)) as avg_hours
            FROM test_results 
            WHERE created_at IS NOT NULL AND test_date IS NOT NULL
            AND test_date BETWEEN '$startDate' AND '$endDate'
        ");
        $avgRow = $avgDuration->fetch_assoc();
        $avgTestHours = round($avgRow['avg_hours'] ?? 0, 1);
        
        // Product distribution
        $productDist = $conn->query("
            SELECT 
                p.product_id,
                p.product_type,
                COUNT(tr.id) as test_count
            FROM test_results tr
            LEFT JOIN products p ON tr.product_id = p.product_id
            WHERE tr.test_date BETWEEN '$startDate' AND '$endDate'
            GROUP BY tr.product_id, p.product_type
            ORDER BY test_count DESC
            LIMIT 10
        ");
        
        $productDistribution = [];
        while ($row = $productDist->fetch_assoc()) {
            $productDistribution[] = [
                'product_id' => $row['product_id'],
                'product_type' => $row['product_type'] ?? 'Unknown',
                'test_count' => $row['test_count']
            ];
        }
        
        // Test type distribution
        $testTypeDist = $conn->query("
            SELECT 
                tt.test_code,
                tt.test_name,
                COUNT(tr.id) as test_count
            FROM test_results tr
            LEFT JOIN test_types tt ON tr.test_type_id = tt.id
            WHERE tr.test_date BETWEEN '$startDate' AND '$endDate'
            GROUP BY tr.test_type_id, tt.test_code, tt.test_name
            ORDER BY test_count DESC
        ");
        
        $testTypeDistribution = [];
        while ($row = $testTypeDist->fetch_assoc()) {
            $testTypeDistribution[] = [
                'test_code' => $row['test_code'] ?? 'Unknown',
                'test_name' => $row['test_name'] ?? 'Unknown',
                'test_count' => $row['test_count']
            ];
        }
        
        // Daily trend data
        $dailyTrends = $conn->query("
            SELECT 
                DATE(test_date) as date,
                COUNT(*) as total_tests,
                COUNT(CASE WHEN result = 'Pass' THEN 1 END) as pass_count,
                COUNT(CASE WHEN result = 'Fail' THEN 1 END) as fail_count
            FROM test_results
            WHERE test_date BETWEEN '$startDate' AND '$endDate'
            GROUP BY DATE(test_date)
            ORDER BY date
        ");
        
        $trendData = [];
        while ($row = $dailyTrends->fetch_assoc()) {
            $trendData[] = [
                'date' => $row['date'],
                'total' => $row['total_tests'],
                'pass' => $row['pass_count'],
                'fail' => $row['fail_count']
            ];
        }
        
        return [
            'success' => true,
            'stats' => [
                'totalTests' => $totalTests,
                'passCount' => $passCount,
                'failCount' => $failCount,
                'inconclusiveCount' => $inconclusiveCount,
                'passRate' => $passRate,
                'avgTestHours' => $avgTestHours,
                'cpriSent' => $criRow['sent_to_cpri'] ?? 0,
                'cpriApproved' => $criRow['approved_by_cpri'] ?? 0,
                'cpriRejected' => $criRow['rejected_by_cpri'] ?? 0
            ],
            'breakdown' => [
                'status' => $statusBreakdown,
                'products' => $productDistribution,
                'testTypes' => $testTypeDistribution
            ],
            'trends' => $trendData
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error fetching report stats: ' . $e->getMessage()
        ];
    }
}

// Export report function
function exportReport($conn, $params) {
    try {
        $format = $params['format'] ?? 'csv';
        $data = getReportData($conn);
        
        if (!$data['success']) {
            return $data;
        }
        
        $exportData = $data['data'];
        
        if ($format === 'csv') {
            // Generate CSV
            $filename = 'labify_report_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Headers
            fputcsv($output, [
                'Test ID', 'Product ID', 'Product Type', 'Test Type', 
                'Test Date', 'Result', 'Status', 'Tested By', 
                'Duration', 'Remarks'
            ]);
            
            // Data rows
            foreach ($exportData as $row) {
                fputcsv($output, [
                    $row['test_id'],
                    $row['product_id'],
                    $row['product_type'],
                    $row['test_name'],
                    $row['test_date'],
                    $row['result'],
                    $row['status'],
                    $row['tested_by'],
                    $row['duration'],
                    $row['remarks']
                ]);
            }
            
            fclose($output);
            exit;
            
        } elseif ($format === 'excel') {
            // Generate Excel (as CSV with different headers)
            $filename = 'labify_report_' . date('Y-m-d_H-i-s') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo "Test ID\tProduct ID\tProduct Type\tTest Type\tTest Date\tResult\tStatus\tTested By\tDuration\tRemarks\n";
            
            foreach ($exportData as $row) {
                echo implode("\t", [
                    $row['test_id'],
                    $row['product_id'],
                    $row['product_type'],
                    $row['test_name'],
                    $row['test_date'],
                    $row['result'],
                    $row['status'],
                    $row['tested_by'],
                    $row['duration'],
                    $row['remarks']
                ]) . "\n";
            }
            exit;
            
        } else {
            return [
                'success' => false,
                'message' => 'Unsupported export format'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Export error: ' . $e->getMessage()
        ];
    }
}

// Delete test function
function deleteTest($conn, $testId) {
    try {
        $testId = $conn->real_escape_string($testId);
        
        // First check if test exists
        $check = $conn->query("SELECT test_id FROM test_results WHERE test_id = '$testId'");
        if ($check->num_rows === 0) {
            return ['success' => false, 'message' => 'Test not found'];
        }
        
        // Delete the test
        $sql = "DELETE FROM test_results WHERE test_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $testId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Test deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete test: ' . $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting test: ' . $e->getMessage()];
    }
}

// Update test status
function updateTestStatus($conn, $params) {
    try {
        $testId = $conn->real_escape_string($params['test_id']);
        $status = $conn->real_escape_string($params['status']);
        $remarks = $conn->real_escape_string($params['remarks'] ?? '');
        
        $sql = "UPDATE test_results SET status = ?, remarks = ? WHERE test_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $status, $remarks, $testId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update status: ' . $stmt->error];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}
?>