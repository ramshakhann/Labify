<?php
require_once 'config.php';

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);

try {
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];

    if (!empty($data['startDate'])) {
        $where[] = "DATE(tr.test_date) >= ?";
        $params[] = $data['startDate'];
    }

    if (!empty($data['endDate'])) {
        $where[] = "DATE(tr.test_date) <= ?";
        $params[] = $data['endDate'];
    }

    if (!empty($data['testTypes'])) {
        $placeholders = str_repeat('?,', count($data['testTypes']) - 1) . '?';
        $where[] = "tt.test_name IN ($placeholders)";
        $params = array_merge($params, $data['testTypes']);
    }

    if (!empty($data['statuses'])) {
        $placeholders = str_repeat('?,', count($data['statuses']) - 1) . '?';
        $where[] = "tr.status IN ($placeholders)";
        $params = array_merge($params, $data['statuses']);
    }

    if (!empty($data['products'])) {
        $placeholders = str_repeat('?,', count($data['products']) - 1) . '?';
        $where[] = "p.product_type IN ($placeholders)";
        $params = array_merge($params, $data['products']);
    }

    $sql = "
        SELECT tr.*, u.full_name as tested_by_name, tt.test_name, p.product_type
        FROM test_results tr
        LEFT JOIN users u ON tr.tester_id = u.id
        LEFT JOIN test_types tt ON tr.test_type_id = tt.id
        LEFT JOIN products p ON tr.product_id = p.product_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY tr.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
        'message' => 'Error filtering reports: ' . $e->getMessage()
    ]);
}
?>