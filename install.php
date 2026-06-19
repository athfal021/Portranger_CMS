<?php
// Check if already installed
if (file_exists('admin/includes/config.php')) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .card { max-width: 500px; width: 100%; }
        </style>
    </head>
    <body>
        <div class="card bg-white rounded-lg shadow-2xl p-8 text-center">
            <div class="text-6xl mb-4 text-green-500"><i class="fas fa-check-circle"></i></div>
            <h1 class="text-2xl font-bold mb-2">Setup Already Completed</h1>
            <p class="text-gray-600 mb-6">The system is already configured. Redirecting to admin panel...</p>
            <div class="animate-pulse"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i></div>
        </div>
        <meta http-equiv="refresh" content="3;url=admin/login.php">
    </body>
    </html>';
    exit;
}

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Determine current step
$step = isset($_SESSION['install_step']) ? (int)$_SESSION['install_step'] : 1;
$error = '';
$db_host = isset($_SESSION['db_host']) ? $_SESSION['db_host'] : 'localhost';
$db_name = isset($_SESSION['db_name']) ? $_SESSION['db_name'] : '';
$db_user = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : '';
$db_pass = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';

// Step 1: Database credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF validation failed');
    }
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = $_POST['db_pass'];

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'All database fields are required.';
    } else {
        // Test connection
        try {
            $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Check if database exists
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
            if ($stmt->fetch()) {
                // Database exists – check if it has tables
                $pdo->exec("USE `$db_name`");
                $tables = $pdo->query("SHOW TABLES")->fetchAll();
                if (count($tables) > 0) {
                    // Tables exist – store flag for step 2 safety
                    $_SESSION['db_has_tables'] = true;
                } else {
                    $_SESSION['db_has_tables'] = false;
                }
            } else {
                $_SESSION['db_has_tables'] = false;
            }
            // Store credentials in session
            $_SESSION['db_host'] = $db_host;
            $_SESSION['db_name'] = $db_name;
            $_SESSION['db_user'] = $db_user;
            $_SESSION['db_pass'] = $db_pass;
            $_SESSION['install_step'] = 2;
            header('Location: install.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }
}

// Step 2: Admin credentials & final install
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF validation failed');
    }
    $admin_user = trim($_POST['admin_user']);
    $admin_pass = $_POST['admin_pass'];
    $admin_confirm = $_POST['admin_confirm'];
    $confirm_drop = isset($_POST['confirm_drop']) ? true : false;

    if (empty($admin_user) || empty($admin_pass)) {
        $error = 'All admin fields are required.';
    } elseif ($admin_pass !== $admin_confirm) {
        $error = 'Admin passwords do not match.';
    } elseif (strlen($admin_pass) < 6) {
        $error = 'Admin password must be at least 6 characters.';
    } elseif ($_SESSION['db_has_tables'] && !$confirm_drop) {
        $error = 'You must confirm that you want to delete existing tables.';
    } else {
        // Proceed with installation
        try {
            $db_host = $_SESSION['db_host'];
            $db_name = $_SESSION['db_name'];
            $db_user = $_SESSION['db_user'];
            $db_pass = $_SESSION['db_pass'];

            $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            // Read SQL file
            $sql_file = __DIR__ . '/sql/install.sql';
            if (!file_exists($sql_file)) {
                throw new Exception('SQL installation file not found. Please ensure sql/install.sql exists.');
            }
            $sql = file_get_contents($sql_file);
            if ($sql === false) {
                throw new Exception('Failed to read SQL file.');
            }

            // Execute SQL statements
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $queries = explode(";\n", $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            // Create admin user
            $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$admin_user, $hashed]);

            // Write config.php
            $config_template = '<?php
session_start();
ob_start();
error_reporting(E_ALL);
ini_set("display_errors", 0);
date_default_timezone_set("UTC");

// Database configuration
define("DB_HOST", "{{DB_HOST}}");
define("DB_NAME", "{{DB_NAME}}");
define("DB_USER", "{{DB_USER}}");
define("DB_PASS", "{{DB_PASS}}");

// Site paths
$protocol = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https://" : "http://";
$host = $_SERVER["HTTP_HOST"];
$script_name = $_SERVER["SCRIPT_NAME"];
$root_path = dirname(dirname($script_name));
if ($root_path == "/" || $root_path == "\\\\") $root_path = "";
define("BASE_URL", $protocol . $host . $root_path . "/");
define("ADMIN_URL", BASE_URL . "admin/");
define("UPLOAD_DIR", dirname(__DIR__, 2) . "/uploads/media/");
define("BACKUP_DIR", dirname(__DIR__, 2) . "/backups/");

// Allowed file types
define("ALLOWED_IMAGES", ["jpg", "jpeg", "png", "gif", "webp", "svg"]);
define("ALLOWED_DOCS", ["pdf", "doc", "docx", "zip", "txt"]);
define("MAX_FILE_SIZE", 5242880);

// CSRF Token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>';
            $config_content = str_replace(
                ['{{DB_HOST}}', '{{DB_NAME}}', '{{DB_USER}}', '{{DB_PASS}}'],
                [$db_host, $db_name, $db_user, $db_pass],
                $config_template
            );

            $config_path = __DIR__ . '/admin/includes/config.php';
            if (!file_put_contents($config_path, $config_content)) {
                throw new Exception('Failed to write config.php. Please check file permissions.');
            }

            // Clear session and show success
            $_SESSION = [];
            session_destroy();
            $success = true;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// If success, show success page
if (isset($success) && $success === true) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Complete</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
            .card { max-width: 540px; width: 100%; background: white; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 2rem; }
        </style>
    </head>
    <body>
        <div class="card text-center">
            <div class="text-6xl mb-4 text-green-500"><i class="fas fa-check-circle"></i></div>
            <h1 class="text-2xl font-bold mb-2">Setup Complete!</h1>
            <p class="text-gray-600 mb-6">Your site has been configured successfully.</p>
            <a href="admin/login.php" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 inline-block">Go to Admin Login</a>
            <p class="text-sm text-red-500 mt-4"><i class="fas fa-exclamation-triangle"></i> For security, please delete this file (install.php) now.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Display the installer UI (step 1 or step 2)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { max-width: 540px; width: 100%; background: white; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 2rem; }
        .step-dots { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem; }
        .step-dot { width: 10px; height: 10px; border-radius: 50%; background: #d1d5db; transition: 0.3s; }
        .step-dot.active { background: #3b82f6; width: 30px; border-radius: 5px; }
        .step-dot.completed { background: #10b981; }
        .error-box { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <!-- Step dots -->
        <div class="step-dots">
            <div class="step-dot <?= $step == 1 ? 'active' : 'completed' ?>"></div>
            <div class="step-dot <?= $step == 2 ? 'active' : '' ?>"></div>
        </div>

        <?php if ($step == 1): ?>
            <!-- Step 1: Database -->
            <h1 class="text-2xl font-bold text-center mb-2">Database Setup</h1>
            <p class="text-gray-500 text-center mb-6">Enter your database credentials.</p>

            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="step1" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-1">Database Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($db_host) ?>" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-1">Database Name</label>
                    <input type="text" name="db_name" value="<?= htmlspecialchars($db_name) ?>" placeholder="Enter database name" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-1">Database Username</label>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($db_user) ?>" placeholder="Enter database username" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-1">Database Password</label>
                    <input type="password" name="db_pass" placeholder="Enter database password" class="w-full border rounded px-3 py-2">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Continue</button>
            </form>
        <?php else: ?>
            <!-- Step 2: Admin + Safety -->
            <h1 class="text-2xl font-bold text-center mb-2">Admin Account</h1>
            <p class="text-gray-500 text-center mb-6">Set your admin username and password.</p>

            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($_SESSION['db_has_tables']): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i> Warning</p>
                    <p>The database "<strong><?= htmlspecialchars($_SESSION['db_name']) ?></strong>" already contains tables.</p>
                    <p>Continuing will <strong>delete all existing data</strong> and replace it with default content.</p>
                    <label class="mt-2 flex items-center">
                        <input type="checkbox" name="confirm_drop" value="1" class="mr-2" required>
                        <span>I understand and want to proceed</span>
                    </label>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="step2" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-1">Admin Username</label>
                    <input type="text" name="admin_user" value="admin" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-1">Admin Password</label>
                    <input type="password" name="admin_pass" placeholder="Min 6 characters" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-1">Confirm Admin Password</label>
                    <input type="password" name="admin_confirm" placeholder="Confirm password" class="w-full border rounded px-3 py-2" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Install</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>