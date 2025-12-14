<?php
require_once 'config.php';

 $format = isset($_GET['format']) ? $_GET['format'] : 'csv';

try {
    // Get all reports
    $stmt = $pdo->query("
        SELECT tr.*, u.full_name as tested_by_name, tt.test_name, p.product_type
        FROM test_results tr
        LEFT JOIN users u ON tr.tester_id = u.id
        LEFT JOIN test_types tt ON tr.test_type_id = tt.id
        LEFT JOIN products p ON tr.product_id = p.product_id
        ORDER BY tr.created_at DESC
    ");
    $reports = $stmt->fetchAll();

    if ($format === 'csv') {
        // Generate CSV
        $filename = "labify-test-results-" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, ['Test ID', 'Test Type', 'Product Type', 'Tested By', 'Test Date', 'Result', 'Status', 'Remarks']);
        
        // Add data rows
        foreach ($reports as $report) {
            fputcsv($output, [
                $report['test_id'],
                $report['test_name'],
                $report['product_type'],
                $report['tested_by_name'],
                date('Y-m-d', strtotime($report['test_date'])),
                $report['result'],
                $report['status'],
                $report['remarks']
            ]);
        }
        
        fclose($output);
    } elseif ($format === 'pdf') {
        // For PDF, you would typically use a library like TCPDF or FPDF
        // For now, we'll create a simple text-based response
        $filename = "labify-test-results-" . date('Y-m-d') . ".pdf";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // This is a placeholder - in production, use a proper PDF library
        echo "%PDF-1.4\n%Labify Test Results Export\n";
        echo "Test ID,Test Type,Product Type,Tested By,Test Date,Result,Status,Remarks\n";
        
        foreach ($reports as $report) {
            echo $report['test_id'] . "," . $report['test_name'] . "," . 
                 $report['product_type'] . "," . $report['tested_by_name'] . "," . 
                 date('Y-m-d', strtotime($report['test_date'])) . "," . 
                 $report['result'] . "," . $report['status'] . "," . 
                 $report['remarks'] . "\n";
        }
    }

} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting reports: ' . $e->getMessage()
    ]);
}
?>