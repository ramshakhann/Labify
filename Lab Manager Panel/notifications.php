<?php
session_start();
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// Get all notifications
$query = "SELECT * FROM notifications 
          WHERE user_id = ? 
          ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}


?>
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
    <title>Notifications - Labify</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
        <link rel="stylesheet" href="./assets/style.css">

   <style>
    
    /* Notification */
  .notification {
      position: fixed;
      top: 80px;
      right: 20px;
      background: transparent;
      backdrop-filter: blur(10px);
      border-radius: var(--radius-lg);
      padding: 1rem;
      box-shadow: 0 0 20px rgba(6, 154, 195, 0.8);
      border-left: 4px solid var(--accent);
      max-width: 300px;
      z-index: 1000;
      animation: slideInRight 0.5s ease;
  }

  .notification-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
  }

  .notification-title {
      font-weight: 600;
      color: var(--white);
  }

  .notification-close {
      background: none;
      border: none;
      color: var(--gray);
      cursor: pointer;
  }

  .notification-body {
      color: var(--gray-light);
      font-size: 0.875rem;
  }                  /* User actions styling */
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
     body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #0f172a;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Particles container - fixed position covering entire screen */
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
        }
        .status-badge {
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    font-size: 0.8em;
}

.status-pass {
    color: #10b981; /* Green */
    background-color: rgba(16, 185, 129, 0.1);
}

.status-fail {
    color: #ef4444; /* Red */
    background-color: rgba(239, 68, 68, 0.1);
}

.status-pending {
    color: #f59e0b; /* Orange/Amber */
    background-color: rgba(245, 158, 11, 0.1);
}
        
        /* Make content appear above particles */
        .notifications-container {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .notification-card {
    background: linear-gradient(135deg, rgba(1, 8, 57, 0.61), rgba(0, 95, 127, 0.48));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(5, 76, 191, 0.5);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .notification-card.unread {
    background: linear-gradient(135deg, rgba(10, 51, 43, 0.78), rgba(3, 199, 176, 0.63));
            border-left: 4px solid #3bcef6ff;
            box-shadow: 0 0 15px rgba(20, 152, 169, 0.54);
        }
        
        .notification-card.read {
            opacity: 0.8;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .notification-title {
            font-weight: bold;
            color: white;
        }
        
        .notification-time {
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        .notification-message {
            color: #d1d5db;
            margin-bottom: 0.5rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-mark-read {
            background: #0b9999ff;
            border: 5px solid rgba(6, 92, 111, 0.5);
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
         .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            color: var(--primary);
            box-shadow: 0 4px 15px rgba(100, 255, 218, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(100, 255, 218, 0.5);
        }
        
        .btn-mark-read:hover {
            background: #026457ff;
            border: 1px solid rgba(5, 76, 191, 0.5);

            transform: translateY(-1px);
        }
        
.notification-icon .fa-check-circle {
    color: #10b981;
}

.notification-icon .fa-exclamation-triangle {
    color: #f59e0b;
}

.notification-icon .fa-info-circle {
    color: #3b82f6;
}

.notification-content {
    flex: 1;
}

.notification-content strong {
    display: block;
    color: white;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.notification-content p {
    margin: 0;
    color: #d1d5db;
    font-size: 0.85rem;
    line-height: 1.4;
}

.notification-content small {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-top: 5px;
    display: block;
}

.notification-empty {
    padding: 30px;
    text-align: center;
    color: #9ca3af;
}

.notification-empty i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

       
        
        .notification-empty {
            text-align: center;
            padding: 4rem 1rem;
            background: rgba(31, 41, 55, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            border: 1px solid rgba(55, 65, 81, 0.5);
        }
        
        .notification-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #4b5563;
        }
        
        .notification-empty h3 {
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .notification-empty p {
            color: #9ca3af;
        }
        
        /* Navbar should be above particles */
        nav {
            position: relative;
            z-index: 100;
        }
   </style>
</head>
<body>
     <!-- Particles Background -->
    <div id="particles-js"></div>
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
        <li><a href="./dashboard.php">Dashboard</a></li>
        <li><a href="./products.php">Products</a></li>
        <li><a href="./log_test.php">Testing</a></li>
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
        <li><a href="../dashboard.php" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="../products.php" data-tab="products"><i class="fas fa-cubes"></i> All Products</a></li>
        <li><a href="../log_test.php" data-tab="new-test"><i class="fas fa-vial"></i> New Test</a></li>
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
    <div class="notifications-container">
        <h1>Notifications</h1>
        
        <div class="notification-actions-global" style="margin-bottom: 1rem;">
            <button onclick="markAllAsRead()" class="btn btn-primary">
                Mark All as Read
            </button>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <i class="far fa-bell-slash" style="font-size: 3rem; color: #4b5563;"></i>
                <h3>No notifications</h3>
                <p>You're all caught up!</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                     id="notification-<?php echo $notification['id']; ?>">
                    <div class="notification-header">
                        <div class="notification-title" style="color:  rgba(57, 140, 255, 1);">
                            <?php echo htmlspecialchars($notification['title']); ?>
                        </div>
                        <div class="notification-time" style="color:  rgba(57, 225, 255, 1);">
                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                        </div>
                    </div>
                    <div class="notification-message" style="color:  rgba(212, 210, 210, 1);">
                        <?php echo htmlspecialchars($notification['message']); ?>
                    </div>
                    <?php if (!$notification['is_read']): ?>
                        <div class="notification-actions">
                            <button class="btn-mark-read" 
                                    onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                Mark as Read
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeParticles();
            initializeNotifications();
        });
        
        // Particles Background
        function initializeParticles() {
            if (typeof particlesJS !== 'undefined') {
                particlesJS("particles-js", {
                    particles: {
                        number: { 
                            value: 80, 
                            density: { 
                                enable: true, 
                                value_area: 800 
                            } 
                        },
                        color: { 
                            value: "#3b82f6" 
                        },
                        shape: { 
                            type: "circle",
                            stroke: {
                                width: 0,
                                color: "#000000"
                            }
                        },
                        opacity: { 
                            value: 0.3, 
                            random: true,
                            anim: {
                                enable: true,
                                speed: 1,
                                opacity_min: 0.1,
                                sync: false
                            }
                        },
                        size: { 
                            value: 2, 
                            random: true,
                            anim: {
                                enable: true,
                                speed: 2,
                                size_min: 0.1,
                                sync: false
                            }
                        },
                        line_linked: {
                            enable: true,
                            distance: 100,
                            color: "#3b82f6",
                            opacity: 0.2,
                            width: 1
                        },
                        move: {
                            enable: true,
                            speed: 1,
                            direction: "none",
                            random: true,
                            straight: false,
                            out_mode: "out",
                            bounce: false,
                            attract: {
                                enable: false,
                                rotateX: 600,
                                rotateY: 1200
                            }
                        }
                    },
                    interactivity: {
                        detect_on: "canvas",
                        events: {
                            onhover: { 
                                enable: true, 
                                mode: "repulse" 
                            },
                            onclick: { 
                                enable: true, 
                                mode: "push" 
                            },
                            resize: true
                        },
                        modes: {
                            grab: {
                                distance: 400,
                                line_linked: {
                                    opacity: 1
                                }
                            },
                            bubble: {
                                distance: 400,
                                size: 40,
                                duration: 2,
                                opacity: 8,
                                speed: 3
                            },
                            repulse: {
                                distance: 100,
                                duration: 0.4
                            },
                            push: {
                                particles_nb: 4
                            },
                            remove: {
                                particles_nb: 2
                            }
                        }
                    },
                    retina_detect: true
                });
                
                
            }
        }
        
        // Notifications functionality
        function initializeNotifications() {
            // Add hover effects to notification cards
            document.querySelectorAll('.notification-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.3)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
        }
        
        // Mark single notification as read
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: notificationId})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const card = document.getElementById('notification-' + notificationId);
                    if (card) {
                        card.classList.remove('unread');
                        card.classList.add('read');
                        card.querySelector('.notification-actions')?.remove();
                        
                        // Add visual feedback
                        card.style.background = 'rgba(31, 41, 55, 0.8)';
                        card.style.borderLeft = '4px solid #4b5563';
                        
                        // Update navbar count if exists
                        updateNavbarNotificationCount();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to mark as read'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            }
        }
        
        // Mark all notifications as read
        async function markAllAsRead() {
            if (!confirm('Are you sure you want to mark all notifications as read?')) {
                return;
            }
            
            try {
                const response = await fetch('mark_all_notifications_read.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update all notification cards
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.classList.add('read');
                        card.querySelector('.notification-actions')?.remove();
                        
                        // Update styling
                        card.style.background = 'rgba(31, 41, 55, 0.8)';
                        card.style.borderLeft = '4px solid #4b5563';
                    });
                    
                    // Update navbar count
                    updateNavbarNotificationCount();
                    
                    // Show success message
                    showNotification('All notifications marked as read!', 'success');
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to mark all as read'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error. Please try again.', 'error');
            }
        }
        
        // Update navbar notification count
        function updateNavbarNotificationCount() {
            const badge = document.getElementById('notificationCount');
            if (badge) {
                badge.style.display = 'none';
            }
        }
        
        // Show notification toast
        function showNotification(message, type = 'success') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `notification-toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            // Style the toast
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 12px 20px;
                border-radius: 5px;
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 1000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            `;
            
            // Add to page
            document.body.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
            
            // Add CSS animation
            if (!document.querySelector('#toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }
    </script>
  <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Labify</h3>
                    <p>Advanced automation solutions for electrical product testing with real-time monitoring and CPRI integration.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                       <li><a href="dashboard.php" class="active" data-tab="dashboard">Dashboard</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="log_test.php" >Testing</a></li>
                    <li><a href="searching.php" >Search</a></li>
                    <li><a href="reports.php" >Reports</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="products.php">Product Testing</a></li>
                        <li><a href="log_test.php">Quality Assurance</a></li>
                        <li><a href="reports.php">CPRI Compliance</a></li>
                        <li><a href="">Consulting</a></li>
                        <li><a href="">Support</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2025 Labify. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>