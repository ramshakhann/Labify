<?php
require_once 'config.php';

try {
    // Get test types
    $stmt = $pdo->query("SELECT test_name FROM test_types ORDER BY test_name");
    $testTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get equipment
    $stmt = $pdo->query("SELECT equipment_name FROM lab_equipment ORDER BY equipment_name");
    $equipment = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get products
    $stmt = $pdo->query("SELECT product_type FROM products GROUP BY product_type ORDER BY product_type");
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'testTypes' => $testTypes,
        'equipment' => $equipment,
        'products' => $products
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching filter data: ' . $e->getMessage()
    ]);
}
?>