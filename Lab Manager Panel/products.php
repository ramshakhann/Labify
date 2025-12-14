<?php include "../auth.php"; ?>


<?php
  $host = 'localhost';
$dbname = 'labify';
$username = 'root';
$password = '';
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

$user_id = $_SESSION['user_id'] ?? null;


  require_once 'includes/db_connect.php';

require_once 'includes/Notification.php';
$notification = new Notification($conn);



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

require_once 'includes/db_connect.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $product_id = trim($_POST["product_id"]);
    $product_type = trim($_POST["product_type"]);
    $revision = trim($_POST["revision"]);
    $created_at = $_POST['created_at'];

    // Validate Product ID
    if (empty($product_id) || !preg_match('/^[A-Za-z0-9]{10}$/', $product_id)) {
        $response['message'] = "Product ID must be exactly 10 letters or digits.";
        echo json_encode($response);
        exit;
    }

    // Validate other fields
    if (empty($product_type)) {
        $response['message'] = "Please select a Product Type.";
        echo json_encode($response);
        exit;
    }
    if (empty($revision)) {
        $response['message'] = "Please select a Revision.";
        echo json_encode($response);
        exit;
    }
    if (empty($created_at)) {
        $response['message'] = "Please select a Date.";
        echo json_encode($response);
        exit;
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO products (product_id, product_type, revision, created_at) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $product_id, $product_type, $revision, $created_at);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Product added successfully!";
        } else {
            if ($conn->errno == 1062) {
                $response['message'] = "Duplicate Product ID. This product already exists.";
            } else {
                $response['message'] = "Oops! Something went wrong: " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $response['message'] = "Database error: could not prepare statement";
    }


    echo json_encode($response);
    exit;
}
?>




<!-- Your HTML for the products page goes here -->
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// Check for and display success or error messages from add_product.php
if (isset($_SESSION['success_message'])) {
    echo '<script>alert("' . $_SESSION['success_message'] . '");</script>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<script>alert("' . $_SESSION['error_message'] . '");</script>';
    unset($_SESSION['error_message']);
}




// Include the database connection file
require_once 'includes/db_connect.php';

// --- Fetch Product Data from Database ---
$sql = "SELECT 
    p.product_id, 
    p.product_type,
    p.revision,
    p.created_at AS product_created_at,
    tr.test_id, 
    tt.test_name AS test_type, 
    tr.result, 
    tr.tested_by,
    tr.status, 
    tr.test_date,
    tr.remarks
FROM products p
LEFT JOIN test_results tr ON p.product_id = tr.product_id
LEFT JOIN test_types tt ON tr.test_type_id = tt.id
ORDER BY p.product_id, tr.test_date DESC";



 $result = $conn->query($sql);

 $products_data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products_data[$row['product_id']][] = $row;
    }
}





 
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products | Labify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>
    <link rel="stylesheet" href="./assets/product_style.css">



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
                    <!-- Mobile Menu Button -->
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="desktop-nav">
                    <ul>
                    <li><a href="dashboard.php" data-tab="dashboard">Dashboard</a></li>
                    <li><a href="products.php" class="active" >Products</a></li>
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
        <li><a href="dashboard.php"  data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="products.php" class="active" data-tab="products"><i class="fas fa-cubes"></i>Products</a></li>
        <li><a href="log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> Testing</a></li>
        <li><a href="searching.php" data-tab="search"><i class="fas fa-search"></i> Search</a></li>
        <li><a href="reports.php" data-tab="reports"><i class="fas fa-chart-bar"></i>Reports</a></li>
        
    </ul>
    
    <!-- Mobile User Actions -->
    <div class="mobile-nav-actions">
        
    <button class="btn btn-primary" onclick="window.location.href='http://localhost/LABIFY/logout.php'">
        LOG OUT
    </button>
</div>    </div>
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
            <h2>Product Management</h2>
            <p>Comprehensive view of all products with advanced search and filtering capabilities</p>
        </div>
    </section>

    <!-- All Products Section -->
      <section class="dashboard">
        <div class="container">
            <div class="form-container">
                <h2 class="section-title">All Products</h2>
                
               <div class="search-filter-container">
    <div class="search-box">
        <h3>Search Products</h3>
                        <input type="text" id="productSearch" class="form-control" placeholder="Enter Product ID, Type, or Status">
                    </div>
                    <div class="form-group">
                        <label for="productFilter">Filter by Status</label>
                        <select id="productFilter" class="form-control">
                            <option value="all">All Status</option>
                            <option value="pass">Passed</option>
                            <option value="fail">Failed</option>
                            <option value="inconclusive">inconclusive</option>
                        </select>
                    </div>
                      
                </div>
                
               
</div>
                <div class="search-results" style="margin-top: 2rem;">
                    <table class="sample-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Type</th>
                                <th>Revision</th>
                                <th> Test Type</th>
                                <th> Result</th>
                                <th>Tested By</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                            
                        </thead>
                       <tbody id="productsResultsBody">
</div> 





 <!-- ADD PRODUCT BUTTON -->
                <div class="add-product-btn-container">
                    <button type="button" class="btn btn-primary" id="openAddProductModalBtn">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>

    <?php if (!empty($products_data)): ?>
        <?php foreach ($products_data as $product_id => $tests): ?>
            <?php
            $latest_test = $tests[0];
            $product_type = $latest_test['product_type'] ?? 'N/A';
            $revision = $latest_test['revision'] ?? 'N/A';
            $test_id = $latest_test['test_id'] ?? 'No Tests';
            $test_type = $latest_test['test_type'] ?? 'N/A';
            $result = $latest_test['result'] ?? 'N/A';
            $tested_by = $latest_test['tested_by'] ?? 'N/A';
            $test_date = $latest_test['product_created_at'] ?? 'N/A';
            $status_class = '';
            if (strtolower($result) === 'pass') $status_class = 'status-completed';
            elseif (strtolower($result) === 'fail') $status_class = 'status-pending';
            elseif (strtolower($result) === 'inconclusive') $status_class = 'status-processing';
            $json_tests = json_encode($tests);
            ?>
            <tr data-product-id="<?php echo htmlspecialchars($product_id); ?>" data-tests='<?php echo $json_tests; ?>'>
                <td data-label="Product ID"><?php echo htmlspecialchars($product_id); ?></td>
                <td data-label="Product Type"><?php echo htmlspecialchars($product_type); ?></td>
                <td data-label="Revision"><?php echo htmlspecialchars($revision); ?></td> <!-- FIXED: This now shows Revision -->
                <td data-label="Test Type"><?php echo htmlspecialchars($test_type); ?></td>
                <td data-label="Result"><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($result)); ?></span></td>
                <td data-label="Tested By"><?php echo htmlspecialchars($tested_by); ?></td>
                <td data-label="Date"><?php echo htmlspecialchars($test_date); ?></td>
                <td data-label="Actions" class="actions-cell">
                    <button class="action-btn btn-view"><i class="fas fa-eye"></i></button>
                    <button class="action-btn btn-edit"><i class="fas fa-edit"></i></button>
                    <button class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="9" style="text-align: center; color: var(--gray);">No products found in the database.</td>
        </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

   
 <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Product Test History</h2>
            <div id="modalContent">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
    </div>

   <!-- Edit Test Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-edit">&times;</span>
<h2>Edit Product: <span id="editProductHeading"></span></h2>
        <form id="editForm">
            <input type="hidden" id="editTestId" name="test_id">
            <input type="hidden" id="editProductId" name="product_id"> 

             <div class="form-group">
                <label for="editType">Product Type</label>
                <select name="type" id="editType" required >
                <option value="">Select Product Type</option>
                <option value="Switch Gear">Switch Gear</option>
                <option value="Fuse">Fuse</option>
                <option value="Capacitor">Capacitor</option>
                <option value="Resistor">Resistor</option>
                <option value="Transformer">Transformer</option>
                <option value="Relay">Relay</option>
</select>
            </div>
             <div class="form-group">
                <label for="editRevision">Revision</label>
                <input type="text" name="revision" id="editRevision">
            </div>
            <div class="form-group">
                <label for="editResult">Test Result</label>
                <select id="editResult" name="result" required>
                    <option value="Pass">Pass</option>
                    <option value="Fail">Fail</option>
                    <option value="Inconclusive">Inconclusive</option>
                </select>
            </div>
            <div class="form-group">
                <label for="editRemarks">Remarks</label>
                <textarea id="editRemarks" name="remarks" rows="4"></textarea>
            </div>
             <div class="form-group">
                <label for="editManufacture">Manufacture Date</label>
                <input type="datetime-local" name="created_at" id="editManufacture">

            </div>
             <div class="form-group">
                <label for="editStatus">Status</label>
                <select id="editStatus" name="status" required>
                    <option value="Pending">Pending</option>
                    <option value="Sent to CPRI">Sent to CPRI</option>
                    <option value="Sent for Re-making">Sent for Re-making</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="edit_product">Save Changes</button>
            </div>
        </form>
    </div>
</div>




        <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
    <div class="modal-content">
        <span class="close-add-product">&times;</span>
        <h2>Add New Product</h2>
        <form id="addProductForm">
            <div class="form-group">
                <label for="new_product_id">Product ID (10 digits)</label>
<input type="text" 
       id="new_product_id" 
       name="product_id" 
       required 
       >
            </div>
           <div class="form-group">
    <label for="new_product_type">Product Type</label>
    <select id="new_product_type" name="product_type" required>
        <option value="">Select Product Type</option>
        <option value="Switch Gear">Switch Gear</option>
        <option value="Fuse">Fuse</option>
        <option value="Capacitor">Capacitor</option>
        <option value="Resistor">Resistor</option>
        <option value="Transformer">Transformer</option>
        <option value="Relay">Relay</option>
    </select>
</div>

            <div class="form-group">
                <label for="new_revision">Revision</label>
                <input type="text" id="new_revision" name="revision" required>
            </div>
            <div class="form-group">
    <label for="new_manufacture">Manufacture Date</label>
    <input type="datetime-local" id="new_manufacture" name="created_at" required>
</div>
           
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Product</button>
            </div>
        </form>
    </div>
</div>







    <!-- Footer -->
    <?php include('./shared1/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            const manufactureInput = document.getElementById('editManufacture');
            const addManufactureInput = document.getElementById('new_manufacture');

const now = new Date();
const localNow = now.toISOString().slice(0,16); // Format YYYY-MM-DDTHH:MM
manufactureInput.max = localNow;
            // Enhanced date restriction with user feedback
document.addEventListener('DOMContentLoaded', function() {
    function restrictFutureDates() {
        const today = new Date().toISOString().split('T')[0];
        const now = new Date().toISOString().slice(0, 16);
        
        // Restrict date inputs
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            input.setAttribute('max', today);
            
            // Add validation feedback
            input.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const todayObj = new Date();
                todayObj.setHours(23, 59, 59, 999); // End of today
                
                if (selectedDate > todayObj) {
                    alert('Please select a date today or in the past.');
                    this.value = ''; // Clear the invalid selection
                    this.focus();
                }
            });
        });
        
      
    }
    
    // Initialize date restrictions
    restrictFutureDates();
    
    // Re-initialize when modals are opened (in case they're dynamically added)
    document.getElementById('openAddProductModalBtn')?.addEventListener('click', restrictFutureDates);
});
            mobileMenuBtn.addEventListener('click', function() {
                mobileNav.classList.add('active');
                mobileNavOverlay.classList.add('active');
            });
            
            closeMobileMenu.addEventListener('click', function() {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
            });
            
            mobileNavOverlay.addEventListener('click', function() {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
            });

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

          // Search functionality
const productSearch = document.getElementById('productSearch');
const productFilter = document.getElementById('productFilter');
const tableBody = document.getElementById('productsResultsBody');
const originalRows = Array.from(tableBody.querySelectorAll('tr'));

function filterProducts() {
    const searchTerm = productSearch.value.toLowerCase();
    const filterStatus = productFilter.value;

    // If search is empty, show all rows that match the status filter
    // This is more efficient than re-cloning every time
    originalRows.forEach(row => {
        let showRow = true;

        // --- SEARCH LOGIC ---
        if (searchTerm) {
            const cells = row.querySelectorAll('td');
            const productId = cells[0].textContent.toLowerCase();
            const productType = cells[1].textContent.toLowerCase();
            const statusBadge = cells[4].querySelector('.status-badge');
            const status = statusBadge ? statusBadge.textContent.toLowerCase() : '';

            showRow = productId.includes(searchTerm) || 
                      productType.includes(searchTerm) || 
                      status.includes(searchTerm);
        }

        // --- STATUS FILTER LOGIC ---
        if (showRow && filterStatus !== 'all') {
            const statusBadge = row.querySelector('.status-badge');
            const status = statusBadge ? statusBadge.textContent.toLowerCase() : '';
            
            // Corrected and simplified status mapping
            switch(filterStatus) {
                case 'pass':
                    showRow = status.includes('pass');
                    break;
                case 'fail':
                    showRow = status.includes('fail');
                    break;
                case 'inconclusive':
                    showRow = status.includes('inconclusive');
                    break;
            }
        }

        // --- TOGGLE ROW VISIBILITY ---
        row.style.display = showRow ? '' : 'none';
    });
}

// Event listeners for search
productSearch.addEventListener('input', filterProducts);
productFilter.addEventListener('change', filterProducts);

            // Delete button functionality
             tableBody.addEventListener('click', function(e) {
                if (e.target.closest('.btn-delete')) {
                    const row = e.target.closest('tr');
                    const productId = row.querySelector('td:first-child').textContent;
                    
                    if (confirm(`Are you sure you want to delete product ${productId}?`)) {
                        fetch('delete_product.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `product_id=${encodeURIComponent(productId)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                row.remove(); // Remove row from table on success
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting.');
                        });
                    }
                }
            });

            




                        // --- MODAL FUNCTIONALITY ---
            // --- MODAL OPEN / CLOSE ---
const viewModal = document.getElementById("viewModal");
const editModal = document.getElementById("editModal");
const addProductModal = document.getElementById("addProductModal");

const closeView = document.querySelector(".close");
const closeEdit = document.querySelector(".close-edit");
const closeAdd = document.querySelector(".close-add-product");

// Open Add Product modal
document.getElementById('openAddProductModalBtn').addEventListener('click', function() {
    addProductModal.style.display = "block";
});

// Close modals via X button
closeView?.addEventListener('click', () => viewModal.style.display = "none");
closeEdit?.addEventListener('click', () => editModal.style.display = "none");
closeAdd?.addEventListener('click', () => addProductModal.style.display = "none");

// Close modals by clicking outside
window.addEventListener('click', function(event) {
    if (event.target == viewModal) viewModal.style.display = "none";
    if (event.target == editModal) editModal.style.display = "none";
    if (event.target == addProductModal) addProductModal.style.display = "none";
});

           // --- VIEW BUTTON ---
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-view')) {
        const row = e.target.closest('tr');
        const cells = row.querySelectorAll('td');
        
        // Get data from table cells
        const productId = cells[0].textContent;
        const productType = cells[1].textContent;
        const revision = cells[2].textContent;
        const testType = cells[3].textContent;
        const result = cells[4].querySelector('.status-badge')?.textContent || 'N/A';
        const testedBy = cells[5].textContent;
        const date = cells[6].textContent;
        
        // Create content using table data
        let content = `
            <div class="product-info">
                <h3 style="color: #64ffda; margin-bottom: 15px;">Product Details</h3>
                <table class="info-table">
                    <tr>
                        <th>Product ID:</th>
                        <td>${productId}</td>
                    </tr>
                    <tr>
                        <th>Product Type:</th>
                        <td>${productType}</td>
                    </tr>
                    <tr>
                        <th>Revision:</th>
                        <td>${revision}</td>
                    </tr>
                    <tr>
                        <th>Latest Test Type:</th>
                        <td>${testType}</td>
                    </tr>
                    <tr>
                        <th>Result:</th>
                        <td><span class="status-badge ${getStatusClass(result)}">${result}</span></td>
                    </tr>
                    <tr>
                        <th>Tested By:</th>
                        <td>${testedBy}</td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td>${date}</td>
                    </tr>
                </table>
            </div>
        `;
        
        // Check if there are additional tests in data-tests
        const tests = JSON.parse(row.dataset.tests || '[]');
        if (tests.length > 1) {
            content += `
                <div class="test-history" style="margin-top: 20px;">
                    <h4 style="color: #64ffda; margin-bottom: 10px;">Test History</h4>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Test ID</th>
                                <th>Test Type</th>
                                <th>Date</th>
                                <th>Result</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            tests.forEach(test => {
                content += `
                    <tr>
                        <td>${test.test_id || 'N/A'}</td>
                        <td>${test.test_type || 'N/A'}</td>
                        <td>${test.product_created_at || 'N/A'}</td>
                        <td><span class="status-badge ${getStatusClass(test.result)}">${test.result || 'N/A'}</span></td>
                        <td>${test.status || 'N/A'}</td>
                        <td>${test.remarks || 'N/A'}</td>
                    </tr>
                `;
            });
            
            content += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        document.getElementById('modalContent').innerHTML = content;
        viewModal.style.display = "block";
    }
});

// Helper function for status badge classes
function getStatusClass(status) {
    if (!status) return '';
    status = status.toLowerCase();
    if (status.includes('pass')) return 'status-completed';
    if (status.includes('fail')) return 'status-pending';
    if (status.includes('inconclusive') || status.includes('processing')) return 'status-processing';
    return '';
}

            // --- EDIT BUTTON ---
            document.getElementById('editForm').addEventListener('submit', function(e) {
    const selectedDate = new Date(manufactureInput.value);
    const now = new Date();
    if (selectedDate > now) {
        e.preventDefault();
        alert("Manufacture date cannot be in the future!");
        return false;
    }
});

// --- EDIT BUTTON ---
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-edit')) {
        const row = e.target.closest('tr');
        const cells = row.querySelectorAll('td');
        
        // Get data directly from the table cells for THIS row
        const productId = cells[0].textContent;
        const productType = cells[1].textContent;
        const revision = cells[2].textContent;
        const testType = cells[3].textContent;
        const result = cells[4].querySelector('.status-badge')?.textContent || 'N/A';
        const dateCell = cells[6].textContent;
        
        // Also get test_id from the tests array if available
        const tests = JSON.parse(row.dataset.tests || '[]');
        const latestTest = tests.length > 0 ? tests[0] : null;
        
        console.log('Editing row with data:', {
            productId,
            productType,
            revision,
            testType,
            result,
            dateCell,
            testId: latestTest?.test_id,
            testsLength: tests.length
        });
        
        // Format date for datetime-local input
        let formattedDate = '';
        if (dateCell && dateCell !== 'N/A') {
            try {
                const dateObj = new Date(dateCell);
                formattedDate = dateObj.toISOString().slice(0, 16);
            } catch (e) {
                console.error('Date parsing error:', e);
                formattedDate = '';
            }
        }
        
        // Populate the form
        if (latestTest && latestTest.test_id) {
            document.getElementById('editTestId').value = latestTest.test_id;
        } else {
            // If no test_id, we need to handle product editing differently
            alert('This product has no tests yet. Adding product editing feature...');
            // We'll handle product editing without test_id
            document.getElementById('editTestId').value = 'product_only';
        }
                document.getElementById('editProductHeading').textContent = productId;

document.getElementById('editProductId').value = productId;
        document.getElementById('editType').value = productType;
        document.getElementById('editRevision').value = revision;
        document.getElementById('editManufacture').value = formattedDate;
        
        // Only set result and status if we have a test
        if (latestTest) {
            document.getElementById('editResult').value = latestTest.result || result;
            document.getElementById('editStatus').value = latestTest.status || 'Pending';
            document.getElementById('editRemarks').value = latestTest.remarks || '';
        } else {
            document.getElementById('editResult').value = 'inconclusive';
            document.getElementById('editStatus').value = 'Pending';
            document.getElementById('editRemarks').value = '';
        }
        
        editModal.style.display = "block";
    }
});

            // --- EDIT FORM SUBMISSION ---
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('update_product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    alert(data); // Show success/error message
                    editModal.style.display = "none";
                    location.reload(); // Reload page to see changes
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the test.');
                });
            });

        }); 

                    document.getElementById('openAddProductModalBtn').addEventListener('click', function() {
                addProductModal.style.display = "block";
            });

           // --- ADD PRODUCT FORM VALIDATION & AJAX ---
const addProductForm = document.getElementById('addProductForm');
const addProductModal = document.getElementById('addProductModal');
const addManufactureInput = document.getElementById('new_manufacture');

// Restrict future dates for manufacture date
const now = new Date();
addManufactureInput.max = now.toISOString().slice(0,16); // YYYY-MM-DDTHH:mm

addProductForm.addEventListener('submit', function(e) {
    e.preventDefault(); // prevent default submit

    let valid = true;

    // Reset previous borders
    this.querySelectorAll('input, select').forEach(input => input.style.border = "");

    // Validation: check for empty fields
    this.querySelectorAll('input, select').forEach(input => {
        if (!input.value) {
            valid = false;
            input.style.border = "2px solid red"; // red border for empty
        }
    });

    // Stop if validation failed
    if (!valid) return;

    // Prepare FormData
    const formData = new FormData(this);

    // AJAX submission
fetch('add_product.php', {
            method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            addProductModal.style.display = "none";
            this.reset(); // clear form

            // Add new row to table dynamically
            const tableBody = document.getElementById('productsResultsBody');
            const newRowHtml = `
                <tr data-product-id="${data.product.product_id}" data-tests='[]'>
                    <td data-label="Product ID">${data.product.product_id}</td>
                    <td data-label="Product Type">${data.product.product_type}</td>
                    <td data-label="Revision">${data.product.revision}</td>
                    <td data-label="Latest Test Type">N/A</td>
                    <td data-label="Latest Result"><span class="status-badge">N/A</span></td>
                    <td data-label="Tested By">N/A</td>
                    <td data-label="Date">${data.product.created_at}</td>
                    <td data-label="Actions" class="actions-cell">
                        <button class="action-btn btn-view"><i class="fas fa-eye"></i></button>
                        <button class="action-btn btn-edit"><i class="fas fa-edit"></i></button>
                        <button class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            tableBody.insertAdjacentHTML('afterbegin', newRowHtml);

        } else {
            alert('Error: ' + data.message);
        }
    })
    
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
        

    });
 

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
</button>  
</body>
</html>