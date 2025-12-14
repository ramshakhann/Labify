


<?php
session_start();

// Simple direct database connection
$host = 'localhost';
$dbname = 'labify';
$username = 'root';
$password = '';
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Fetch test types from database
try {
    $stmt = $conn->prepare("SELECT id, test_name FROM test_types ORDER BY test_name");
    $stmt->execute();
    $test_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching test types: " . $e->getMessage());
    $test_types = []; // Empty array as fallback
}
// Get user info from session
$user_id = $_SESSION['user_id'] ?? null;
$tested_by_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Guest User';

// Get user role
$user_role = 'User';
if ($user_id) {
    try {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && isset($user['role'])) {
            $user_role = $user['role'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user role: " . $e->getMessage());
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labify - Advanced Search</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/log_test_style.css">
    <link rel="stylesheet" href="./assets/product_style.css">
    
    <style>
        /* Additional custom styles */
        .dashboard-grid {
            display: block;
        }
        
        .card {
            margin-top: 1.5rem;
        }
        
        .search-form {
            position: sticky;
            top: 20px;
            z-index: 10;
        }
        
        .table-container {
            min-height: 200px;
            overflow-x: auto;
        }
        
        /* Status badges matching your style */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; border: 1px solid #fbbf24; }
        .status-in-progress { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid #3b82f6; }
        .status-completed { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid #22c55e; }
        .status-fail { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .status-pass { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid #22c55e; }
        .status-inconclusive { background: rgba(120, 113, 108, 0.2); color: #78716c; border: 1px solid #78716c; }
        .status-sent-to-cpri { background: rgba(99, 102, 241, 0.2); color: #6366f1; border: 1px solid #6366f1; }
        .status-sent-for-remake { background: rgba(251, 146, 60, 0.2); color: #f97316; border: 1px solid #f97316; }
        .status-scheduled { background: rgba(168, 85, 247, 0.2); color: #a855f7; border: 1px solid #a855f7; }
        
        /* Search loading states */
        .search-loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }
        
        .search-loading.active {
            display: block;
        }
        
        .search-loading i {
            font-size: 2rem;
            margin-bottom: 10px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .initial-state, .no-results {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }
        
        .initial-state i, .no-results i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .result-count {
            font-size: 0.9rem;
            color: var(--gray);
            margin-left: 10px;
            font-weight: normal;
        }
        
        /* Action buttons matching your style */
      
        /* User actions styling - from your products.php */
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
                        <li><a href="log_test.php" class="nav-link ">Testing</a></li>
                        <li><a href="products.php" class="nav-link ">Products</a></li>
                        <li><a href="searching.php" class="nav-link active" >Search</a></li>
                        <li><a href="reports.php" class="nav-link  ">Reports</a></li>
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
        <li><a href="log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> Testing</a></li>
        <li><a href="products.php"data-tab="products"><i class="fas fa-cubes"></i>Products</a></li>
        <li><a href="searching.php"  class="active" data-tab="search"><i class="fas fa-search"></i> Search</a></li>
        <li><a href="reports.php" data-tab="reports"><i class="fas fa-chart-bar"></i>Reports</a></li>
        
    </ul>
        
        <!-- Mobile User Actions -->
        <div class="mobile-nav-actions">
            <button class="btn btn-primary" onclick="window.location.href='logout.php'">
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
        </div>
        <div class="container">
            <h2>Advanced Search</h2>
            <p>Find any test record quickly using powerful filters.</p>
        </div>
    </section>

    <div class="container">
        <div class="dashboard-grid">
            <!-- Search Form Card -->
            <div class="card search-form">
                <div class="card-header">
                    <h3 class="card-title">Search Filters</h3>
                    <div class="card-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                </div>
                <form id="searchForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="test_id">Test ID</label>
                            <input type="text" id="test_id" name="test_id" placeholder="e.g., PRD1VLT123">
                        </div>
                        <div class="form-group">
                            <label for="product_id">Product ID</label>
                            <input type="text" id="product_id" name="product_id" placeholder="e.g., PRD-001">
                        </div>
                        <div class="form-group">
                            <label for="test_type_id">Test Type</label>
                            <select id="test_type_id" name="test_type_id">
                                <option value="">All Types</option>
                                
                                <?php foreach($test_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                        <?php echo htmlspecialchars($type['test_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="test_result">Test Result</label>
                            <select id="test_result" name="test_result">
                                <option value="">All Results</option>
                                <option value="Pass">Pass</option>
                                <option value="Fail">Fail</option>
                                <option value="Inconclusive">Inconclusive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Sent to CPRI">Sent to CPRI</option>
                                <option value="Sent for Remake">Sent for Remake</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tested_by">Tested By</label>
                            <input type="text" id="tested_by" name="tested_by" placeholder="e.g., Rameez Khan">
                        </div>
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from">
                        </div>
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="clearFilters" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Clear Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Search Results
                        <span id="resultCount" class="result-count"></span>
                    </h3>
                    <div class="card-icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
                
                <!-- Loading Indicator -->
                <div id="searchLoading" class="search-loading">
                    <i class="fas fa-spinner"></i>
                    <p>Searching records...</p>
                </div>
                
                <!-- Initial State -->
                <div id="initialState" class="initial-state">
                    <i class="fas fa-search"></i>
                    <h4>Ready to search</h4>
                    <p>Use the filters to find specific test records.</p>
                </div>
                
                <!-- No Results State -->
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="fas fa-search-minus"></i>
                    <h4>No results found</h4>
                    <p>Try adjusting your search filters.</p>
                </div>
                
                <!-- Results Table -->
                <div id="resultsContainer" class="table-container" style="display: none;">
                    <table id="resultsTable">
                        <thead>
                            <tr>
                                <th>Test ID</th>
                                <th>Product ID</th>
                                <th>Test Type</th>
                                <th>Test Date</th>
                                <th>Result</th>
                                <th>Status</th>
                                <th>Tester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                            <!-- Results will be inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (from your products.php style) -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Test Details</h2>
            <div id="modalContent">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-edit">&times;</span>
            <h2>Edit Test</h2>
            <form id="editForm">
                <input type="hidden" id="editTestId" name="test_id">
                <div class="form-group">
                    <label for="editResult">Test Result</label>
                    <select id="editResult" name="result" required>
                        <option value="Pass">Pass</option>
                        <option value="Fail">Fail</option>
                        <option value="Inconclusive">Inconclusive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Sent to CPRI">Sent to CPRI</option>
                        <option value="Sent for Remake">Sent for Remake</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editRemarks">Remarks</label>
                    <textarea id="editRemarks" name="remarks" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Initialize mobile menu
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            if (mobileMenuBtn && mobileNav) {
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
            }
            
            // Setup search form
            const searchForm = document.getElementById('searchForm');
            const searchInputs = searchForm.querySelectorAll('input, select');
            const clearButton = document.getElementById('clearFilters');
            
            // Auto-search on input change
            searchInputs.forEach(input => {
                input.addEventListener('change', performSearch);
                if (input.type === 'text' || input.type === 'date') {
                    input.addEventListener('input', debounce(performSearch, 500));
                }
            });
            
            // Clear filters
            clearButton.addEventListener('click', function() {
                searchForm.reset();
                performSearch();
            });
            
            // Initialize modals
            initializeModals();
            
            // Load initial data
            setTimeout(() => {
                performSearch();
            }, 100);
        });
        
        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    func.apply(context, args);
                }, wait);
            };
        }
        
        // Main search function
        function performSearch() {
            console.log('Starting search...');
            
            // Show loading state
            document.getElementById('searchLoading').classList.add('active');
            document.getElementById('initialState').style.display = 'none';
            document.getElementById('noResults').style.display = 'none';
            document.getElementById('resultsContainer').style.display = 'none';
            
            // Get form data
            const formData = new FormData(document.getElementById('searchForm'));
            const params = new URLSearchParams();
            
            // Add all form fields to URL params
            for (const [key, value] of formData.entries()) {
                if (value.trim() !== '') {
                    params.append(key, value);
                }
            }
            
            console.log('Search params:', params.toString());
            
            // Make AJAX request
            fetch('search_handler.php?' + params.toString())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    handleSearchResponse(data);
                })
                .catch(error => {
                    console.error('Search error:', error);
                    document.getElementById('searchLoading').classList.remove('active');
                    showNotification('error', 'Search Failed', 'Failed to connect to server');
                    showNoResults();
                });
        }
        
        // Handle search response
        function handleSearchResponse(data) {
            // Hide loading state
            document.getElementById('searchLoading').classList.remove('active');
            
            if (data.success) {
                if (data.data && data.data.length > 0) {
                    displayResults(data.data);
                    document.getElementById('resultCount').textContent = `(${data.count || data.data.length} found)`;
                } else {
                    showNoResults();
                }
            } else {
                showNotification('error', 'Search Error', data.message || 'Unknown error');
                showNoResults();
            }
        }
        
        // Display results in table (your style)
        function displayResults(results) {
            const tableBody = document.getElementById('resultsTableBody');
            tableBody.innerHTML = '';
            
            results.forEach(test => {
                const row = document.createElement('tr');
                
                // Determine status class
                const statusClass = test.status ? test.status.toLowerCase().replace(/ /g, '-') : 'pending';
                const resultClass = test.result ? test.result.toLowerCase() : 'pending';
                
                row.innerHTML = `
                    <td>${test.test_id || 'N/A'}</td>
                    <td>${test.product_id || 'N/A'}</td>
                    <td>${test.test_type || test.test_type_id || 'N/A'}</td>
                    <td>${test.test_date || 'N/A'}</td>
                    <td>
                        <span class="status-badge status-${resultClass}">
                            ${test.result || 'Pending'}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-${statusClass}">
                            ${test.status || 'Pending'}
                        </span>
                    </td>
                    <td>${test.tested_by || 'N/A'}</td>
                    <td>
                        <button type="button" class="btn-view" 
                                data-testid="${test.test_id}"
                                data-productid="${test.product_id}"
                                data-testtype="${test.test_type}"
                                data-testdate="${test.test_date}"
                                data-result="${test.result}"
                                data-status="${test.status}"
                                data-testedby="${test.tested_by}">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn-edit" 
                                data-testid="${test.test_id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-delete" 
                                data-testid="${test.test_id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                tableBody.appendChild(row);
            });
            
            document.getElementById('resultsContainer').style.display = 'block';
        }
        
        // Show no results
        function showNoResults() {
            document.getElementById('noResults').style.display = 'block';
            document.getElementById('resultCount').textContent = '(0 found)';
        }
        
        // Modal initialization (like your products.php)
        function initializeModals() {
            // Close buttons
            const closeView = document.querySelector(".close");
            const closeEdit = document.querySelector(".close-edit");
            const viewModal = document.getElementById("viewModal");
            const editModal = document.getElementById("editModal");
            
            closeView?.addEventListener('click', () => viewModal.style.display = "none");
            closeEdit?.addEventListener('click', () => editModal.style.display = "none");
            
            // Close modals by clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == viewModal) viewModal.style.display = "none";
                if (event.target == editModal) editModal.style.display = "none";
            });
            
            // View button click
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-view')) {
                    const btn = e.target.closest('.btn-view');
                    const testId = btn.getAttribute('data-testid');
                    const productId = btn.getAttribute('data-productid');
                    const testType = btn.getAttribute('data-testtype');
                    const testDate = btn.getAttribute('data-testdate');
                    const result = btn.getAttribute('data-result');
                    const status = btn.getAttribute('data-status');
                    const testedBy = btn.getAttribute('data-testedby');
                    
                    let content = `
                        <h3>Test Details</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;"><strong>Test ID:</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;">${testId}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;"><strong>Product ID:</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;">${productId}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;"><strong>Test Type:</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;">${testType}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;"><strong>Test Date:</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;">${testDate}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;"><strong>Result:</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;">
                                    <span class="status-badge status-${result.toLowerCase()}">${result}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;"><strong>Status:</strong></td>
                                <td style="padding: 10px; border-bottom: 1px solid #374151;">
                                    <span class="status-badge status-${status.toLowerCase().replace(/ /g, '-')}">${status}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px;"><strong>Tested By:</strong></td>
                                <td style="padding: 10px;">${testedBy}</td>
                            </tr>
                        </table>
                    `;
                    
                    document.getElementById('modalContent').innerHTML = content;
                    viewModal.style.display = "block";
                }
            });
            
            // Edit button click
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-edit')) {
                    const btn = e.target.closest('.btn-edit');
                    const testId = btn.getAttribute('data-testid');
                    
                    // Populate edit form
                    document.getElementById('editTestId').value = testId;
                    
                    // Find the row data
                    const row = btn.closest('tr');
                    const result = row.querySelector('.btn-view').getAttribute('data-result');
                    const status = row.querySelector('.btn-view').getAttribute('data-status');
                    
                    document.getElementById('editResult').value = result || 'Pass';
                    document.getElementById('editStatus').value = status || 'Pending';
                    
                    editModal.style.display = "block";
                }
            });
            
            // Edit form submission
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('update_test.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('success', 'Success', data.message);
                        editModal.style.display = "none";
                        performSearch(); // Refresh results
                    } else {
                        showNotification('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('error', 'Error', 'Failed to update test');
                });
            });
            
            // Delete button click
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-delete')) {
                    const btn = e.target.closest('.btn-delete');
                    const testId = btn.getAttribute('data-testid');
                    
                    if (confirm(`Are you sure you want to delete test ${testId}?`)) {
                        fetch('delete_test.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `test_id=${encodeURIComponent(testId)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('success', 'Success', data.message);
                                performSearch(); // Refresh results
                            } else {
                                showNotification('error', 'Error', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('error', 'Error', 'Failed to delete test');
                        });
                    }
                }
            });
        }
        
        // Notification system
        function showNotification(type, title, message) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            
            notification.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 5px;">${title}</div>
                <div>${message}</div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
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
    <?php 
include('shared1/footer.php');
?>
</body>
</html>