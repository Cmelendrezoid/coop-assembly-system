<?php
session_start();

// Determine the redirect target destination safely
$redirectTo = 'login.php';
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    // Strip malicious path tags or characters if present
    $redirectTo = basename($_GET['redirect']); 
}

// Clear all active session variables
$_SESSION = array();

// Cleanly invalidate the session cookie on the client browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session data storage on server
session_destroy();

// Forward browser headers to the clean destination
header("Location: " . $redirectTo);
exit();