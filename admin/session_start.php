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

// Require an admin session established after verifying the account's role.
if (
    !isset($_SESSION['admin_id']) ||
    ($_SESSION['admin_role_verified'] ?? false) !== true
) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role_verified']);
    header("Location: login.php");
    exit();
}