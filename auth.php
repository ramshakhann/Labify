<?php
session_start();
// Check if user is not logged in but has remember me cookies
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_validator'])) {
    
    $selector = $_COOKIE['remember_selector'];
    $validator = $_COOKIE['remember_validator'];

    // 1. Look up the token by its selector
    $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW() LIMIT 1");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $token = $result->fetch_assoc();

        // 2. Verify the validator from the cookie against the hashed token in the DB
        if (password_verify($validator, $token['token'])) {
            
            // 3. If valid, log the user in
            $_SESSION['user_id'] = $token['user_id'];
            // ... fetch other user details and add to session ...

            // 4. IMPORTANT: For security, regenerate the tokens
            // Delete the old token
            $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE id = ?");
            $stmt->bind_param("i", $token['id']);
            $stmt->execute();

            // Generate and set new tokens (same logic as in step 1)
            // ... (code to generate new selector/validator and set new cookies) ...

        } else {
            // Invalid token, clear cookies to prevent retry
            setcookie("remember_selector", "", time() - 3600, "/");
            setcookie("remember_validator", "", time() - 3600, "/");
        }
    } else {
        // Token expired or not found, clear cookies
        setcookie("remember_selector", "", time() - 3600, "/");
        setcookie("remember_validator", "", time() - 3600, "/");
    }
}
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
?>


