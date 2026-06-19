<?php
require_once 'admin/includes/config.php';
require_once 'admin/includes/db.php';
require_once 'admin/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $message = sanitize($_POST['message']);
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $message]);
    
    $_SESSION['contact_success'] = 'Message sent successfully!';
    header('Location: ' . BASE_URL . 'index.php#contact');
    exit;
}
?>