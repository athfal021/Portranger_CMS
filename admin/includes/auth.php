<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . ADMIN_URL . 'login.php');
    exit;
}

// Refresh session activity
$_SESSION['last_activity'] = time();
?>