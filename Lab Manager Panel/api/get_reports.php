<?php
require_once 'config.php';

try {
    // Get reports with user information
    $stmt = $pdo->query("
        SELECT tr.*, u.full_name as tested_by_name, tt.test_name, p.product_type
        FROM test_results tr
        LEFT JOIN users u ON tr.tester_id = u.id
        LEFT JOIN test_types tt ON tr.test_type_id = tt.id
        LEFT JOIN products p ON tr.product_id = p.product_id
        ORDER BY tr.created_at DESC
    ");
    $reports = $stmt->fetchAll();

    // Format the reports
    $formattedReports = [];
    foreach ($reports as $report) {
        $formattedReports[] = [
            'id' => $report['id'],
            'name' => $report['test_id'],
            'type' => $report['test_name'],
            'product' => $report['product_type'],
            'generatedBy' => $report['tested_by_name'],
            'date' => date('Y-m-d', strtotime($report['test_date'])),
            'status' => $report['status'],
            'result' => $report['result']
        ];
    }

    echo json_encode([
        'success' => true,
        'reports' => $formattedReports
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reports: ' . $e->getMessage()
    ]);
}
?>