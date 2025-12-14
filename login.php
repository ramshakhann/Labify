<?php
session_start();
include "db.php";

// Check if there's an error message in the session and display it
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    // IMPORTANT: Unset the error message from the session so it doesn't show up again
    unset($_SESSION['error']);
}

$host = 'localhost';
$dbname = 'labify';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle the form submission
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // --- Use Prepared Statements to prevent SQL Injection ---
    // Modified query to check for is_active = 1
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Check if user is active
            if ($user['is_active'] == 0) {
                $_SESSION['error'] = "Your account has been deactivated. Please contact administrator.";
                header("Location: login.php");
                exit();
            }

            // Login successful - set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // RECORD THE LOGIN
            try {
                $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, username) VALUES (?, ?)");
                $stmt->execute([$user['id'], $user['full_name']]);
                
                // Also update last_login in users table
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            } catch (Exception $e) {
                error_log("Login tracking error: " . $e->getMessage());
            }

            // Remember Me functionality
            if ($remember) {
                // 1. Generate a secure random selector and validator
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));

                // 2. Hash the validator for database storage
                $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);

                // 3. Set the expiry date (e.g., 30 days from now)
                $expiry = date('Y-m-d H:i:s', time() + 86400 * 30);

                // 4. Insert the new token into the database
                $stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $user['id'], $selector, $hashedValidator, $expiry);
                $stmt->execute();

                // 5. Set TWO cookies on the user's browser
                // Both cookies should have the same expiry date
                $expiryTime = time() + 86400 * 30;
                setcookie("remember_selector", $selector, $expiryTime, "/", "", true, true); // Use secure and httponly flags
                setcookie("remember_validator", $validator, $expiryTime, "/", "", true, true);
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['full_name'];
            $_SESSION['role']    = $user['role'];

            // Remember Me Cookie (simple version for backward compatibility)
            if ($remember) {
                setcookie("labify_user_email", $user['email'], time() + (86400 * 30), "/"); // Expires in 30 days
            }

            // Role-based redirect
            if ($user['role'] == 'admin') {
                header("Location: Admin Panel/dashboard.php");
            } else if ($user['role'] == 'lab_manager') {
                header("Location: Lab Manager Panel/dashboard.php");
            } else {
                header("Location: Tester/log_test.php");
            }
            exit();

        } else {
            // Password is incorrect
            $_SESSION['error'] = "Invalid email or password.";
        }

    } else {
        // Email not found OR account is inactive
        // Check if user exists but is inactive
        $checkStmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 0 LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 1) {
            $_SESSION['error'] = "Your account has been deactivated. Please contact administrator.";
        } else {
            $_SESSION['error'] = "Invalid email or password.";
        }
        $checkStmt->close();
    }
    $stmt->close();

    // --- REDIRECT ---
    // After processing the POST, redirect back to the login page
    header("Location: login.php");
    exit(); // Always call exit after a redirect
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Labify - Login</title>
    <link rel="stylesheet" href="./Admin Panel/assets/login.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="container">
    <div class="left">    <div class="circuit-board" id="circuitBoard"></div>

        <h1>LABIFY</h1>
        <p>Lab Automation & Testing System</p>
        <p>Secure • Fast • Smart</p>
    </div>

    <div class="right">
        <h2>LOGIN</h2>

        <?php if (isset($error)): ?>
            <!-- Use htmlspecialchars to prevent XSS -->
            <div class="error"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action=""> 
            
            <span class="icon-wrapper">
        <svg width="24" height="24" viewBox="0 0 24 24">
        <g fill="none" stroke="#64ffda" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <path class="envelope-line" d="M22 6l-10 7L2 6"/>
        </g>
        <style>
            .envelope-line {
                stroke-dasharray: 30;
                stroke-dashoffset: 30;
                animation: draw 2s ease-in-out infinite;
            }
            @keyframes draw {
                0%, 100% { stroke-dashoffset: 30; }
                50% { stroke-dashoffset: 0; }
            }
            .icon-wrapper, .heading-text {
    display: inline-block;
    vertical-align: middle;
}
.heading-text {
    color: #64ffda;
    font-weight: bold;
    margin-left: 8px;
}
        </style>

        </svg>
    </span>
    <span class="heading-text">Username</span>
    <br>
            <input type="email" name="email" placeholder="Email" required >

               <span class="icon-wrapper">
        <svg width="24" height="24" viewBox="0 0 24 24">
            <g fill="none" stroke="#64ffda" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                <!-- Optional: Add animation to lock -->
                <path class="lock-line" d="M12 15v2"/>
            </g>
            <style>
                .lock-line {
                    stroke-dasharray: 5;
                    stroke-dashoffset: 5;
                    animation: lockDraw 1.5s ease-in-out infinite;
                }
                @keyframes lockDraw {
                    0%, 100% { stroke-dashoffset: 5; }
                    50% { stroke-dashoffset: 0; }
                }
            </style>
        </svg>
    </span>
    <span class="heading-text">Password</span>
    <br>
            <input type="password" name="password" placeholder="Password" required>

            <div class="options">
                <div class="remember">
                    <input type="checkbox" name="remember" id="remember_checkbox">
                    <label for="remember_checkbox">Remember me</label>
                </div>
            </div>

            <button type="submit" name="login">Login</button>
        </form>

        <div class="signup">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
    </div>
</div>
<script>
    // Function to create electrical lab animations
    document.addEventListener('DOMContentLoaded', function() {
        const leftSection = document.querySelector('.left');
        const circuitBoard = document.getElementById('circuitBoard');
        
        // Create circuit board lines
        createCircuitBoard();
        
        // Create moving electrons
        createElectrons(5);
        
        // Create electrical components
        createElectricalComponents();
        
        // Create floating lab equipment icons
        createLabEquipment();
        
        function createCircuitBoard() {
            // Horizontal lines
            for (let i = 0; i < 5; i++) {
                const line = document.createElement('div');
                line.className = 'circuit-line horizontal';
                line.style.top = `${20 + i * 20}%`;
                line.style.left = '0';
                circuitBoard.appendChild(line);
            }
            
            // Vertical lines
            for (let i = 0; i < 6; i++) {
                const line = document.createElement('div');
                line.className = 'circuit-line vertical';
                line.style.left = `${15 + i * 15}%`;
                line.style.top = '0';
                circuitBoard.appendChild(line);
            }
        }
        
        function createElectrons(count) {
            for (let i = 0; i < count; i++) {
                const electron = document.createElement('div');
                electron.className = 'electron';
                
                // Random starting position
                const startX = Math.random() * 80 + 10;
                const startY = Math.random() * 80 + 10;
                
                electron.style.left = `${startX}%`;
                electron.style.top = `${startY}%`;
                
                // Random movement
                const endX = (Math.random() * 60 - 30) + startX;
                const endY = (Math.random() * 60 - 30) + startY;
                
                electron.style.setProperty('--end-x', `${endX - startX}px`);
                electron.style.setProperty('--end-y', `${endY - startY}px`);
                
                // Random animation duration
                const duration = Math.random() * 5 + 3;
                electron.style.animation = `electronMove ${duration}s linear infinite alternate`;
                
                circuitBoard.appendChild(electron);
            }
        }
        
        function createElectricalComponents() {
            // Create resistors
            for (let i = 0; i < 3; i++) {
                const resistor = document.createElement('div');
                resistor.className = 'resistor';
                resistor.style.left = `${Math.random() * 70 + 10}%`;
                resistor.style.top = `${Math.random() * 70 + 10}%`;
                resistor.style.setProperty('--rotation', `${Math.random() * 90}deg`);
                resistor.style.animation = `float ${Math.random() * 4 + 3}s ease-in-out infinite`;
                circuitBoard.appendChild(resistor);
            }
            
            // Create capacitors
            for (let i = 0; i < 2; i++) {
                const capacitor = document.createElement('div');
                capacitor.className = 'capacitor';
                capacitor.style.left = `${Math.random() * 70 + 10}%`;
                capacitor.style.top = `${Math.random() * 70 + 10}%`;
                capacitor.style.animation = `rotateSlow ${Math.random() * 20 + 10}s linear infinite`;
                circuitBoard.appendChild(capacitor);
            }
            
            // Create LEDs
            for (let i = 0; i < 4; i++) {
                const led = document.createElement('div');
                led.className = 'led';
                led.style.left = `${Math.random() * 70 + 10}%`;
                led.style.top = `${Math.random() * 70 + 10}%`;
                circuitBoard.appendChild(led);
            }
            
            // Create voltage indicators
            for (let i = 0; i < 3; i++) {
                const voltage = document.createElement('div');
                voltage.className = 'voltage-text';
                voltage.textContent = `${Math.floor(Math.random() * 12) + 1}.${Math.floor(Math.random() * 9)}V`;
                voltage.style.left = `${Math.random() * 70 + 10}%`;
                voltage.style.top = `${Math.random() * 70 + 10}%`;
                voltage.style.animation = `float ${Math.random() * 5 + 3}s ease-in-out infinite`;
                circuitBoard.appendChild(voltage);
            }
        }
        
        function createLabEquipment() {
            const equipmentIcons = ['fa-bolt', 'fa-microchip', 'fa-satellite-dish', 'fa-wave-square', 'fa-tachometer-alt', 'fa-plug'];
            
            equipmentIcons.forEach((icon, index) => {
                const equipment = document.createElement('i');
                equipment.className = `fas ${icon} lab-equipment`;
                equipment.style.left = `${Math.random() * 70 + 10}%`;
                equipment.style.top = `${Math.random() * 70 + 10}%`;
                equipment.style.fontSize = `${Math.random() * 20 + 15}px`;
                equipment.style.animation = `float ${Math.random() * 6 + 4}s ease-in-out infinite`;
                equipment.style.animationDelay = `${index * 0.5}s`;
                circuitBoard.appendChild(equipment);
            });
        }
    });
</script>
</body>
</html>