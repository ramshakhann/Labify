<?php
require_once 'config.php';

// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Send updates every 5 seconds
while (true) {
    try {
        // Get current stats
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM test_results");
        $totalTests = $stmt->fetch()['total'];

        $stmt = $pdo->query("SELECT COUNT(*) as passed FROM test_results WHERE result = 'Pass'");
        $passedTests = $stmt->fetch()['passed'];

        $stmt = $pdo->query("SELECT COUNT(*) as failed FROM test_results WHERE result = 'Fail'");
        $failedTests = $stmt->fetch()['failed'];

        $stmt = $pdo->query("SELECT COUNT(*) as pending FROM test_results WHERE status = 'Pending'");
        $pendingTests = $stmt->fetch()['pending'];

        // Send data as SSE
        echo "data: " . json_encode([
            'totalTests' => $totalTests,
            'passedTests' => $passedTests,
            'failedTests' => $failedTests,
            'pendingTests' => $pendingTests,
            'timestamp' => time()
        ]) . "\n\n";

        // Flush output
        ob_flush();
        flush();

        // Wait 5 seconds
        sleep(5);
    } catch (Exception $e) {
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        break;
    }
}
?>