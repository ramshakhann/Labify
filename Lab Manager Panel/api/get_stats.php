<?php
require_once 'config.php';

try {
    // Get total tests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM test_results");
    $totalTests = $stmt->fetch()['total'];

    // Get passed tests
    $stmt = $pdo->query("SELECT COUNT(*) as passed FROM test_results WHERE result = 'Pass'");
    $passedTests = $stmt->fetch()['passed'];

    // Get failed tests
    $stmt = $pdo->query("SELECT COUNT(*) as failed FROM test_results WHERE result = 'Fail'");
    $failedTests = $stmt->fetch()['failed'];

    // Get pending tests
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM test_results WHERE status = 'Pending'");
    $pendingTests = $stmt->fetch()['pending'];

    // Get tests sent to CPRI
    $stmt = $pdo->query("SELECT COUNT(*) as cpri FROM test_results WHERE status = 'Sent to CPRI'");
    $cpriTests = $stmt->fetch()['cpri'];

    // Get tests sent for remake
    $stmt = $pdo->query("SELECT COUNT(*) as remake FROM test_results WHERE status = 'Sent for Remake'");
    $remakeTests = $stmt->fetch()['remake'];

    // Get tests in progress
    $stmt = $pdo->query("SELECT COUNT(*) as inprogress FROM test_results WHERE status = 'In Progress'");
    $inprogressTests = $stmt->fetch()['inprogress'];

    // Get previous day's stats for comparison
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM test_results WHERE DATE(test_date) = CURDATE() - INTERVAL 1 DAY");
    $yesterdayTotal = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as passed FROM test_results WHERE result = 'Pass' AND DATE(test_date) = CURDATE() - INTERVAL 1 DAY");
    $yesterdayPassed = $stmt->fetch()['passed'];

    $stmt = $pdo->query("SELECT COUNT(*) as failed FROM test_results WHERE result = 'Fail' AND DATE(test_date) = CURDATE() - INTERVAL 1 DAY");
    $yesterdayFailed = $stmt->fetch()['failed'];

    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM test_results WHERE status = 'Pending' AND DATE(test_date) = CURDATE() - INTERVAL 1 DAY");
    $yesterdayPending = $stmt->fetch()['pending'];

    // Calculate percentage changes
    $totalChange = $yesterdayTotal > 0 ? round((($totalTests - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
    $passedChange = $yesterdayPassed > 0 ? round((($passedTests - $yesterdayPassed) / $yesterdayPassed) * 100, 1) : 0;
    $failedChange = $yesterdayFailed > 0 ? round((($failedTests - $yesterdayFailed) / $yesterdayFailed) * 100, 1) : 0;
    $pendingChange = $yesterdayPending > 0 ? round((($pendingTests - $yesterdayPending) / $yesterdayPending) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'totalTests' => $totalTests,
        'passedTests' => $passedTests,
        'failedTests' => $failedTests,
        'pendingTests' => $pendingTests,
        'cpriTests' => $cpriTests,
        'remakeTests' => $remakeTests,
        'inprogressTests' => $inprogressTests,
        'totalChange' => $totalChange,
        'passedChange' => $passedChange,
        'failedChange' => $failedChange,
        'pendingChange' => $pendingChange
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching statistics: ' . $e->getMessage()
    ]);
}
?>