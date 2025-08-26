<?php
require_once 'config.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    logAdminActivity('LOGOUT', 'Admin logged out');
}

// Destroy all session data
session_destroy();

// Redirect to login page with logout message
header('Location: login.php?logout=1');
exit();
?>
