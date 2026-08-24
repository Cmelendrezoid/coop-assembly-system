<?php
/*
|--------------------------------------------------------------------------
| SESSION CONFIGURATION & LIFETIME CONTROL
|--------------------------------------------------------------------------
*/

// If session was already started prematurely by another script, close it temporarily
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Configure session settings safely prior to session initialization
if (session_status() === PHP_SESSION_NONE) {
    // 1. Extend server-side session garbage collection lifetime (1 day = 86400 seconds)
    ini_set('session.gc_maxlifetime', 86400);

    // 2. Extend browser cookie expiration (1 day = 86400 seconds)
    ini_set('session.cookie_lifetime', 86400);

    // 3. Set cookie path to root so session is accessible across all subfolders safely
    ini_set('session.cookie_path', '/');

    // 4. Protect session cookies from being accessed or hijacked by client-side scripts
    ini_set('session.cookie_httponly', 1);

    // 5. Start the session engine with custom rules active
    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION CONFIGURATION
|--------------------------------------------------------------------------
*/

$host = "localhost"; 
$user = "root";            
$pass = ""; // Default XAMPP/WAMP password is empty
$db   = "coopevoting"; // Primary target database


/*
|--------------------------------------------------------------------------
| 1. MYSQLI CONNECTION ($conn)
|--------------------------------------------------------------------------
*/

$conn = @new mysqli($host, $user, $pass, $db);

// Fallback to legacy database name if coopevoting does not exist
if ($conn->connect_error) {
    $db = "migs_db";
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("Database Connection Failed (MySQLi): " . $conn->connect_error);
    }
}

// Set charset to utf8mb4 for unicode compatibility
$conn->set_charset("utf8mb4");


/*
|--------------------------------------------------------------------------
| 2. PDO CONNECTION ($pdo)
|--------------------------------------------------------------------------
| Created alongside $conn to guarantee compatibility across modules 
| using PDO prepared statements.
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed (PDO): " . $e->getMessage());
}