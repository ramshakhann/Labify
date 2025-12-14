<?php
// Start the session
session_start();

// *** THIS IS THE MISSING LINE ***
// Include the database connection file
require_once 'db.php';

// --- Your existing logic, which is now correct ---

// Delete the user's auth tokens from the database if they are logged in
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close(); // Good practice to close the statement
}

// Delete the "Remember Me" cookies from the user's browser
setcookie("remember_selector", "", time() - 3600, "/");
setcookie("remember_validator", "", time() - 3600, "/");

/* Destroy the session */
session_unset();
session_destroy();

/* Also delete the PHP session cookie itself */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

/* Close the database connection */
 $conn->close();

/* Redirect to the login page */
header("Location: login.php");
exit();
?>