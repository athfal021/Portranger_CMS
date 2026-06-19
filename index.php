<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin/includes/config.php';
require_once 'admin/includes/db.php';
require_once 'admin/includes/functions.php';

$db = getDB();
$profile = getProfile();
$appearance = getAppearance();
$seo = getSEO();
$sections = $db->query("SELECT * FROM sections WHERE is_visible = 1 ORDER BY display_order")->fetchAll();
$skills = $db->query("SELECT * FROM skills ORDER BY display_order")->fetchAll();
$experiences = $db->query("SELECT * FROM experiences ORDER BY display_order")->fetchAll();
$educations = $db->query("SELECT * FROM educations ORDER BY display_order")->fetchAll();
$projects = $db->query("SELECT p.*, m.file_path as thumbnail_path FROM projects p LEFT JOIN media m ON p.thumbnail_media_id = m.id ORDER BY p.display_order LIMIT 6")->fetchAll();
$socialLinks = $db->query("SELECT * FROM social_links ORDER BY display_order")->fetchAll();
$navigation = $db->query("SELECT * FROM navigation_items WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// Cover slides
$coverSlides = [];
try {
    $coverSlides = $db->query("SELECT cs.*, m.file_path FROM cover_slides cs JOIN media m ON cs.media_id = m.id WHERE cs.is_active = 1 ORDER BY cs.display_order")->fetchAll();
} catch (PDOException $e) {
    $coverSlides = [];
}

// Fetch social sharing image
$socialImageUrl = null;
if (!empty($seo['social_image_media_id'])) {
    $stmt = $db->prepare("SELECT file_path, created_at FROM media WHERE id = ?");
    $stmt->execute([$seo['social_image_media_id']]);
    $img = $stmt->fetch();
    if ($img) {
        $socialImageUrl = BASE_URL . $img['file_path'] . '?v=' . strtotime($img['created_at']);
    }
}

// Add Downloads link if needed
$downloadCount = $db->query("SELECT COUNT(*) FROM downloads")->fetchColumn();
$hasDownloadsLink = false;
foreach ($navigation as $nav) {
    if (strpos($nav['url'], 'downloads.php') !== false) {
        $hasDownloadsLink = true;
        break;
    }
}
if ($downloadCount > 0 && !$hasDownloadsLink) {
    $navigation[] = [
        'title' => 'Downloads',
        'url' => BASE_URL . 'downloads.php',
        'target' => '_self',
        'id' => 999,
        'is_active' => 1,
        'display_order' => 999
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seo['site_title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo['meta_description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seo['meta_keywords']) ?>">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($seo['site_title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo['meta_description']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php if ($socialImageUrl): ?>
        <meta property="og:image" content="<?= $socialImageUrl ?>">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:image" content="<?= $socialImageUrl ?>">
    <?php endif; ?>
    
    <?php if ($appearance['favicon_path']): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $appearance['favicon_path'] ?>">
    <?php endif; ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <style>
        :root {
            --primary: <?= $appearance['primary_color'] ?>;
            --secondary: <?= $appearance['secondary_color'] ?>;
        }
        .bg-primary { background-color: var(--primary); }
        .bg-secondary { background-color: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .border-primary { border-color: var(--primary); }
        .border-secondary { border-color: var(--secondary); }
        .hover\:bg-primary:hover { background-color: var(--primary); }
        .hover\:bg-secondary:hover { background-color: var(--secondary); }
        .hover\:text-primary:hover { color: var(--primary); }
        .hover\:text-secondary:hover { color: var(--secondary); }
        .hover\:border-primary:hover { border-color: var(--primary); }
        .hover\:border-secondary:hover { border-color: var(--secondary); }
        .ring-primary { --tw-ring-color: var(--primary); }
        .ring-secondary { --tw-ring-color: var(--secondary); }
        
        /* Mobile menu */
        .mobile-nav { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1); transition: left 0.3s ease; z-index: 1000; overflow-y: auto; }
        .mobile-nav.open { left: 0; }
        .menu-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .menu-overlay.active { display: block; }
        body.menu-open { overflow: hidden; }
        .slide { transition: opacity 1s ease-in-out; }
        .z-1 { z-index: 1; }
        .z-2 { z-index: 2; }
    </style>
</head>
<body class="bg-gray-50">
  <!-- ---------------------- -->

<!-- Animated Background - Interactive Effect -->

       <!-- Premium Mesh Gradient Background -->
<div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
    <!-- Gradient Orbs -->
    <div class="absolute top-0 left-0 w-[600px] h-[600px] rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-mesh-1" style="background: radial-gradient(circle, var(--primary) 0%, transparent 70%);"></div>
    <div class="absolute bottom-0 right-0 w-[500px] h-[500px] rounded-full mix-blend-multiply filter blur-3xl opacity-25 animate-mesh-2" style="background: radial-gradient(circle, var(--secondary) 0%, transparent 70%);"></div>
    <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-[400px] h-[400px] rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-mesh-3" style="background: radial-gradient(circle, var(--primary) 0%, var(--secondary) 50%, transparent 70%);"></div>
    <div class="absolute bottom-1/4 left-1/4 w-[300px] h-[300px] rounded-full mix-blend-multiply filter blur-3xl opacity-15 animate-mesh-4" style="background: radial-gradient(circle, var(--secondary) 0%, transparent 70%);"></div>
    
    <!-- Floating Particles -->
    <div class="absolute top-1/4 right-1/3 w-2 h-2 rounded-full opacity-60 animate-particle-1" style="background: var(--primary);"></div>
    <div class="absolute top-2/3 left-1/4 w-3 h-3 rounded-full opacity-50 animate-particle-2" style="background: var(--secondary);"></div>
    <div class="absolute top-1/2 right-1/4 w-2 h-2 rounded-full opacity-45 animate-particle-3" style="background: var(--primary);"></div>
    <div class="absolute top-3/4 left-1/2 w-2.5 h-2.5 rounded-full opacity-40 animate-particle-4" style="background: var(--secondary);"></div>
    <div class="absolute top-1/3 left-3/4 w-1.5 h-1.5 rounded-full opacity-55 animate-particle-1" style="background: var(--primary);"></div>
    <div class="absolute bottom-1/3 right-1/4 w-3 h-3 rounded-full opacity-35 animate-particle-2" style="background: var(--secondary);"></div>
</div>

<style>
    /* Mesh Gradient Animations */
    @keyframes mesh-1 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        25% { transform: translate(60px, -40px) scale(1.15); }
        50% { transform: translate(-30px, 60px) scale(0.9); }
        75% { transform: translate(40px, -30px) scale(1.05); }
    }
    @keyframes mesh-2 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        25% { transform: translate(-50px, 40px) scale(0.9); }
        50% { transform: translate(70px, -30px) scale(1.2); }
        75% { transform: translate(-30px, 50px) scale(0.95); }
    }
    @keyframes mesh-3 {
        0%, 100% { transform: translate(-50%, -50%) scale(1); }
        33% { transform: translate(-45%, -55%) scale(1.2); }
        66% { transform: translate(-55%, -45%) scale(0.85); }
    }
    @keyframes mesh-4 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(40px, -50px) scale(1.1); }
    }
    
    /* Particle animations */
    @keyframes particle-1 {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.3; }
        50% { transform: translate(30px, -40px) scale(2); opacity: 0.9; }
    }
    @keyframes particle-2 {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.2; }
        50% { transform: translate(-35px, 25px) scale(2.2); opacity: 0.8; }
    }
    @keyframes particle-3 {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.15; }
        50% { transform: translate(20px, -50px) scale(2.5); opacity: 0.7; }
    }
    @keyframes particle-4 {
        0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.2; }
        50% { transform: translate(-25px, 35px) scale(1.8); opacity: 0.6; }
    }
    
    /* Apply animations */
    .animate-mesh-1 { animation: mesh-1 18s ease-in-out infinite; }
    .animate-mesh-2 { animation: mesh-2 20s ease-in-out infinite 1s; }
    .animate-mesh-3 { animation: mesh-3 22s ease-in-out infinite 2s; }
    .animate-mesh-4 { animation: mesh-4 16s ease-in-out infinite 1.5s; }
    
    .animate-particle-1 { animation: particle-1 6s ease-in-out infinite; }
    .animate-particle-2 { animation: particle-2 7s ease-in-out infinite 0.5s; }
    .animate-particle-3 { animation: particle-3 8s ease-in-out infinite 1s; }
    .animate-particle-4 { animation: particle-4 6.5s ease-in-out infinite 0.3s; }
</style>
                            

<!-- ---------------------- -->

    <!-- Mobile Menu Overlay -->
    <div class="menu-overlay" id="menu-overlay"></div>
    
    <!-- Mobile Navigation -->
    <div class="mobile-nav" id="mobile-nav">
        <div class="p-4 border-b flex justify-between items-center">
            <?php if ($appearance['logo_path']): ?>
                <img src="<?= BASE_URL . $appearance['logo_path'] ?>" class="h-8 w-auto" alt="Logo">
            <?php else: ?>
                <span class="font-bold text-xl text-primary"><?= htmlspecialchars($profile['full_name']) ?></span>
            <?php endif; ?>
            <button id="close-mobile-menu" class="text-gray-600 hover:text-primary"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <nav class="p-4 space-y-3">
            <?php foreach ($navigation as $nav): ?>
                <a href="<?= htmlspecialchars($nav['url']) ?>" class="block py-2 text-gray-700 hover:text-primary transition"><?= htmlspecialchars($nav['title']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Desktop Navbar -->
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
            <button id="mobile-menu-btn" class="md:hidden text-gray-700 hover:text-primary"><i class="fas fa-bars text-2xl"></i></button>
        </div>
    </nav>

    <!-- Hero Slideshow -->
    <section class="relative pt-32 pb-20 text-white overflow-hidden">
        <div id="slideshow-container" class="absolute inset-0 z-0">
            <?php if (count($coverSlides) > 0): ?>
                <?php foreach ($coverSlides as $index => $slide): ?>
                <div class="slide absolute inset-0 bg-cover bg-center <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>" style="background-image: url('<?= BASE_URL . $slide['file_path'] ?>');"></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="slide absolute inset-0 bg-cover bg-center" style="background: linear-gradient(135deg, <?= $appearance['primary_color'] ?> 0%, <?= $appearance['secondary_color'] ?> 100%);"></div>
            <?php endif; ?>
        </div>
        <div class="absolute inset-0 bg-black/40 z-1"></div>
        <div class="container mx-auto px-6 text-center relative z-2">
            <?php if ($profile['profile_image_path']): ?>
                <img src="<?= BASE_URL . $profile['profile_image_path'] ?>" class="w-32 h-32 rounded-full mx-auto mb-4 border-4 border-white shadow-lg object-cover">
            <?php endif; ?>
            <h1 class="text-5xl font-bold mb-4 drop-shadow-lg"><?= htmlspecialchars($profile['full_name']) ?></h1>
            <p class="text-xl mb-6 drop-shadow-md"><?= htmlspecialchars($profile['professional_title']) ?></p>
            <p class="text-lg max-w-2xl mx-auto drop-shadow-md"><?= htmlspecialchars($profile['short_intro']) ?></p>
            <div class="mt-8 flex justify-center space-x-4">
                <?php foreach ($socialLinks as $social): ?>
                    <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" class="bg-white/20 backdrop-blur-sm p-3 rounded-full hover:bg-white/30 transition">
                        <?php if (!empty($social['icon'])): ?>
                            <i class="<?= htmlspecialchars($social['icon']) ?> text-xl"></i>
                        <?php else: ?>
                            <i class="fab fa-<?= strtolower($social['platform']) ?> text-xl"></i>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">About Me</h2>
            <div class="flex flex-col md:flex-row items-center gap-10">
                <?php if ($profile['about_image_path']): ?>
                    <div class="md:w-1/3">
                        <img src="<?= BASE_URL . $profile['about_image_path'] ?>" class="rounded-lg shadow-lg w-full object-cover border-4 border-primary/20">
                    </div>
                    <div class="md:w-2/3 text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($profile['about_description'])) ?></div>
                <?php else: ?>
                    <div class="max-w-3xl mx-auto text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($profile['about_description'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Skills -->
    <?php if (count($skills) > 0): ?>
    <section id="skills" class="py-20 bg-gray-100">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Skills</h2>
            <div class="flex flex-wrap justify-center gap-4">
                <?php foreach ($skills as $skill): ?>
                <div class="bg-white rounded-lg shadow-md p-4 text-center w-40 hover:shadow-lg transition transform hover:-translate-y-1">
                    <?php if ($skill['icon']): ?>
                        <i class="<?= htmlspecialchars($skill['icon']) ?> text-3xl text-primary mb-2"></i>
                    <?php endif; ?>
                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($skill['name']) ?></h3>
                    <?php if ($skill['level']): ?><p class="text-sm text-gray-500"><?= htmlspecialchars($skill['level']) ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Experience -->
    <?php if (count($experiences) > 0): ?>
    <section id="experience" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Work Experience</h2>
            <div class="max-w-3xl mx-auto space-y-8">
                <?php foreach ($experiences as $exp): ?>
                <div class="bg-gray-50 p-6 rounded-lg shadow-md border-l-4 border-primary hover:shadow-lg transition">
                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($exp['position']) ?></h3>
                    <p class="text-primary font-semibold"><?= htmlspecialchars($exp['company']) ?></p>
                    <p class="text-gray-500 text-sm"><?= date('M Y', strtotime($exp['start_date'])) ?> - <?= $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])) ?></p>
                    <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Education -->
    <?php if (count($educations) > 0): ?>
    <section id="education" class="py-20 bg-gray-100">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Education</h2>
            <div class="max-w-3xl mx-auto space-y-6">
                <?php foreach ($educations as $edu): ?>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-secondary hover:shadow-lg transition">
                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($edu['degree']) ?></h3>
                    <p class="text-secondary font-semibold"><?= htmlspecialchars($edu['institution']) ?></p>
                    <p class="text-gray-500 text-sm"><?= date('Y', strtotime($edu['start_date'])) ?> - <?= $edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : 'Present' ?></p>
                    <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($edu['description'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Projects -->
    <?php if (count($projects) > 0): ?>
    <section id="projects" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Projects</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($projects as $project): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition transform hover:-translate-y-1">
                    <?php if ($project['thumbnail_path']): ?>
                        <img src="<?= BASE_URL . $project['thumbnail_path'] ?>" class="w-full h-48 object-cover" alt="<?= htmlspecialchars($project['title']) ?>">
                    <?php endif; ?>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($project['title']) ?></h3>
                        <p class="text-gray-600 mb-4"><?= htmlspecialchars($project['short_description']) ?></p>
                        <div class="flex space-x-3">
                            <?php if ($project['demo_link']): ?>
                                <a href="<?= $project['demo_link'] ?>" target="_blank" class="bg-primary text-white px-4 py-2 rounded hover:bg-secondary transition">Live Demo</a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>project.php?id=<?= $project['id'] ?>" class="border-2 border-primary text-primary px-4 py-2 rounded hover:bg-primary hover:text-white transition">Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Dynamic Sections -->
    <?php foreach ($sections as $section): ?>
    <section id="section-<?= $section['id'] ?>" class="py-20 <?= $section['id'] % 2 == 0 ? 'bg-gray-100' : 'bg-white' ?>">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-6 text-gray-800"><?= htmlspecialchars($section['title']) ?></h2>
            <?php if ($section['description']): ?>
                <div class="max-w-3xl mx-auto text-gray-700"><?= nl2br(htmlspecialchars($section['description'])) ?></div>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <!-- Contact -->
    <section id="contact" class="py-20 bg-gray-100">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Contact Me</h2>
            <div class="max-w-2xl mx-auto">
                <?php if (isset($_SESSION['contact_success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 rounded mb-4"><?= $_SESSION['contact_success']; unset($_SESSION['contact_success']); ?></div>
                <?php endif; ?>
                <form action="<?= BASE_URL ?>contact.php" method="POST" class="bg-white p-8 rounded-lg shadow-lg">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-4">
                        <input type="text" name="name" placeholder="Your Name" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                    <div class="mb-4">
                        <input type="email" name="email" placeholder="Your Email" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                    <div class="mb-4">
                        <textarea name="message" rows="5" placeholder="Your Message" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary" required></textarea>
                    </div>
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded hover:bg-secondary transition w-full">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-6">
        <div class="container mx-auto px-6">
            <?php
            $footerLinks = $db->query("SELECT * FROM footer_links WHERE is_active = 1 ORDER BY display_order")->fetchAll();
            ?>
            <?php if (count($footerLinks) > 0): ?>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-3 mb-4">
                <?php foreach ($footerLinks as $link): ?>
                    <a href="<?= htmlspecialchars($link['url']) ?>" target="<?= $link['target'] ?>" class="text-gray-300 hover:text-primary transition text-sm"><?= htmlspecialchars($link['title']) ?></a>
                <?php endforeach; ?>
            </div>
            <hr class="border-gray-700 max-w-md mx-auto mb-4">
            <?php endif; ?>
            <div class="<?= count($footerLinks) > 0 ? '' : 'border-0 pt-0' ?>">
                <p class="text-center text-gray-400 text-sm">&copy; <?= date('Y') ?> <?= htmlspecialchars($profile['full_name']) ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>assets/js/frontend.js"></script>
    <script>
        // Slideshow
        (function() {
            const slides = document.querySelectorAll('#slideshow-container .slide');
            if (slides.length > 1) {
                let current = 0;
                setInterval(() => {
                    slides[current].classList.remove('opacity-100');
                    slides[current].classList.add('opacity-0');
                    current = (current + 1) % slides.length;
                    slides[current].classList.remove('opacity-0');
                    slides[current].classList.add('opacity-100');
                }, 5000);
            }
        })();
    </script>
</body>
</html>