<?php
require_once 'config.php';

// Get time range from query parameter
 $range = isset($_GET['range']) ? intval($_GET['range']) : 30;

try {
    // Get test results distribution
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN result = 'Pass' THEN 1 ELSE 0 END) as passed,
            SUM(CASE WHEN result = 'Fail' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
        FROM test_results 
        WHERE test_date >= DATE_SUB(CURDATE(), INTERVAL $range DAY)
    ");
    $testResults = $stmt->fetch();

    // Get trend data
    $trendLabels = [];
    $testsConducted = [];
    $passRates = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trendLabels[] = date('M j', strtotime("-$i days"));
        
        // Get tests conducted on this day
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN result = 'Pass' THEN 1 ELSE 0 END) as passed
            FROM test_results 
            WHERE DATE(test_date) = ?
        ");
        $stmt->execute([$date]);
        $dayData = $stmt->fetch();
        
        $testsConducted[] = intval($dayData['total']);
        $passRates[] = $dayData['total'] > 0 ? round(($dayData['passed'] / $dayData['total']) * 100, 1) : 0;
    }

    // Get equipment usage (from lab_equipment table)
    $stmt = $pdo->query("
        SELECT equipment_name, status
        FROM lab_equipment
        ORDER BY equipment_name
    ");
    $equipmentData = $stmt->fetchAll();

    $equipmentLabels = [];
    $equipmentStatus = [];
    foreach ($equipmentData as $equipment) {
        $equipmentLabels[] = $equipment['equipment_name'];
        $equipmentStatus[] = $equipment['status'];
    }

    // Get compliance data
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN result = 'Pass' THEN 1 ELSE 0 END) as compliant,
            SUM(CASE WHEN result = 'Fail' THEN 1 ELSE 0 END) as non_compliant,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
        FROM test_results 
        WHERE test_date >= DATE_SUB(CURDATE(), INTERVAL $range DAY)
    ");
    $complianceData = $stmt->fetch();

    $totalTestsForCompliance = $complianceData['compliant'] + $complianceData['non_compliant'] + $complianceData['pending'];
    $compliancePercentage = $totalTestsForCompliance > 0 ? [
        round(($complianceData['compliant'] / $totalTestsForCompliance) * 100),
        round(($complianceData['non_compliant'] / $totalTestsForCompliance) * 100),
        round(($complianceData['pending'] / $totalTestsForCompliance) * 100)
    ] : [0, 0, 0];

    // Get test types distribution
    $stmt = $pdo->query("
        SELECT tt.test_name, COUNT(tr.id) as count
        FROM test_types tt
        LEFT JOIN test_results tr ON tt.id = tr.test_type_id
        GROUP BY tt.id, tt.test_name
        ORDER BY count DESC
    ");
    $testTypesData = $stmt->fetchAll();

    $testTypesLabels = [];
    $testTypesCount = [];
    foreach ($testTypesData as $testType) {
        $testTypesLabels[] = $testType['test_name'];
        $testTypesCount[] = intval($testType['count']);
    }

    echo json_encode([
        'success' => true,
        'passedTests' => intval($testResults['passed']),
        'failedTests' => intval($testResults['failed']),
        'pendingTests' => intval($testResults['pending']),
        'trendLabels' => $trendLabels,
        'testsConducted' => $testsConducted,
        'passRates' => $passRates,
        'equipmentLabels' => $equipmentLabels,
        'equipmentStatus' => $equipmentStatus,
        'complianceData' => $compliancePercentage,
        'testTypesLabels' => $testTypesLabels,
        'testTypesCount' => $testTypesCount
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching chart data: ' . $e->getMessage()
    ]);
}
?>