<?php

// If a session was already started prematurely by another script, close it temporarily
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

/*
|--------------------------------------------------------------------------
| CONFIGURE SESSION LIFETIME & TRACKING SCOPE
|--------------------------------------------------------------------------
*/

// 1. Extend the server-side lifetime of the session data file to 1 day (86400 seconds)
ini_set('session.gc_maxlifetime', 86400);

// 2. Extend the browser-side cookie expiration to match
ini_set('session.cookie_lifetime', 86400);

// 3. Set the cookie path to the root '/' so it is shared across all subfolders safely
ini_set('session.cookie_path', '/');

// 4. Protect session cookies from being accessed or hijacked by malicious client-side scripts
ini_set('session.cookie_httponly', 1);

// 5. Restart the session engine with our custom rules active
session_start();


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION CONFIGURATION (UPDATED FOR LOCALHOST)
|--------------------------------------------------------------------------
*/

$host = "localhost"; 
$user = "root";            
$pass = ""; // Default XAMPP/WAMP password is empty
$db   = "migs_db"; 

$conn = new mysqli(
    $host,
    $user,
    $pass,
    $db
);

if($conn->connect_error){
    die(
        "Connection failed: " . 
        $conn->connect_error
    );
}