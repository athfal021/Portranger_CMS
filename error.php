<?php
require_once 'admin/includes/config.php';
require_once 'admin/includes/db.php';
require_once 'admin/includes/functions.php';

$db = getDB();
$profile = getProfile();
$appearance = getAppearance();
$seo = getSEO();
$navigation = $db->query("SELECT * FROM navigation_items WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// Get error details
$error_code = isset($_SERVER['REDIRECT_STATUS']) ? $_SERVER['REDIRECT_STATUS'] : (isset($_GET['code']) ? (int)$_GET['code'] : 500);
$error_messages = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Page Not Found',
    405 => 'Method Not Allowed',
    500 => 'Internal Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout'
];
$error_title = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Something Went Wrong';
$error_description = $error_code === 404 ? 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.' : 'We\'re sorry, but something went wrong. Please try again later or contact the administrator.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?= $error_code ?> - <?= htmlspecialchars($seo['site_title']) ?></title>
    <?php if ($appearance['favicon_path']): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $appearance['favicon_path'] ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --primary: <?= $appearance['primary_color'] ?>; }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .border-primary { border-color: var(--primary); }
        
        /* Floating animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        .float-animation-delay {
            animation: float 3s ease-in-out infinite 0.5s;
        }
        
        /* Fade in */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }
        .fade-in-up-delay {
            animation: fadeInUp 0.8s ease-out 0.3s forwards;
            opacity: 0;
        }
        
        /* Pulse for primary button */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(<?= hexdec(substr($appearance['primary_color'], 1, 2)) ?>, <?= hexdec(substr($appearance['primary_color'], 3, 2)) ?>, <?= hexdec(substr($appearance['primary_color'], 5, 2)) ?>, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(<?= hexdec(substr($appearance['primary_color'], 1, 2)) ?>, <?= hexdec(substr($appearance['primary_color'], 3, 2)) ?>, <?= hexdec(substr($appearance['primary_color'], 5, 2)) ?>, 0); }
            100% { box-shadow: 0 0 0 0 rgba(<?= hexdec(substr($appearance['primary_color'], 1, 2)) ?>, <?= hexdec(substr($appearance['primary_color'], 3, 2)) ?>, <?= hexdec(substr($appearance['primary_color'], 5, 2)) ?>, 0); }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        /* Error number big and floating */
        .error-number {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        @media (min-width: 768px) {
            .error-number {
                font-size: 12rem;
            }
        }
        
        /* Geometric shapes decoration */
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            pointer-events: none;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 overflow-x-hidden">
    <!-- Decorative shapes -->
    <div class="shape w-64 h-64 bg-primary top-20 -left-20 float-animation"></div>
    <div class="shape w-48 h-48 bg-indigo-400 bottom-20 right-20 float-animation-delay"></div>
    <div class="shape w-32 h-32 bg-purple-400 top-1/2 left-1/2 float-animation" style="animation-delay: 1s;"></div>

    <!-- Simple Nav -->
    <nav class="bg-white shadow-md fixed w-full z-20">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <?php if ($appearance['logo_path']): ?>
                    <img src="<?= BASE_URL . $appearance['logo_path'] ?>" class="h-10 w-auto" alt="Logo">
                <?php endif; ?>
                <div class="text-xl font-bold text-primary"><?= htmlspecialchars($profile['full_name']) ?></div>
            </div>
            <div class="hidden md:flex space-x-6">
                <?php foreach ($navigation as $nav): ?>
                    <a href="<?= htmlspecialchars($nav['url']) ?>" class="text-gray-700 hover:text-primary transition"><?= htmlspecialchars($nav['title']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <!-- Error Content -->
    <div class="container mx-auto px-6 min-h-screen flex items-center justify-center pt-20 pb-10">
        <div class="text-center relative z-10 fade-in-up">
            <!-- Floating icon -->
            <div class="mb-6 float-animation">
                <div class="inline-block bg-primary/10 p-6 rounded-full">
                    <?php if ($error_code === 404): ?>
                        <i class="fas fa-compass text-6xl text-primary"></i>
                    <?php elseif ($error_code === 403): ?>
                        <i class="fas fa-lock text-6xl text-primary"></i>
                    <?php elseif ($error_code === 500): ?>
                        <i class="fas fa-server text-6xl text-primary"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle text-6xl text-primary"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Error number -->
            <div class="error-number"><?= $error_code ?></div>

            <!-- Error title -->
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mt-2"><?= htmlspecialchars($error_title) ?></h1>

            <!-- Error description -->
            <p class="text-gray-600 mt-4 max-w-md mx-auto"><?= htmlspecialchars($error_description) ?></p>

            <!-- Action buttons -->
            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4 fade-in-up-delay">
                <a href="<?= BASE_URL ?>" class="bg-primary text-white px-8 py-3 rounded-full hover:bg-opacity-90 transition inline-flex items-center justify-center gap-2 pulse-animation">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
                <button onclick="history.back()" class="border-2 border-primary text-primary px-8 py-3 rounded-full hover:bg-primary hover:text-white transition inline-flex items-center justify-center gap-2">
                    <i class="fas fa-arrow-left"></i> Go Back
                </button>
            </div>

            <!-- Helpful links (only on 404) -->
            <?php if ($error_code === 404): ?>
            <div class="mt-8 flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
                <a href="<?= BASE_URL ?>#about" class="text-gray-500 hover:text-primary transition">About</a>
                <a href="<?= BASE_URL ?>#projects" class="text-gray-500 hover:text-primary transition">Projects</a>
                <a href="<?= BASE_URL ?>#contact" class="text-gray-500 hover:text-primary transition">Contact</a>
                <?php if ($downloadCount > 0): ?>
                <a href="<?= BASE_URL ?>downloads.php" class="text-gray-500 hover:text-primary transition">Downloads</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Status code -->
            <p class="text-xs text-gray-400 mt-8">Error <?= $error_code ?> · <?= date('Y') ?> <?= htmlspecialchars($profile['full_name']) ?></p>
        </div>
    </div>

    <script>
        // Add a small confetti-like effect on load
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth entrance animation already handled by CSS
        });
    </script>
</body>
</html>