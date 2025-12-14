<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Search parameters
 $params = [];
 $where_clauses = [];
 $sql = "SELECT 
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
        WHERE 1=1";

// Build WHERE clauses based on GET parameters
if (!empty($_GET['test_id'])) {
    $where_clauses[] = "tr.test_id LIKE ?";
    $params[] = '%' . $_GET['test_id'] . '%';
}

if (!empty($_GET['product_id'])) {
    $where_clauses[] = "tr.product_id LIKE ?";
    $params[] = '%' . $_GET['product_id'] . '%';
}

if (!empty($_GET['test_type_id'])) {
    $where_clauses[] = "tr.test_type_id = ?";
    $params[] = $_GET['test_type_id'];
}

if (!empty($_GET['test_result'])) {
    $where_clauses[] = "tr.result = ?";
    $params[] = $_GET['test_result'];
}

if (!empty($_GET['status'])) {
    $where_clauses[] = "tr.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['tested_by'])) {
    $where_clauses[] = "tr.tested_by LIKE ?";
    $params[] = '%' . $_GET['tested_by'] . '%';
}

if (!empty($_GET['date_from'])) {
    $where_clauses[] = "DATE(tr.test_date) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $where_clauses[] = "DATE(tr.test_date) <= ?";
    $params[] = $_GET['date_to'];
}

// Add WHERE clauses to SQL
if (!empty($where_clauses)) {
    $sql .= " AND " . implode(' AND ', $where_clauses);
}

 $sql .= " ORDER BY tr.test_date DESC, tr.id DESC LIMIT 100"; // Limit to prevent too many results

// Prepare and execute the statement
try {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $results = $stmt->get_result();
    
    $data = [];
    while ($row = $results->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

<?php
// In the SQL query section, make sure you're joining correctly:
$sql = "SELECT 
            tr.test_id,
            tr.product_id,
            COALESCE(tt.test_name, tr.test_type_id) as test_type,  // This will show the test name
            DATE_FORMAT(tr.test_date, '%Y-%m-%d') as test_date,
            tr.result,
            tr.status,
            tr.tested_by
        FROM test_results tr
        LEFT JOIN test_types tt ON tr.test_type_id = tt.id";
?>