<?php
// Ensure variables are defined
$seo = $seo ?? getSEO();
$appearance = $appearance ?? getAppearance();
$profile = $profile ?? getProfile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($seo['site_title'] ?? 'Admin') ?> - Admin</title>
    <?php if (!empty($appearance['favicon_path'])): ?>
        <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $appearance['favicon_path'] ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
    <style>
        /* Mobile sidebar toggle */
        .sidebar-mobile { transition: transform 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar-desktop { transform: translateX(-100%); position: fixed; z-index: 50; height: 100vh; overflow-y: auto; background: #111827; }
            .sidebar-desktop.open { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile top bar with hamburger -->
    <div class="md:hidden bg-gray-900 text-white p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-50">
        <div class="font-bold"><?= htmlspecialchars($seo['site_title'] ?? 'Portfolio CMS') ?></div>
        <button id="mobile-menu-toggle" class="text-white focus:outline-none">
            <i class="fas fa-bars text-2xl"></i>
        </button>
    </div>
    <div class="flex min-h-screen pt-16 md:pt-0">