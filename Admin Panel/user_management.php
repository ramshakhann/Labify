


<!-- In your header section, add this after user actions -->
<?php
// Add after line with user actions
?>

<?php
require_once '../auth.php';

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$user_id = $_SESSION['user_id'] ?? null;
$tested_by_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Unknown';

// --- DEBUGGING: Turn on error reporting ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// --- 2. DATABASE CONNECTION ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); 
define('DB_PASS', '');     
define('DB_NAME', 'labify');

try {
    $host = 'localhost';
$dbname = 'labify';
$username = 'root';
$password = '';
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);


} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}


// --- 3. HANDLE FORM SUBMISSIONS (CRUD ACTIONS) ---
 $message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // (All your existing POST handling logic for add, edit, delete remains the same)
    if ($_POST['action'] == 'add_user') { /* ... */ }
    if ($_POST['action'] == 'edit_user') { /* ... */ }
    if ($_POST['action'] == 'delete_user') { /* ... */ }
    
    // Handle Add User
    if ($_POST['action'] == 'add_user') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $sql = "INSERT INTO users (full_name, email, password, role , is_active) VALUES (?, ?, ?, ?,1)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $email, $password, $role]);
$_SESSION['message'] = "User added successfully!";
                         

            header("Location: user_management.php");
             $_SESSION['message'] = "";
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "Error: This email address is already in use.";
            } else {
                $message = "Error: " . $e->getMessage();
            }
        }
    }

    // Handle Edit User
    if ($_POST['action'] == 'edit_user') {
        $id = $_POST['id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $sql = "UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $email, $role, $id]);
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Updated successfully!'];
            header("Location: user_management.php");
            exit();
        } catch (PDOException $e) {
             $error_message = ($e->getCode() == 23000) ? "This email address is already in use." : $e->getMessage();
            $_SESSION['notification'] = ['type' => 'error', 'message' => "Error: " . $error_message];
            header("Location: user_management.php"); // Redirect to show the error notification
            exit();}
    }

    // Handle Delete User
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_user') {

 $role = $_SESSION['role'] 
     ?? $_SESSION['user_role'] 
     ?? $_SESSION['type'] 
     ?? '';

$role = strtolower(trim($role));

if ($role !== 'admin' && $role !== 'administrator' && $role !== 'super admin') {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized access"
    ]);
    exit;
}


$userId = intval($_POST['id']);

    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
    $stmt->execute([$userId]);
}


    
}

// --- 4. FETCH DATA FROM DATABASE ---

// Fetch data for the new stat cards
 $total_users_count = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch()['count'];
 $total_testers_count = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'tester' AND is_active = 1")->fetch()['count'];

// Fetch the top tester
// Get the user with most tests conducted
$top_tester_sql = "
    SELECT 
        u.full_name,
        COUNT(tr.id) AS test_count
    FROM 
        test_results tr
    INNER JOIN 
        users u ON tr.tester_id = u.id
    GROUP BY 
        u.id, u.full_name
    ORDER BY 
        test_count DESC
    LIMIT 1
";

$top_stmt = $pdo->prepare($top_tester_sql);
$top_stmt->execute();
$top_tester = $top_stmt->fetch(PDO::FETCH_ASSOC);

// Handle results
if ($top_tester && !empty($top_tester['full_name'])) {
    $top_tester_name = $top_tester['full_name'];
    $top_tester_count = $top_tester['test_count'];
} else {
    $top_tester_name = 'No tests conducted';
    $top_tester_count = 0;
}


// Fetch all users for the table
 $users = $pdo->query("SELECT id, full_name, email, role, created_at FROM users WHERE is_active = 1 ORDER BY created_at DESC ")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Labify Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="assets/log_test_style.css">
        <link rel="stylesheet" href="assets/product_style.css">

        <link rel="stylesheet" href="assets/notification.css">
 
    <!-- Inline CSS -->
    <style>
        
        /* --- NEW: Stats Cards Section --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            margin-top: 30px;

        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card h3 i {
            margin-right: 15px;
            font-size: 2rem;
        }

        .stat-card p {
            font-size: 1rem;
            color: var(--text-color);
            margin: 0;
            font-weight: 500;
        }


        /* --- Content Section & Cards --- */
        .content-section {
            padding: 0 0 40px 0;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--accent-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            padding: 12px;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            font-size: 1rem;
        }

       

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            background-color: rgba(255, 255, 255, 0.05);
            font-weight: 600;
        }

        /* --- Modal Styles --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1002;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }

        .modal-content {
            background-color: var(--secondary-color);
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: var(--text-color);
            text-decoration: none;
            cursor: pointer;
        }

        /* --- Utility & Responsive --- */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background-color: var(--success-color);
            color: white;
        }

        @media (max-width: 768px) {
            .desktop-nav, .user-actions {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .hero h2 {
                font-size: 2rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
             
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
background: linear-gradient(135deg, rgba(14, 210, 231, 0.975), rgba(3, 61, 118, 0.4));
    border-radius: 5px;
    border: 2px solid rgba(0, 0, 0, 0.1);
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, rgba(100, 221, 255, 0.6), rgba(74, 222, 255, 0.6));
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
                    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                </div>
                <nav class="desktop-nav">
                   <ul>
                        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                        <li><a href="products.php" class="nav-link">Products</a></li>
                        <li><a href="log_test.php" class="nav-link">Testing</a></li>
                        <li><a href="searching.php" class="nav-link">Search</a></li>
                        <li><a href="reports.php" class="nav-link ">Reports</a></li>
                         <li><a href="user_management.php" class="nav-link active">Users</a></li>
                    </ul>
                </nav>
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
<div class="mobile-user-profile" style="display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; flex: 1;">
        <div class="mobile-user-avatar">
            <?php echo substr($tested_by_name, 0, 1); ?>
        </div>
        <div class="mobile-user-info">
            <div class="mobile-user-name"><?php echo htmlspecialchars($tested_by_name); ?></div>
            <div class="mobile-user-role"><?php echo htmlspecialchars($user_role); ?></div>
        </div>
    </div>
    
    <!-- Include Mobile Notification Bell -->
    <?php include_once 'includes/notification_mobile.php'; ?>
</div>
    <!-- Mobile Navigation Menu -->
    <ul class="mobile-nav-menu">
        <li><a href="dashboard.php" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="products.php" data-tab="products"><i class="fas fa-cubes"></i>Products</a></li>
        <li><a href="log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> Testing</a></li>
        <li><a href="searching.php" data-tab="search"><i class="fas fa-search"></i> Search</a></li>
        <li><a href="reports.php" data-tab="reports"><i class="fas fa-chart-bar"></i>Reports</a></li>
        <li><a href="user_management.php"><i class="fas fa-users"></i> User </a></li>
        
    </ul>
        
        <div class="mobile-nav-actions">
            <button class="btn btn-primary" onclick="window.location.href='http://localhost/LABIFY/logout.php'">
                LOG OUT
            </button>
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
           <h2>User Management</h2>
            <p>Manage system users, roles, and permissions.</p>
        </div>
    </section>
    </div>
    </div>
    </div>

    <!-- Main Content Area -->
    <main class="content-section">
        
        <div class="container">
           

            <!-- NEW: Summary Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> <?= $total_users_count ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-vial"></i> <?= $total_testers_count ?></h3>
                    <p>Total Testers</p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-trophy"></i> <?= htmlspecialchars($top_tester_name) ?></h3>
                    <p>Top Tester (<?= $top_tester_count ?> tests)</p>
                </div>
            </div>

                <div id="notification-container"></div>


            <div class="card">
                <h3>Add New User</h3>
                <form action="user_management.php" method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-grid">
                        <div class="form-group"><label>Name</label><input type="text" name="full_name" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                        <div class="form-group"><label>Role</label><select name="role" required><option value="tester">Tester</option><option value="lab_manager">Lab Manager</option><option value="admin">Admin</option></select></div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</button>
                    </div>
                </form>
            </div>

            <div class="card">
    <h3>Existing Users</h3>
    
    <!-- Search Bar -->
    <div class="search-bar" style="margin-bottom: 20px;">
        <div class="form-grid">
            <div class="form-group">
                <input type="text" id="searchInput" placeholder="Search by name or email..." 
                       style="width: 100%; padding: 12px;">
            </div>
            <div class="form-group">
                <select id="roleFilter" style="width: 100%; padding: 12px;">
                    <option value="">All Roles</option>
                    <option value="tester">Tester</option>
                    <option value="lab_manager">Lab Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
          
            
        </div>
        <!-- Add this right after <h3>Existing Users</h3> -->
<div style="display: flex; justify-content: flex-end; gap:2px; margin-bottom: 15px;">
    <div></div> <!-- Empty div for spacing -->
      <button type="button" id="clearSearch" class="btn btn-primary">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
    <button type="button" id="printAllUsers" class="btn btn-primary">
        <i class="fas fa-print"></i> Print All Users
    </button>

    <button type="button" id="exportCSV" class="btn btn-primary">
    <i class="fas fa-file-csv"></i> Export to CSV
</button>
</div>
        
        <div class="search-info" id="searchInfo" style="margin-top: 10px; font-size: 0.9rem; color: #888; display: none;">
            Showing <span id="visibleCount">0</span> of <?= count($users) ?> users
        </div>
    </div>
    
    <!-- User Table -->
    <div class="table-responsive">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Member Since</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php foreach ($users as $user): ?>
                <tr data-name="<?= htmlspecialchars(strtolower($user['full_name'])) ?>" 
                    data-email="<?= htmlspecialchars(strtolower($user['email'])) ?>"
                    data-role="<?= htmlspecialchars($user['role']) ?>">
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <span>
                            <button type="button" class="btn btn-edit" onclick="openEditUserModal('<?= htmlspecialchars(json_encode($user)) ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </span>
                        <span>
                           <form action="user_management.php" method="POST" style="display:inline;">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="id" value="<?= (int)$user['id']; ?>">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

    <button type="submit"
            class="btn btn-delete"
            onclick="return confirm('Are you sure you want to delete this user?');">
        <i class="fas fa-trash"></i> Delete
    </button>
</form>

                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- No Results Message -->
    <div id="noResults" style="display: none; text-align: center; padding: 40px; color: #888;">
        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 20px;"></i>
        <h3>No users found</h3>
        <p>Try adjusting your search terms</p>
    </div>
</div>
            
        </div>
    </main>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Edit User</h3>
            <form action="user_management.php" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" id="editUserId" name="id">
                <div class="form-group" style="margin-bottom: 15px;"><label for="editUserName">Name</label><input type="text" id="editUserName" name="full_name" required></div>
                <div class="form-group" style="margin-bottom: 15px;"><label for="editUserEmail">Email</label><input type="email" id="editUserEmail" name="email" required></div>
                <div class="form-group" style="margin-bottom: 20px;"><label for="editUserRole">Role</label><select id="editUserRole" name="role" required><option value="tester">Tester</option><option value="lab_manager">Lab Manager</option><option value="admin">Admin</option></select></div>
                <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-submit" style="background: #6c757d; color: white;" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success" >Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <?php include('./shared1/footer.php'); ?>

    <!-- Scripts -->
     <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
     <?php if (isset($_SESSION['notification'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showNotification("<?= htmlspecialchars($_SESSION['notification']['message']) ?>", "<?= htmlspecialchars($_SESSION['notification']['type']) ?>");
            });
        </script>
        <?php unset($_SESSION['notification']); // Remove it so it doesn't show again ?>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle

            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            function openMobileMenu() { mobileNav.classList.add('active'); mobileNavOverlay.classList.add('active'); }
            function closeMobileMenuFunc() { mobileNav.classList.remove('active'); mobileNavOverlay.classList.remove('active'); }
            
            mobileMenuBtn.addEventListener('click', openMobileMenu);
            closeMobileMenu.addEventListener('click', closeMobileMenuFunc);
            mobileNavOverlay.addEventListener('click', closeMobileMenuFunc);
                initializeParticles();


 // Initialize particles
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

            function handleLogout() { fetch('../logout.php', { method: 'POST', credentials: 'same-origin' }).then(() => window.location.href = '../login.php').catch(error => console.error('Logout failed:', error)); }
            document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
            document.getElementById('mobileLogoutBtn')?.addEventListener('click', handleLogout);

            const selectAllUsers = document.getElementById('selectAllUsers');
            if (selectAllUsers) {
                selectAllUsers.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.user-checkbox');
                    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                });
            }


            
        });

        function openEditUserModal(userJson) {
            const user = JSON.parse(userJson);
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserName').value = user.full_name;
            document.getElementById('editUserEmail').value = user.email;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeModal() { document.getElementById('editUserModal').style.display = 'none'; }
        window.onclick = function(event) { const modal = document.getElementById('editUserModal'); if (event.target == modal) { closeModal(); } }
   
            // --- Notification Function ---
        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="notification-message">${message}</div>
            `;
            
            container.appendChild(notification);
            
            // Trigger the slide-in animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 100); // Small delay to ensure the element is rendered before the class is added

            // Set a timer to remove the notification
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.5s ease-out forwards';
                setTimeout(() => {
                    notification.remove();
                }, 500); // Wait for the animation to finish
            }, 4000); // Display for 4 seconds
        }
        function showNotification(message, type) {
    const container = document.getElementById('notification-container');
    
    // Clear any existing notifications first (optional)
    // container.innerHTML = '';
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${iconClass}"></i>
        </div>
        <div class="notification-message">${message}</div>
        <div class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Trigger the slide-in animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // Auto-remove after 5 seconds (optional)
    const autoRemove = setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.5s ease-out forwards';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500);
        }
    }, 5000);

    // Store the timeout ID so we can clear it if user closes manually
    notification.autoRemoveTimeout = autoRemove;
}
   // Add this function to filter users
function filterUsersTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const rows = document.querySelectorAll('#usersTableBody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const email = row.getAttribute('data-email') || '';
        const role = row.getAttribute('data-role') || '';
        
        // Check if row matches search criteria
        const matchesSearch = searchTerm === '' || 
                            name.includes(searchTerm) || 
                            email.includes(searchTerm);
        
        const matchesRole = roleFilter === '' || role === roleFilter;
        
        // Show/hide row based on filters
        if (matchesSearch && matchesRole) {
            row.style.display = '';
            visibleCount++;
            
            // Highlight search term in text if applicable
            if (searchTerm !== '') {
                highlightText(row, searchTerm);
            } else {
                removeHighlights(row);
            }
        } else {
            row.style.display = 'none';
            removeHighlights(row);
        }
    });
    
    // Update counter
    document.getElementById('visibleCount').textContent = visibleCount;
    const searchInfo = document.getElementById('searchInfo');
    
    if (searchTerm !== '' || roleFilter !== '') {
        searchInfo.style.display = 'block';
        
        // Add visual indicator to search bar
        document.querySelector('.search-bar').classList.add('filter-active');
    } else {
        searchInfo.style.display = 'none';
        document.querySelector('.search-bar').classList.remove('filter-active');
    }
    
    // Show/hide "no results" message
    const noResults = document.getElementById('noResults');
    if (visibleCount === 0 && (searchTerm !== '' || roleFilter !== '')) {
        noResults.style.display = 'block';
        document.querySelector('.table-responsive').style.display = 'none';
    } else {
        noResults.style.display = 'none';
        document.querySelector('.table-responsive').style.display = 'block';
    }
}

// Helper function to highlight search terms
function highlightText(row, searchTerm) {
    const cells = row.querySelectorAll('td:not(:last-child)'); // Don't highlight actions column
    
    cells.forEach(cell => {
        const originalText = cell.textContent;
        const lowerText = originalText.toLowerCase();
        
        if (lowerText.includes(searchTerm)) {
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            const highlightedText = originalText.replace(regex, '<span class="highlight">$1</span>');
            cell.innerHTML = highlightedText;
        }
    });
}

// Helper function to remove highlights
function removeHighlights(row) {
    const cells = row.querySelectorAll('td');
    
    cells.forEach(cell => {
        const spans = cell.querySelectorAll('.highlight');
        spans.forEach(span => {
            cell.innerHTML = cell.innerHTML.replace(/<span class="highlight">(.*?)<\/span>/g, '$1');
        });
    });
}

// Clear all filters
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('roleFilter').value = '';
    filterUsersTable();
}

// Initialize search functionality
function initSearch() {
    // Add event listeners
    document.getElementById('searchInput').addEventListener('input', filterUsersTable);
    document.getElementById('roleFilter').addEventListener('change', filterUsersTable);
    document.getElementById('clearSearch').addEventListener('click', clearFilters);
    
    // Initialize the filter
    filterUsersTable();
}

// Call initSearch when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // ... your existing code ...
    
    // Initialize search
    initSearch();
    
    // ... rest of your existing code ...
});
// Print All Users Function
function printAllUsers() {
    // Create a print-friendly version of the table
    const printContent = document.createElement('div');
    
    // Add title and date
    const title = document.createElement('h1');
    title.textContent = 'Labify - Users List';
    title.style.textAlign = 'center';
    title.style.marginBottom = '10px';
    
    const date = document.createElement('p');
    date.textContent = 'Generated: ' + new Date().toLocaleDateString();
    date.style.textAlign = 'center';
    date.style.color = '#666';
    date.style.marginBottom = '20px';
    
    const count = document.createElement('p');
    count.textContent = 'Total Users: ' + <?= count($users) ?>;
    count.style.textAlign = 'center';
    count.style.marginBottom = '30px';
    count.style.fontWeight = 'bold';
    
    // Clone the table (remove action buttons for cleaner print)
    const table = document.querySelector('#usersTable').cloneNode(true);
    
    // Remove the Actions column for print
    table.querySelectorAll('th:last-child, td:last-child').forEach(el => el.remove());
    
    // Apply print styles
    table.style.width = '100%';
    table.style.borderCollapse = 'collapse';
    table.style.fontFamily = 'Arial, sans-serif';
    
    // Add borders for print
    table.querySelectorAll('th, td').forEach(cell => {
        cell.style.border = '1px solid #ddd';
        cell.style.padding = '8px';
    });
    
    // Style header
    table.querySelectorAll('th').forEach(th => {
        th.style.backgroundColor = '#f2f2f2';
        th.style.fontWeight = 'bold';
    });
    
    // Alternate row colors for readability
    table.querySelectorAll('tbody tr:nth-child(even)').forEach(row => {
        row.style.backgroundColor = '#f9f9f9';
    });
    
    // Build the print document
    printContent.appendChild(title);
    printContent.appendChild(date);
    printContent.appendChild(count);
    printContent.appendChild(table);
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Labify Users List</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                @media print {
                    body { margin: 0; }
                    @page { margin: 20mm; }
                }
                h1 { color: #2c3e50; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #f2f2f2; color: #333; }
                .footer {
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            ${printContent.innerHTML}
            <div class="footer">
                Generated by Labify Admin Panel • Page ${printWindow.document.body.innerHTML.includes('</table>') ? 1 : 1}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Print after content loads
    printWindow.onload = function() {
        printWindow.print();
        // Optionally close the window after printing
        // printWindow.close();
    };
}

// Initialize the button
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('printAllUsers')?.addEventListener('click', printAllUsers);
});


// Print All Users Function
function printAllUsers() {
    // Create a print-friendly version of the table
    const printContent = document.createElement('div');
    
    // Add title and date
    const title = document.createElement('h1');
    title.textContent = 'Labify - Users List';
    title.style.textAlign = 'center';
    title.style.marginBottom = '10px';
    
    const date = document.createElement('p');
    date.textContent = 'Generated: ' + new Date().toLocaleDateString();
    date.style.textAlign = 'center';
    date.style.color = '#666';
    date.style.marginBottom = '20px';
    
    const count = document.createElement('p');
    count.textContent = 'Total Users: ' + <?= count($users) ?>;
    count.style.textAlign = 'center';
    count.style.marginBottom = '30px';
    count.style.fontWeight = 'bold';
    
    // Clone the table (remove action buttons for cleaner print)
    const table = document.querySelector('#usersTable').cloneNode(true);
    
    // Remove the Actions column for print
    table.querySelectorAll('th:last-child, td:last-child').forEach(el => el.remove());
    
    // Apply print styles
    table.style.width = '100%';
    table.style.borderCollapse = 'collapse';
    table.style.fontFamily = 'Arial, sans-serif';
    
    // Add borders for print
    table.querySelectorAll('th, td').forEach(cell => {
        cell.style.border = '1px solid #ddd';
        cell.style.padding = '8px';
    });
    
    // Style header
    table.querySelectorAll('th').forEach(th => {
        th.style.backgroundColor = '#f2f2f2';
        th.style.fontWeight = 'bold';
    });
    
    // Alternate row colors for readability
    table.querySelectorAll('tbody tr:nth-child(even)').forEach(row => {
        row.style.backgroundColor = '#f9f9f9';
    });
    
    // Build the print document
    printContent.appendChild(title);
    printContent.appendChild(date);
    printContent.appendChild(count);
    printContent.appendChild(table);
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Labify Users List</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                @media print {
                    body { margin: 0; }
                    @page { margin: 20mm; }
                }
                h1 { color: #2c3e50; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #f2f2f2; color: #333; }
                .footer {
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            ${printContent.innerHTML}
            <div class="footer">
                Generated by Labify Admin Panel • Page ${printWindow.document.body.innerHTML.includes('</table>') ? 1 : 1}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Print after content loads
    printWindow.onload = function() {
        printWindow.print();
        // Optionally close the window after printing
        // printWindow.close();
    };
}

// Initialize the button
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('printAllUsers')?.addEventListener('click', printAllUsers);
});

// Export to CSV Function
function exportToCSV() {
    // Get all users data from PHP
    const users = <?= json_encode($users) ?>;
    
    // Define CSV headers
    let csv = 'ID,Name,Email,Role,Member Since\n';
    
    // Add user data
    users.forEach(user => {
        csv += `${user.id},"${user.full_name}","${user.email}",${user.role},"${user.created_at}"\n`;
    });
    
    // Create and trigger download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `labify_users_${new Date().toISOString().slice(0,10)}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize CSV export button
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('exportCSV')?.addEventListener('click', exportToCSV);
});
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
</button>  </body>
</html>