<?php


  require_once 'includes/db_connect.php';

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
    }}

$user_id = $_SESSION['user_id'] ?? null;
$tested_by_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Unknown';



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labify | Product Management - Test Records & Inventory</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
        <link rel="stylesheet" href="./assets/products.css">

        <style>

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
        <li><a href="./log_test.php">Testing</a></li>
        <li><a href="./products.php">Products</a></li>
        <li><a href="./searching.php">Search</a></li>
        <li><a href="./reports.php">Reports</a></li>
    </ul>
            </nav>
            
          <div class="user-actions" style="position:relative; z-index:999999;">
     <!-- ADD NOTIFICATION BELL HERE -->
    <?php include_once 'includes/notification_simple.php'; ?>
    
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
            <i class="fas fa-user"></i>
        </div>
        <div class="mobile-user-info">
            <div class="mobile-user-name" id="mobileUserName">Admin User</div>
            <div class="mobile-user-role" id="mobileUserRole">Administrator</div>
        </div>
    </div>
    
    <!-- Mobile Navigation Menu -->
    <ul class="mobile-nav-menu">
        <li><a href="../log_test.php" data-tab="new-test"><i class="fas fa-vial"></i>  Testing</a></li>
        <li><a href="../products.php" data-tab="products"><i class="fas fa-cubes"></i> All Products</a></li>
        <li><a href="../searching.php" data-tab="search"><i class="fas fa-search"></i> Advanced Search</a></li>
        <li><a href="../reports.php" data-tab="reports"><i class="fas fa-chart-bar"></i> Test Reports</a></li>
       
    </ul>
    
    <!-- Mobile User Actions -->
    <div class="mobile-nav-actions">
    <a href="http://localhost/LABIFY/logout.php"
     onclick="alert('LOGOUT CLICKED');"
     style="display:inline-block; cursor:pointer; background:red; color:white; padding:10px 16px; border-radius:6px; text-decoration:none;">
     LOG OUT
  </a>
</div>
</div>

</body>
</html>