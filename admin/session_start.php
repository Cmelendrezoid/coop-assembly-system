<?php
// admin/session_start.php

// 1. Set session cookie lifetime (8 hours = 28800 seconds)
ini_set('session.cookie_lifetime', 28800);

// 2. Set the maximum lifetime of the session data on the server (8 hours)
ini_set('session.gc_maxlifetime', 28800);

// 3. Start the session safely if it isn't started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 5. Global Authentication Guard
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../voters/login.php");
    exit();
}