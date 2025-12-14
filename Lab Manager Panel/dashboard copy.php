
<?php include "../auth.php"; 

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
    }
}

 $user_id = $_SESSION['user_id'] ?? null;
 $tested_by_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Unknown';

// Handle test type operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'addTestType') {
        $testCode = $_POST['testCode'] ?? '';
        $testName = $_POST['testName'] ?? '';
        
        // Validate input
        if (empty($testCode) || empty($testName)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        
        try {
            // Check if test code already exists
            $stmt = $pdo->prepare("SELECT id FROM test_types WHERE test_code = ?");
            $stmt->execute([$testCode]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Test code already exists']);
                exit;
            }
            
            // Insert new test type
            $stmt = $pdo->prepare("INSERT INTO test_types (test_code, test_name) VALUES (?, ?)");
            $stmt->execute([$testCode, $testName]);
            
            echo json_encode(['success' => true, 'message' => 'Test type added successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    } elseif ($action === 'editTestType') {
        $testId = $_POST['testId'] ?? '';
        $testCode = $_POST['testCode'] ?? '';
        $testName = $_POST['testName'] ?? '';
        
        // Validate input
        if (empty($testId) || empty($testCode) || empty($testName)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        
        try {
            // Check if test code already exists for another test type
            $stmt = $pdo->prepare("SELECT id FROM test_types WHERE test_code = ? AND id != ?");
            $stmt->execute([$testCode, $testId]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Test code already exists']);
                exit;
            }
            
            // Update test type
            $stmt = $pdo->prepare("UPDATE test_types SET test_code = ?, test_name = ? WHERE id = ?");
            $stmt->execute([$testCode, $testName, $testId]);
            
            echo json_encode(['success' => true, 'message' => 'Test type updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    } elseif ($action === 'deleteTestType') {
        $testId = $_POST['testId'] ?? '';
        
        // Validate input
        if (empty($testId)) {
            echo json_encode(['success' => false, 'message' => 'Test ID is required']);
            exit;
        }
        
        try {
            // Check if test type is being used in test results
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM test_results WHERE test_type_id = ?");
            $stmt->execute([$testId]);
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete test type that is in use']);
                exit;
            }
            
            // Delete test type
            $stmt = $pdo->prepare("DELETE FROM test_types WHERE id = ?");
            $stmt->execute([$testId]);
            
            echo json_encode(['success' => true, 'message' => 'Test type deleted successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getTestType') {
    $testId = $_GET['testId'] ?? '';
    
    if (empty($testId)) {
        echo json_encode(['success' => false, 'message' => 'Test ID is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM test_types WHERE id = ?");
        $stmt->execute([$testId]);
        $testType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($testType) {
            echo json_encode(['success' => true, 'testType' => $testType]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Test type not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labify| Advanced Laboratory Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Grid Layout Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 300;
            color: #333;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-size: 1rem;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #64ffda;
            box-shadow: 0 0 0 3px rgba(100, 255, 218, 0.2);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* List Styles */
        .list-item {
            display: flex;
            justify-content: space-evenly;
            align-items: center;
            padding: 1rem;
            gap: 3px;
            border-bottom: 1px solid #063683ff;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item-title {
            font-weight: 500;
        }
        
        .list-item-subtitle {
            color: #0e8cfaff;
            font-size: 0.9rem;
        }
        
        .list-item-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Chart Container */
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pass {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }
        
        .status-fail {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-in-progress {
            background: rgba(33, 150, 243, 0.2);
            color: #2196f3;
        }
        
        /* Priority Badge */
        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-low {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }
        
        .priority-medium {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .priority-high {
            background: rgba(255, 152, 0, 0.2);
            color: #ff9800;
        }
        
        .priority-urgent {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Only Card Layout Changes - Preserves Your Theme */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .card {
    overflow: hidden;
}
        /* Card Header Layout  */
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        /* Card Content Layout Only */
        .card-content {
            max-height: 400px;
            overflow-y: auto; 
        }
        
        /* List Item Layout Only */
        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 221, 245, 0.43);
            transition: background-color 0.2s;
        }
        
        .list-item:hover {
            background-color: rgba(3, 74, 94, 0.38);
        }
        
        .list-item-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .list-item-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Button Layout Only - Preserves Your Colors */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Stats Layout Only */
        .stat {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(12, 255, 235, 0.96);
        }
        
        /* Priority Badge Layout Only */
        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* Media Queries for Responsiveness */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
      
        .schedule-details {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .schedule-time {
            font-size: 0.85rem;
            color: rgba(12, 255, 235, 0.8);
            font-weight: 500;
            min-width: 90px;
            text-align: right;
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: #0a1128;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(100, 255, 218, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(100, 255, 218, 0.2);
            padding-bottom: 10px;
        }
        
        .modal-title {
            color: #64ffda;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #64ffda;
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            border-top: 1px solid rgba(100, 255, 218, 0.2);
            padding-top: 10px;
        }
        
        /* Button Icon Styles */
        .btn-icon {
            background: none;
            border: none;
            color: #64ffda;
            cursor: pointer;
            padding: 5px;
            font-size: 16px;
        }
        #testTypesList {
    max-height: 400px;
    overflow-y: auto;
}
        .btn-icon-delete {
            color: #f44336;
        }
        
        .btn-icon:hover {
            opacity: 0.7;
        }
        
        .btn-secondary {
            background-color: transparent;
            color: #64ffda;
            border: 1px solid #64ffda;
        }
        
        .btn-secondary:hover {
            background-color: rgba(100, 255, 218, 0.1);
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
    background: linear-gradient(45deg, #16f7f0ff, #098fbcff); 
    border-radius: 10px;
    border: 2px solid #1a1a2e; 
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(45deg, #7efffb, #0ab8d1); 
}

/* For Firefox */
* {
    scrollbar-width: thin; 
    scrollbar-color: #16f7f0ff #1a1a2e;
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
                    <!-- Mobile Menu Button -->
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="desktop-nav">
                    <ul>
                        <li><a href="#" class="active" data-tab="dashboard">Dashboard</a></li>
                        <li><a href="products.php">Products</a></li>
                        <li><a href="log_test.php" >Testing</a></li>
                        <li><a href="searching.php" >Search</a></li>
                        <li><a href="reports.php" >Reports</a></li>
                    </ul>
                </nav>
                
                <!-- Desktop User Actions -->
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
            <li><a href="dashboard.php" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php" data-tab="products"><i class="fas fa-cubes"></i>Products</a></li>
            <li><a href="log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> Testing</a></li>
            <li><a href="searching.php" data-tab="search"><i class="fas fa-search"></i> Search</a></li>
            <li><a href="reports.php" data-tab="reports"><i class="fas fa-chart-bar"></i>Reports</a></li>
        </ul>
        
        <!-- Mobile User Actions -->
        <div class="mobile-nav-actions">
            <button class="btn btn-primary" onclick="window.location.href='http://localhost/LABIFY/logout.php'">
                LOG OUT
            </button>
        </div>
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
            <h2>Advanced Laboratory Automation</h2>
            <p>Transform your electrical product testing with our cutting-edge automation platform featuring real-time monitoring, advanced analytics, and seamless CPRI integration.</p>
            <button class="btn btn-primary" id="exploreFeatures">Explore Features</button>
        </div>
    </section>

    <!-- 3D Slider -->
    <div class="container">
        <div class="slider-container">
            <div class="slider" id="slider">
                <div class="slide active">
                    <div class="slide-bg"></div>
                    <div class="bg-elements" id="bgElements1"></div>
                    <div class="slide-content">
                        <h3>Real-Time Monitoring</h3>
                        <p>Track all your testing processes in real-time with advanced visualization tools</p>
                    </div>
                </div>
                <div class="slide next">
                    <div class="slide-bg"></div>
                    <div class="bg-elements" id="bgElements2"></div>
                    <div class="slide-content">
                        <h3>Advanced Analytics</h3>
                        <p>Gain insights from your testing data with powerful analytical tools</p>
                    </div>
                </div>
                <div class="slide prev">
                    <div class="slide-bg"></div>
                    <div class="bg-elements" id="bgElements3"></div>
                    <div class="slide-content">
                        <h3>CPRI Integration</h3>
                        <p>Seamlessly submit your test results to CPRI for approval</p>
                    </div>
                </div>
            </div>
            <div class="slider-nav">
                <div class="slider-dot active" data-index="0"></div>
                <div class="slider-dot" data-index="1"></div>
                <div class="slider-dot" data-index="2"></div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Section -->
    <section class="dashboard">
        <div class="container">
            <div class="tabs">
                <div class="tab active" data-tab="dashboard">Highlights</div>
            </div>
                            <!-- Notifications Card -->
       <div class="card fade-in" style="margin-bottom:20px;">
    <div class="card-bubbles" id="bubbles8"></div>
    <div class="card-header">
        <h3 class="card-title">User Activity</h3>
        <span class="card-icon"><i class="fas fa-bell"></i></span>
    </div>

    
    
                <div class="equipment-status">
<?php
// First check what column exists
try {
    // Try with login_time column first
    $recent_logins = $pdo->query("
        SELECT username, login_time 
        FROM login_logs 
        WHERE login_time IS NOT NULL
        ORDER BY login_time DESC 
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    // If login_time doesn't exist, try created_at
    $recent_logins = $pdo->query("
        SELECT username, created_at as login_time 
        FROM login_logs 
        WHERE created_at IS NOT NULL
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
}

$colors = ['#64ffda', '#00b4d8', '#f87171', '#a78bfa', '#fb923c'];

if (!empty($recent_logins)) {
    foreach ($recent_logins as $index => $login) {
        $color = $colors[$index % count($colors)];
        
        // Simple time display
        if (!empty($login['login_time'])) {
            $time_display = date("h:i A", strtotime($login['login_time']));
        } else {
            $time_display = "--";
        }
        
        echo '
        <div class="equipment-item">
            <div class="equipment-name">
                <span class="animated-dot" style="background: ' . $color . '"></span>
                ' . htmlspecialchars($login['username']) . '
            </div>
            <span>' . $time_display . '</span>
        </div>';
    }
} else {
    echo '<div class="equipment-item">
            <div class="equipment-name">
                <span class="animated-dot" style="background: #666"></span>
                No recent activity
            </div>
            <span>--</span>
          </div>';
}
?>
</div>

<style>
.animated-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 10px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.5); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
</div>
            <!-- Stats Grid -->
            <div class="stats-grid">
                <!-- Test Success Rate Card -->
      <div class="card fade-in">
    <div class="card-bubbles" id="bubbles5"></div>
    <div class="card-header">
        <h3 class="card-title">Test Success Rate</h3>
        <span class="card-icon"><i class="fas fa-chart-line"></i></span>
    </div>
    <div class="stat"style="font-size:2rem">
        <?php 
        $success_data = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN result IN ('pass', 'completed', 'approved') THEN 1 ELSE 0 END) as passed FROM test_results")->fetch();
        $success_rate = $success_data['total'] > 0 ? round(($success_data['passed'] / $success_data['total']) * 100) : 0;
        echo $success_rate . '%'; 
        ?>
    </div>
    <p class="stat-desc">Overall success rate</p>
</div>
        
        <!-- Top Tester Card -->
<div class="card fade-in">
    <div class="card-bubbles" id="bubbles6"></div>
    <div class="card-header">
        <h3 class="card-title">Top Tester</h3>
        <span class="card-icon"><i class="fas fa-trophy"></i></span>
    </div>
    <div class="stat" style="font-size:2rem">
        <?php 
        $top_tester = $pdo->query("SELECT u.full_name, COUNT(tr.id) as test_count FROM test_results tr JOIN users u ON tr.tester_id = u.id GROUP BY u.id ORDER BY test_count DESC LIMIT 1")->fetch();
        echo htmlspecialchars($top_tester['full_name'] ?? 'No data'); 
        ?>
    </div>
    <p class="stat-desc">
        <?php echo ($top_tester['test_count'] ?? 0) . ' tests conducted'; ?>
    </p>
</div>
        

        
        <!-- CPRI Approval Status Card -->
        <div class="card fade-in">
            <div class="card-bubbles" id="bubbles7"></div>
            <div class="card-header">
                <h3 class="card-title">CPRI Approval Status</h3>
                <span class="card-icon"><i class="fas fa-check-circle"></i></span>
            </div>
             <div class="stat" style="font-size:2rem">
        <?php 
        $success_data = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN STATUS IN ('sent to CPRI') THEN 1 ELSE 0 END) as passed FROM test_results")->fetch();
        $success_rate = $success_data['total'] > 0 ? round(($success_data['passed'] / $success_data['total']) * 100) : 0;
        echo $success_rate . '%'; 
        ?>
    </div>
    <p class="stat-desc">Overall success rate</p>
        </div>
  
                <!-- Total Tests Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles1"></div>
                    <div class="card-header">
                        <h3 class="card-title">Total Tests</h3>
                        <span class="card-icon"><i class="fas fa-vial"></i></span>
                    </div>
                    <div class="stat" style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $total_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results")->fetch()['count'];
                        echo $total_tests; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <?php 
                        $today_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
                        echo "<i class='fas fa-arrow-up'></i> " . $today_tests . " new today"; 
                        ?>
                    </p>
                </div>
 

                <!-- Passed Tests Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles2"></div>
                    <div class="card-header">
                        <h3 class="card-title">Passed Tests</h3>
                        <span class="card-icon"><i class="fas fa-check"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    "
                    >
                        <?php 
                        $passed_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE result = 'Pass'")->fetch()['count'];
                        echo $passed_tests; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <?php 
                        $total_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results")->fetch()['count'];
                        $pass_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100) : 0;
                        echo "<i class='fas fa-check-circle'></i> " . $pass_rate . "% pass rate"; 
                        ?>
                    </p>
                </div>
                
                <!-- Failed Tests Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles3"></div>
                    <div class="card-header">
                        <h3 class="card-title">Failed Tests</h3>
                        <span class="card-icon"><i class="fas fa-times"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $failed_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE result = 'Fail'")->fetch()['count'];
                        echo $failed_tests; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <?php 
                        $total_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results")->fetch()['count'];
                        $fail_rate = $total_tests > 0 ? round(($failed_tests / $total_tests) * 100) : 0;
                        echo "<i class='fas fa-times-circle'></i> " . $fail_rate . "% fail rate"; 
                        ?>
                    </p>
                </div>
                
                <!-- Pending Tests Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles4"></div>
                    <div class="card-header">
                        <h3 class="card-title">Pending Tests</h3>
                        <span class="card-icon"><i class="fas fa-clock"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $pending_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE status = 'Pending'")->fetch()['count'];
                        echo $pending_tests; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <?php 
                        $total_tests = $pdo->query("SELECT COUNT(*) as count FROM test_results")->fetch()['count'];
                        $pending_rate = $total_tests > 0 ? round(($pending_tests / $total_tests) * 100) : 0;
                        echo "<i class='fas fa-hourglass-half'></i> " . $pending_rate . "% pending"; 
                        ?>
                    </p>
                </div>
                
                <!-- Total Products Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles5"></div>
                    <div class="card-header">
                        <h3 class="card-title">Total Products</h3>
                        <span class="card-icon"><i class="fas fa-boxes"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $total_products = $pdo->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
                        echo $total_products; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <i class="fas fa-box"></i> Active inventory
                    </p>
                </div>
                
                <!-- Scheduled Tests Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles6"></div>
                    <div class="card-header">
                        <h3 class="card-title">Scheduled Tests</h3>
                        <span class="card-icon"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.50rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $scheduled_tests = $pdo->query("SELECT COUNT(*) as count FROM test_schedule WHERE scheduled_date >= NOW()")->fetch()['count'];
                        echo $scheduled_tests; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <?php 
                        $week_tests = $pdo->query("SELECT COUNT(*) as count FROM test_schedule WHERE scheduled_date >= NOW() AND scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetch()['count'];
                        echo "<i class='fas fa-calendar-check'></i> " . $week_tests . " this week"; 
                        ?>
                    </p>
                </div>
                
                <!-- Team Members Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles7"></div>
                    <div class="card-header">
                        <h3 class="card-title">Team Members</h3>
                        <span class="card-icon"><i class="fas fa-user-tie"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $team_members = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
                        echo $team_members; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <?php 
                        $active_members = $pdo->query("SELECT COUNT(DISTINCT tester_id) as count FROM test_results WHERE tester_id IS NOT NULL")->fetch()['count'];
                        echo "<i class='fas fa-users'></i> " . $active_members . " active today"; 
                        ?>
                    </p>
                </div>
                
                <!-- Test Types Card -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles8"></div>
                    <div class="card-header">
                        <h3 class="card-title">Test Types</h3>
                        <span class="card-icon"><i class="fas fa-microscope"></i></span>
                    </div>
                    <div class="stat"
                    style="
                     background: rgba(0, 9, 52, 0.1);
    color: #64ffda;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 2rem;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(100, 255, 218, 0.3);
                    ">
                        <?php 
                        $test_types = $pdo->query("SELECT COUNT(*) as count FROM test_types")->fetch()['count'];
                        echo $test_types; 
                        ?>
                    </div>
                    <p class="stat-desc">
                        <i class="fas fa-vial"></i> Available tests
                    </p>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="charts-grid">
                <!-- Test Results by Type Chart -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles9"></div>
                    <div class="card-header">
                        <h3 class="card-title">Test Results by Type</h3>
                     
                    </div>
                    <div class="chart-container">
                        <canvas id="testTypeChart"></canvas>
                    </div>
                </div>

                <!-- Product Test Distribution Chart -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles10"></div>
                    <div class="card-header">
                        <h3 class="card-title">Product Test Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="productChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Products Section -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles11"></div>
                    <div class="card-header">
                        <h3 class="card-title">Products Inventory</h3>
                    </div>
                    <div class="card-content" id="productsList">
                        <?php
                        $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5")->fetchAll();
                        
                        foreach ($products as $product) {
                            $test_count = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE product_id = '{$product['product_id']}'")->fetch()['count'];
                            
                            echo "
                            <div class='list-item'>
                                <div>
                                    <div class='list-item-title'>{$product['product_id']}</div>
                                    <div class='list-item-subtitle'>{$product['product_type']} • Rev: {$product['revision']}</div>
                                </div>
                                <div class='list-item-actions'>
                                    <span class='stat'>{$test_count} tests</span>
                                </div>
                            </div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Test Schedule Section -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles12"></div>
                    <div class="card-header">
                        <h3 class="card-title">Test Schedule</h3>
                    </div>
                    <div class="card-content" id="scheduleList">
                        <?php
                        $scheduled_tests = $pdo->query("
                            SELECT ts.*, p.product_type, tt.test_name 
                            FROM test_schedule ts 
                            JOIN products p ON ts.product_id = p.product_id 
                            JOIN test_types tt ON ts.test_type_id = tt.id 
                            WHERE ts.scheduled_date >= NOW()
                            ORDER BY ts.scheduled_date ASC 
                            LIMIT 5
                        ")->fetchAll();
                        
                        foreach ($scheduled_tests as $test) {
                            $priority_class = 'priority-' . strtolower($test['priority']);
                            $formatted_date = date('M j, H:i', strtotime($test['scheduled_date']));
                            
                            echo "
                            <div class='list-item'>
                                <div class='list-item-content'>
                                    <div>
                                        <div class='list-item-title'>{$test['id']}</div>
                                        <div class='list-item-subtitle'>{$test['product_type']} • {$test['test_name']}</div>
                                    </div>
                                </div>
                                <div class='list-item-actions'>
                                    <div class='schedule-details'>
                                        <span class='priority-badge {$priority_class}'>{$test['priority']}</span>
                                        <span class='schedule-time'>{$formatted_date}</span>
                                    </div>
                                </div>
                            </div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Team Section -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles13"></div>
                    <div class="card-header">
                        <h3 class="card-title">Team Members</h3>
                    </div>
                    <div class="card-content" id="teamList">
                        <?php
                        $team_members = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
                        
                        foreach ($team_members as $member) {
                            $test_count = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE tester_id = {$member['id']}")->fetch()['count'];
                            $role_class = 'role-' . str_replace('_', '-', $member['role']);
                            
                            echo "
                            <div class='list-item'>
                                <div>
                                    <div class='list-item-title'>{$member['full_name']}</div>
                                    <div class='list-item-subtitle'>{$member['email']} • {$member['role']}</div>
                                </div>
                                <div class='list-item-actions'>
                                    <span class='stat'>{$test_count} tests</span>
                                </div>
                            </div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Test Types Section -->
                <div class="card fade-in">
                    <div class="card-bubbles" id="bubbles14"></div>
                    <div class="card-header">
                        <h3 class="card-title">Available Test Types</h3>
                        <button class="btn btn-view " id="addTestTypeBtn">Add Type</button>
                    </div>
                    <div class="card-content" id="testTypesList" >
                        <?php
                        $test_types = $pdo->query("SELECT * FROM test_types ORDER BY id")->fetchAll();
                        
                        foreach ($test_types as $type) {
                            $test_count = $pdo->query("SELECT COUNT(*) as count FROM test_results WHERE test_type_id = {$type['id']}")->fetch()['count'];
                            
                            echo "
                            <div class='list-item'>
                                <div>
                                    <div class='list-item-title'>{$type['test_code']}</div>
                                    <div class='list-item-subtitle'>{$type['test_name']}</div>
                                </div>
                                <div class='list-item-actions'>
                                    <span class='stat'>{$test_count} tests</span>
                                    <button class='btn btn-primary' onclick='editTestType({$type['id']})'><i class='fas fa-edit'></i></button>
                                    <button class='btn btn-delete' onclick='deleteTestType({$type['id']})'><i class='fas fa-trash'></i></button>
                                </div>
                            </div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Add Test Type Modal -->
    <div id="addTestTypeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Test Type</h3>
                <button class="modal-close" onclick="closeModal('addTestTypeModal')">&times;</button>
            </div>
            <form id="addTestTypeForm">
                <div class="form-group">
                    <label class="form-label" for="testCode">Test Code</label>
                    <input type="text" id="testCode" class="form-input" placeholder="e.g., VLT" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="testName">Test Name</label>
                    <input type="text" id="testName" class="form-input" placeholder="e.g., Voltage Withstand Test" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTestTypeModal')">Cancel</button>
                    <button type="submit" class="btn">Add Test Type</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Test Type Modal -->
    <div id="editTestTypeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Test Type</h3>
                <button class="modal-close" onclick="closeModal('editTestTypeModal')">&times;</button>
            </div>
            <form id="editTestTypeForm">
                <div class="form-group">
                    <label class="form-label" for="editTestId">Test ID</label>
                    <input type="text" id="editTestId" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editTestCode">Test Code</label>
                    <input type="text" id="editTestCode" class="form-input" placeholder="e.g., VLT" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editTestName">Test Name</label>
                    <input type="text" id="editTestName" class="form-input" placeholder="e.g., Voltage Withstand Test" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editTestTypeModal')">Cancel</button>
                    <button type="submit" class="btn">Update Test Type</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <?php include('./shared1/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all systems
            initializeTabSystem();
            initializeMobileMenu();
            initializeParticles();
            initializeSlider();
            initializeFormHandlers();
            initializeSearchFunctionality();
            
            // Add scroll animations
            initializeScrollAnimations();
            
            // Initialize card bubbles
            createCardBubbles();
            
            // Initialize charts
            initializeCharts();
            
            // Set up event listeners
            setupEventListeners();

            initializeMobileNavLinks();
        });

        function initializeMobileNavLinks() {
            const mobileNavLinks = document.querySelectorAll('.mobile-nav-menu a');
            
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get the href attribute
                    const href = this.getAttribute('href');
                    
                    // If it's a regular page link, navigate to it
                    if (href && href !== '#') {
                        window.location.href = href;
                        return;
                    }
                    
                    // For tab switching (if using tabs)
                    const tabId = this.getAttribute('data-tab');
                    if (tabId) {
                        // Call existing switchTab function if you have it
                        if (typeof switchTab === 'function') {
                            switchTab(tabId);
                        }
                    }
                    
                    // Close mobile menu
                    closeMobileMenuFunction();
                });
            });
        }

        // Initialize Charts
        function initializeCharts() {
            // Test Type Chart
            const testTypeCtx = document.getElementById('testTypeChart').getContext('2d');
            const testTypeChart = new Chart(testTypeCtx, {
                type: 'bar',
                data: {
                    labels: ['Voltage Withstand', 'Insulation Resistance', 'Temperature Rise'],
                    datasets: [{
                        label: 'Passed',
                        data: [
                            <?php echo $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN test_types tt ON tr.test_type_id = tt.id WHERE tt.test_code = 'VLT' AND tr.result = 'Pass'")->fetch()['count']; ?>,
                            <?php echo $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN test_types tt ON tr.test_type_id = tt.id WHERE tt.test_code = 'INS' AND tr.result = 'Pass'")->fetch()['count']; ?>,
                            <?php echo $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN test_types tt ON tr.test_type_id = tt.id WHERE tt.test_code = 'TMP' AND tr.result = 'Pass'")->fetch()['count']; ?>
                        ],
                        backgroundColor: 'rgba(76, 175, 80, 0.7)'
                    }, {
                        label: 'Failed',
                        data: [
                            <?php echo $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN test_types tt ON tr.test_type_id = tt.id WHERE tt.test_code = 'VLT' AND tr.result = 'Fail'")->fetch()['count']; ?>,
                            <?php echo $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN test_types tt ON tr.test_type_id = tt.id WHERE tt.test_code = 'INS' AND tr.result = 'Fail'")->fetch()['count']; ?>,
                            <?php echo $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN test_types tt ON tr.test_type_id = tt.id WHERE tt.test_code = 'TMP' AND tr.result = 'Fail'")->fetch()['count']; ?>
                        ],
                        backgroundColor: 'rgba(244, 67, 54, 0.7)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Product Distribution Chart
            const productCtx = document.getElementById('productChart').getContext('2d');
            const productChart = new Chart(productCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php 
                        $products = $pdo->query("SELECT DISTINCT product_type FROM products ORDER BY product_type")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($products as $i => $product) {
                            echo ($i > 0 ? ', ' : '') . "'{$product}'";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            $products = $pdo->query("SELECT DISTINCT product_type FROM products ORDER BY product_type")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($products as $i => $product) {
                                $count = $pdo->query("SELECT COUNT(*) as count FROM test_results tr JOIN products p ON tr.product_id = p.product_id WHERE p.product_type = '{$product}'")->fetch()['count'];
                                echo ($i > 0 ? ', ' : '') . $count;
                            }
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(99, 102, 241, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(251, 146, 60, 0.8)',
                            'rgba(147, 51, 234, 0.8)',
                            'rgba(236, 72, 153, 0.8)',
                            'rgba(14, 165, 233, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Setup Event Listeners
        function setupEventListeners() {
            // Add Product Button
            document.getElementById('addProductBtn')?.addEventListener('click', function() {
                document.getElementById('addProductModal').style.display = 'flex';
            });

            // Schedule Test Button
            document.getElementById('scheduleTestBtn')?.addEventListener('click', function() {
                document.getElementById('scheduleModal').style.display = 'flex';
                // Set tomorrow's date as default
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const dateStr = tomorrow.toISOString().slice(0, 16);
                document.getElementById('scheduleDate').value = dateStr;
            });

            // Add User Button
            document.getElementById('addUserBtn')?.addEventListener('click', function() {
                document.getElementById('addUserModal').style.display = 'flex';
            });

            // Add Test Type Button
            document.getElementById('addTestTypeBtn')?.addEventListener('click', function() {
                document.getElementById('addTestTypeModal').style.display = 'flex';
            });

            // Add Test Type Form
            document.getElementById('addTestTypeForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=addTestType&testCode=' + encodeURIComponent(document.getElementById('testCode').value) + 
                          '&testName=' + encodeURIComponent(document.getElementById('testName').value)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('addTestTypeModal');
                        showNotification(data.message);
                        // Reload page to show new test type
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error adding test type');
                });
            });

            // Edit Test Type Form
            document.getElementById('editTestTypeForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=editTestType&testId=' + encodeURIComponent(document.getElementById('editTestId').value) + 
                          '&testCode=' + encodeURIComponent(document.getElementById('editTestCode').value) + 
                          '&testName=' + encodeURIComponent(document.getElementById('editTestName').value)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal('editTestTypeModal');
                        showNotification(data.message);
                        // Reload the page to show updated test type
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error updating test type');
                });
            });

            // Form Submissions
            document.getElementById('addProductForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                // Add product via AJAX
                const formData = new FormData(this);
                // Implementation would go here
                closeModal('addProductModal');
                showNotification('Product added successfully!');
                location.reload();
            });

            document.getElementById('scheduleForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                // Schedule test via AJAX
                const formData = new FormData(this);
                // Implementation would go here
                closeModal('scheduleModal');
                showNotification('Test scheduled successfully!');
                location.reload();
            });

            document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                // Add user via AJAX
                const formData = new FormData(this);
                // Implementation would go here
                closeModal('addUserModal');
                showNotification('User added successfully!');
                location.reload();
            });
        }

        // Edit Test Type Function
        function editTestType(testId) {
            // Fetch test type details
            fetch(window.location.href + '?action=getTestType&testId=' + testId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate form fields
                        document.getElementById('editTestId').value = data.testType.id;
                        document.getElementById('editTestCode').value = data.testType.test_code;
                        document.getElementById('editTestName').value = data.testType.test_name;
                        
                        // Show modal
                        document.getElementById('editTestTypeModal').style.display = 'flex';
                    } else {
                        showNotification(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error fetching test type details');
                });
        }

        // Delete Test Type Function
        function deleteTestType(testId) {
            if (confirm('Are you sure you want to delete this test type?')) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=deleteTestType&testId=' + testId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message);
                        // Reload page to show updated test types
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error deleting test type');
                });
            }
        }

        // Modal Functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Notification Function
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.background = '#4a6bff';
            notification.style.color = 'white';
            notification.style.padding = '15px';
            notification.style.borderRadius = '5px';
            notification.style.zIndex = '9999';
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Tab System
        function initializeTabSystem() {
            const desktopNavLinks = document.querySelectorAll('.desktop-nav a[data-tab]');
            const tabButtons = document.querySelectorAll('.tab[data-tab]');
            
            function switchTab(tabId) {
                // Remove active class from all
                desktopNavLinks.forEach(link => link.classList.remove('active'));
                tabButtons.forEach(tab => tab.classList.remove('active'));
                
                // Add active class to current tab
                document.querySelectorAll(`[data-tab="${tabId}"]`).forEach(element => {
                    element.classList.add('active');
                });
            }
            
            // Add event listeners ONLY for desktop navigation
            desktopNavLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    switchTab(this.getAttribute('data-tab'));
                });
            });
            
            tabButtons.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    switchTab(this.getAttribute('data-tab'));
                });
            });
        }

        // Mobile Menu
        function initializeMobileMenu() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openMobileMenu();
                });
            }
            
            if (closeMobileMenu) {
                closeMobileMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    closeMobileMenuFunction();
                });
            }
            
            if (mobileNavOverlay) {
                mobileNavOverlay.addEventListener('click', function(e) {
                    e.stopPropagation();
                    closeMobileMenuFunction();
                });
            }
        }

        function openMobileMenu() {
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            if (mobileNav && mobileNavOverlay) {
                mobileNav.classList.add('active');
                mobileNavOverlay.classList.add('active');
                document.body.classList.add('mobile-menu-open');
            }
        }

        function closeMobileMenuFunction() {
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            if (mobileNav && mobileNavOverlay) {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
                document.body.classList.remove('mobile-menu-open');
            }
        }

        // Particles Background
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

        // 3D Slider
        function initializeSlider() {
            const slider = document.getElementById('slider');
            const slides = document.querySelectorAll('.slide');
            const dots = document.querySelectorAll('.slider-dot');
            let currentSlide = 0;
            
            function updateSlider() {
                slides.forEach(slide => slide.classList.remove('active', 'prev', 'next'));
                
                if (slides[currentSlide]) {
                    slides[currentSlide].classList.add('active');
                }
                if (slides[(currentSlide - 1 + slides.length) % slides.length]) {
                    slides[(currentSlide - 1 + slides.length) % slides.length].classList.add('prev');
                }
                if (slides[(currentSlide + 1) % slides.length]) {
                    slides[(currentSlide + 1) % slides.length].classList.add('next');
                }
                
                dots.forEach(dot => dot.classList.remove('active'));
                if (dots[currentSlide]) {
                    dots[currentSlide].classList.add('active');
                }
            }
            
            // Dot click events
            dots.forEach((dot, index) => {
                dot.addEventListener('click', function() {
                    currentSlide = index;
                    updateSlider();
                });
            });
            
            // Initialize slider
            updateSlider();
            
            // Auto-advance
            setInterval(() => {
                currentSlide = (currentSlide + 1) % slides.length;
                updateSlider();
            }, 5000);
        }

        // Form Handlers
        function initializeFormHandlers() {
            // Explore features button
            const exploreBtn = document.getElementById('exploreFeatures');
            if (exploreBtn) {
                exploreBtn.addEventListener('click', function() {
                    // Scroll to dashboard section
                    document.querySelector('.dashboard').scrollIntoView({ behavior: 'smooth' });
                });
            }
        }

        // Search Functionality
        function initializeSearchFunctionality() {
            // Implementation would go here
        }

        // Scroll Animations
        function initializeScrollAnimations() {
            function checkScroll() {
                const fadeElements = document.querySelectorAll('.fade-in');
                fadeElements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementVisible = 150;
                    
                    if (elementTop < window.innerHeight - elementVisible) {
                        element.classList.add('visible');
                    }
                });
            }
            
            // Initial check
            checkScroll();
            
            // Check on scroll
            window.addEventListener('scroll', checkScroll);
            
           
        }

        // Function to create bubbles for cards
        function createCardBubbles() {
            const cardBubbleContainers = [
                document.getElementById('bubbles1'),
                document.getElementById('bubbles2'),
                document.getElementById('bubbles3'),
                document.getElementById('bubbles4'),
                document.getElementById('bubbles5'),
                document.getElementById('bubbles6'),
                document.getElementById('bubbles7'),
                document.getElementById('bubbles8'),
                document.getElementById('bubbles9'),
                document.getElementById('bubbles10'),
                document.getElementById('bubbles11'),
                document.getElementById('bubbles12'),
                document.getElementById('bubbles13'),
                document.getElementById('bubbles14')
            ];
            
            const colors = [
                'rgba(22, 216, 242, 0.2)',
                'rgba(240, 244, 243, 0.2)',
                'rgba(174, 254, 239, 0.2)',
                'rgba(60, 206, 255, 0.2)'
            ];
            
            cardBubbleContainers.forEach(container => {
                if (!container) return;
                
                // Clear any existing elements
                container.innerHTML = '';
                
                // Create 4-6 bubbles per card
                const bubbleCount = Math.floor(Math.random() * 3) + 4;
                
                for (let i = 0; i < bubbleCount; i++) {
                    const bubble = document.createElement('div');
                    bubble.classList.add('bubble');
                    
                    // Random properties
                    const size = Math.random() * 40 + 10; // 10px to 50px
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    const left = Math.random() * 100;
                    const top = Math.random() * 100;
                    const animationDuration = Math.random() * 10 + 8; // 8s to 18s
                    const animationDelay = Math.random() * 5;
                    
                    bubble.style.width = `${size}px`;
                    bubble.style.height = `${size}px`;
                    bubble.style.background = color;
                    bubble.style.left = `${left}%`;
                    bubble.style.top = `${top}%`;
                    bubble.style.animationDuration = `${animationDuration}s`;
                    bubble.style.animationDelay = `${animationDelay}s`;
                    
                    container.appendChild(bubble);
                }
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
</button>


</body>
</html>