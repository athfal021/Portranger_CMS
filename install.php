<?php
declare(strict_types=1);

session_start();

/**
 * Installer for the application.
 * - Step 1: database credentials
 * - Step 2: admin credentials + optional destructive reinstall confirmation
 * - Imports sql/install.sql
 * - Registers default media files from uploads/media/
 * - Writes admin/includes/config.php
 * - Redirects to admin/login.php after success
 */

const INSTALL_SQL_FILE = __DIR__ . '/sql/install.sql';
const CONFIG_FILE      = __DIR__ . '/admin/includes/config.php';
const MEDIA_DIR        = __DIR__ . '/uploads/media/';
const REDIRECT_TARGET  = 'admin/login.php';
const REDIRECT_SECONDS = 5;

function installedAlready(): bool
{
    return file_exists(CONFIG_FILE);
}

function resetInstallerState(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCheck(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('CSRF validation failed.');
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function isPdoError(PDOException $e): string
{
    return $e->getMessage();
}

function dbPdo(string $host, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES    => false,
    ]);
    return $pdo;
}

function databaseExists(PDO $pdo, string $dbName): bool
{
    $stmt = $pdo->prepare(
        'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db'
    );
    $stmt->execute([':db' => $dbName]);
    return (bool) $stmt->fetchColumn();
}

function databaseHasTables(PDO $pdo, string $dbName): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db');
    $stmt->execute([':db' => $dbName]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function dropAllTables(PDO $pdo, string $dbName): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db'
    );
    $stmt->execute([':db' => $dbName]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $safeTable = '`' . str_replace('`', '``', (string) $table) . '`';
        $pdo->exec("DROP TABLE IF EXISTS {$safeTable}");
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function parseSqlStatements(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $sql = str_replace("\r\n", "\n", $sql);

    $statements = [];
    $buffer = '';
    $len = strlen($sql);

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buffer .= $ch;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-') {
                $prev = $i > 0 ? $sql[$i - 1] : "\n";
                if ($prev === "\n" || $prev === "\r") {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    $filtered = [];
    foreach ($statements as $statement) {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            continue;
        }
        if (preg_match('/^\s*DELIMITER\s+/i', $trimmed)) {
            continue;
        }
        $filtered[] = $trimmed;
    }

    return $filtered;
}

function importSqlFile(PDO $pdo, string $file): void
{
    if (!file_exists($file)) {
        throw new RuntimeException('SQL installation file not found.');
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Failed to read SQL installation file.');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach (parseSqlStatements($sql) as $statement) {
        $pdo->exec($statement);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $stmt->execute([':table' => $table]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute([
        ':table' => $table,
        ':column' => $column,
    ]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function getExistingMediaId(PDO $pdo, string $filePath): ?int
{
    if (!tableExists($pdo, 'media')) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM media WHERE file_path = :path LIMIT 1');
        $stmt->execute([':path' => $filePath]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    } catch (Throwable $e) {
        return null;
    }
}

function detectMimeType(string $filePath): string
{
    $mime = function_exists('mime_content_type') ? @mime_content_type($filePath) : false;
    return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
}

function registerMedia(PDO $pdo, string $fullPath, string $relativePath, string $fileName): ?int
{
    if (!file_exists($fullPath)) {
        return null;
    }

    $existing = getExistingMediaId($pdo, $relativePath);
    if ($existing !== null) {
        return $existing;
    }

    if (!tableExists($pdo, 'media')) {
        return null;
    }

    $columns = ['file_name', 'original_name', 'file_path', 'file_type', 'mime_type'];
    $hasColumns = true;
    foreach ($columns as $col) {
        if (!columnExists($pdo, 'media', $col)) {
            $hasColumns = false;
            break;
        }
    }

    if (!$hasColumns) {
        return null;
    }

    $type = 'image';
    $mime = detectMimeType($fullPath);

    $stmt = $pdo->prepare(
        'INSERT INTO media (file_name, original_name, file_path, file_type, mime_type)
         VALUES (:file_name, :original_name, :file_path, :file_type, :mime_type)'
    );
    $stmt->execute([
        ':file_name'     => $fileName,
        ':original_name' => $fileName,
        ':file_path'     => $relativePath,
        ':file_type'     => $type,
        ':mime_type'     => $mime,
    ]);

    return (int) $pdo->lastInsertId();
}

function findDefaultImage(PDO $pdo, string $baseName, array $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico']): ?int
{
    foreach ($extensions as $ext) {
        $fileName = $baseName . '.' . $ext;
        $fullPath = MEDIA_DIR . $fileName;
        $relativePath = 'uploads/media/' . $fileName;

        if (file_exists($fullPath)) {
            $id = registerMedia($pdo, $fullPath, $relativePath, $fileName);
            if ($id !== null) {
                return $id;
            }
        }
    }

    return null;
}

function findDefaultSlides(PDO $pdo): array
{
    $slides = [];
    $index = 1;

    while (true) {
        $found = false;
        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
            $fileName = "default-cover-{$index}.{$ext}";
            $fullPath = MEDIA_DIR . $fileName;
            $relativePath = 'uploads/media/' . $fileName;

            if (file_exists($fullPath)) {
                $id = registerMedia($pdo, $fullPath, $relativePath, $fileName);
                if ($id !== null) {
                    $slides[] = ['media_id' => $id, 'display_order' => $index];
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            break;
        }
        $index++;
    }

    return $slides;
}

function setSingleValue(PDO $pdo, string $table, string $column, int $mediaId): void
{
    if (!tableExists($pdo, $table) || !columnExists($pdo, $table, $column)) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :id WHERE id = 1");
    $stmt->execute([':id' => $mediaId]);
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function writeConfigFile(string $host, string $name, string $user, string $pass): void
{
    ensureDirectory(dirname(CONFIG_FILE));

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/install.php';
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $baseUrl = $protocol . $httpHost . ($basePath !== '' ? $basePath : '') . '/';

    $hostLit = var_export($host, true);
    $nameLit = var_export($name, true);
    $userLit = var_export($user, true);
    $passLit = var_export($pass, true);
    $baseUrlLit = var_export($baseUrl, true);

    $config = <<<PHP
<?php
declare(strict_types=1);

session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

define('DB_HOST', {$hostLit});
define('DB_NAME', {$nameLit});
define('DB_USER', {$userLit});
define('DB_PASS', {$passLit});

define('BASE_URL', {$baseUrlLit});
define('ADMIN_URL', BASE_URL . 'admin/');
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/uploads/media/');
define('BACKUP_DIR', dirname(__DIR__, 2) . '/backups/');

define('ALLOWED_IMAGES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('ALLOWED_DOCS', ['pdf', 'doc', 'docx', 'zip', 'txt']);
define('MAX_FILE_SIZE', 52428800);

if (empty(\$_SESSION['csrf_token'])) {
    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
PHP;

    if (file_put_contents(CONFIG_FILE, $config . "\n") === false) {
        throw new RuntimeException('Failed to write admin/includes/config.php. Check file permissions.');
    }
}

function renderInstalledPage(): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Already Completed</title>
    <meta http-equiv="refresh" content="3;url=<?= h(REDIRECT_TARGET) ?>">
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px;
        }
        .card {
            width: min(560px, 100%);
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.24);
        }
        .title { font-size: 28px; font-weight: 700; margin: 0 0 10px; }
        .text { color: #555; margin: 0 0 18px; line-height: 1.6; }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size:56px;line-height:1;">✅</div>
        <h1 class="title">Setup already completed</h1>
        <p class="text">The application is already configured. Redirecting to the admin panel.</p>
        <a class="btn" href="<?= h(REDIRECT_TARGET) ?>">Go now</a>
    </div>
</body>
</html>
<?php
}

function renderSuccessPage(): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Complete</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px;
        }
        .card {
            width: min(560px, 100%);
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.24);
        }
        .title { font-size: 28px; font-weight: 700; margin: 0 0 10px; }
        .text { color: #555; margin: 0 0 18px; line-height: 1.6; }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
        }
        .warn { color: #b91c1c; margin-top: 18px; font-size: 14px; }
        .count { font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size:56px;line-height:1;">🎉</div>
        <h1 class="title">Setup complete</h1>
        <p class="text">Congratulation! Setup completed successfully.</p>
        <p class="text">Redirecting to the admin panel in <span class="count" id="countdown"><?= (int) REDIRECT_SECONDS ?></span> seconds.</p>
        <a class="btn" href="<?= h(REDIRECT_TARGET) ?>">Go now</a>
        <p class="warn">For security, delete <strong>install.php</strong> after confirming login works.</p>
    </div>
    <script>
        (function () {
            let seconds = <?= (int) REDIRECT_SECONDS ?>;
            const el = document.getElementById('countdown');
            const timer = setInterval(() => {
                seconds--;
                if (el) el.textContent = String(Math.max(seconds, 0));
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = <?= json_encode(REDIRECT_TARGET) ?>;
                }
            }, 1000);
        })();
    </script>
</body>
</html>
<?php
}

function normalizePostString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

if (isset($_GET['reset'])) {
    resetInstallerState();
    session_destroy();
    header('Location: install.php');
    exit;
}

if (installedAlready()) {
    renderInstalledPage();
    exit;
}

ensureCsrfToken();

$errors = [];
$success = false;

$dbHost = (string)($_SESSION['db_host'] ?? 'localhost');
$dbName = (string)($_SESSION['db_name'] ?? '');
$dbUser = (string)($_SESSION['db_user'] ?? '');
$dbPass = (string)($_SESSION['db_pass'] ?? '');

$step = (int)($_SESSION['install_step'] ?? 1);
if ($step !== 2) {
    $step = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    csrfCheck();

    $dbHost = normalizePostString('db_host');
    $dbName = normalizePostString('db_name');
    $dbUser = normalizePostString('db_user');
    $dbPass = (string)($_POST['db_pass'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '' || trim($dbPass) === '') {
        $errors[] = 'All database fields are required.';
        $step = 1;
    } else {
        try {
            $pdo = dbPdo($dbHost, $dbUser, $dbPass);

            if (!databaseExists($pdo, $dbName)) {
                $pdo->exec(
                    "CREATE DATABASE `" . str_replace('`', '``', $dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }

            $pdo->exec("USE `" . str_replace('`', '``', $dbName) . "`");

            $_SESSION['db_host'] = $dbHost;
            $_SESSION['db_name'] = $dbName;
            $_SESSION['db_user'] = $dbUser;
            $_SESSION['db_pass'] = $dbPass;
            $_SESSION['db_has_tables'] = databaseHasTables($pdo, $dbName);
            $_SESSION['install_step'] = 2;

            header('Location: install.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
            $step = 1;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2'])) {
    csrfCheck();

    $adminUser = normalizePostString('admin_user');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminConfirm = (string)($_POST['admin_confirm'] ?? '');
    $confirmDrop = isset($_POST['confirm_drop']) && $_POST['confirm_drop'] === '1';

    if ($adminUser === '' || trim($adminPass) === '' || trim($adminConfirm) === '') {
        $errors[] = 'All admin fields are required.';
    } elseif ($adminPass !== $adminConfirm) {
        $errors[] = 'Admin passwords do not match.';
    } elseif (mb_strlen($adminPass) < 6) {
        $errors[] = 'Admin password must be at least 6 characters.';
    } elseif (!empty($_SESSION['db_has_tables']) && !$confirmDrop) {
        $errors[] = 'You must confirm that you want to delete existing tables.';
    } else {
        try {
            $dbHost = (string)($_SESSION['db_host'] ?? '');
            $dbName = (string)($_SESSION['db_name'] ?? '');
            $dbUser = (string)($_SESSION['db_user'] ?? '');
            $dbPass = (string)($_SESSION['db_pass'] ?? '');

            if ($dbHost === '' || $dbName === '' || $dbUser === '') {
                throw new RuntimeException('Database session data expired. Please start again.');
            }

            $pdo = dbPdo($dbHost, $dbUser, $dbPass);
            $pdo->exec("USE `" . str_replace('`', '``', $dbName) . "`");

            if (!empty($_SESSION['db_has_tables']) && $confirmDrop) {
                dropAllTables($pdo, $dbName);
            }

            importSqlFile($pdo, INSTALL_SQL_FILE);

            // Register default media files and set them as system defaults when the columns exist.
            ensureDirectory(MEDIA_DIR);

            $profileImageId = findDefaultImage($pdo, 'default-profile');
            $aboutImageId   = findDefaultImage($pdo, 'default-about');
            $coverImageId   = findDefaultImage($pdo, 'default-cover');
            $logoImageId    = findDefaultImage($pdo, 'default-logo');
            $faviconImageId  = findDefaultImage($pdo, 'default-favicon');
            $socialImageId  = findDefaultImage($pdo, 'default-social');

            if ($profileImageId !== null) {
                setSingleValue($pdo, 'profile', 'profile_image_id', $profileImageId);
            }
            if ($aboutImageId !== null) {
                setSingleValue($pdo, 'profile', 'about_image_id', $aboutImageId);
            }
            if ($coverImageId !== null) {
                setSingleValue($pdo, 'profile', 'cover_image_id', $coverImageId);
            }
            if ($logoImageId !== null) {
                setSingleValue($pdo, 'appearance', 'logo_media_id', $logoImageId);
            }
            if ($faviconImageId !== null) {
                setSingleValue($pdo, 'appearance', 'favicon_media_id', $faviconImageId);
            }
            if ($socialImageId !== null) {
                setSingleValue($pdo, 'seo', 'social_image_media_id', $socialImageId);
            }

            $slides = findDefaultSlides($pdo);
            if ($slides && tableExists($pdo, 'cover_slides')) {
                $hasMediaId = columnExists($pdo, 'cover_slides', 'media_id');
                $hasOrder   = columnExists($pdo, 'cover_slides', 'display_order');
                if ($hasMediaId && $hasOrder) {
                    $stmt = $pdo->prepare('INSERT INTO cover_slides (media_id, display_order) VALUES (:media_id, :display_order)');
                    foreach ($slides as $slide) {
                        $stmt->execute([
                            ':media_id' => $slide['media_id'],
                            ':display_order' => $slide['display_order'],
                        ]);
                    }
                }
            }

            $hashed = password_hash($adminPass, PASSWORD_DEFAULT);

            if (!tableExists($pdo, 'users')) {
                throw new RuntimeException('The install SQL did not create a users table.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)'
            );
            $stmt->execute([
                ':username' => $adminUser,
                ':password_hash' => $hashed,
            ]);

            writeConfigFile($dbHost, $dbName, $dbUser, $dbPass);

            resetInstallerState();
            session_destroy();

            $success = true;
        } catch (Throwable $e) {
            $errors[] = isPdoError($e instanceof PDOException ? $e : new PDOException($e->getMessage()));
        }
    }
}

$currentStep = $step;

if ($success) {
    renderSuccessPage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation</title>
    <style>
        :root {
            color-scheme: light;
            --bg1: #667eea;
            --bg2: #764ba2;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --blue: #2563eb;
            --blue2: #1d4ed8;
            --gray: #e5e7eb;
            --grayText: #374151;
            --warnBg: #fffbeb;
            --warnBorder: #f59e0b;
            --warnText: #92400e;
            --errBg: #fef2f2;
            --errBorder: #fca5a5;
            --errText: #991b1b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--bg1) 0%, var(--bg2) 100%);
            padding: 24px;
            color: var(--text);
        }
        .shell {
            width: min(620px, 100%);
        }
        .card {
            background: var(--card);
            border-radius: 22px;
            box-shadow: 0 24px 70px rgba(0,0,0,.25);
            padding: 30px;
        }
        .title {
            margin: 0 0 8px;
            text-align: center;
            font-size: 28px;
            font-weight: 800;
        }
        .subtitle {
            margin: 0 0 22px;
            text-align: center;
            color: var(--muted);
            line-height: 1.6;
        }
        .steps {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 0 0 26px;
        }
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: #d1d5db;
            transition: .25s ease;
        }
        .dot.active { width: 34px; background: var(--blue); }
        .dot.done { background: #10b981; }
        .row { margin-bottom: 16px; }
        label {
            display: block;
            margin: 0 0 7px;
            font-weight: 700;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            background: #fff;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }
        .help {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }
        .error {
            background: var(--errBg);
            border: 1px solid var(--errBorder);
            color: var(--errText);
            border-radius: 14px;
            padding: 13px 14px;
            margin-bottom: 16px;
        }
        .warning {
            background: var(--warnBg);
            border: 1px solid var(--warnBorder);
            color: var(--warnText);
            border-radius: 14px;
            padding: 14px;
            margin: 0 0 18px;
        }
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }
        .btn {
            appearance: none;
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform .15s ease, background .2s ease, opacity .2s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary {
            background: var(--blue);
            color: #fff;
            flex: 1;
        }
        .btn-primary:hover { background: var(--blue2); }
        .btn-secondary {
            background: #e5e7eb;
            color: var(--grayText);
            min-width: 120px;
        }
        .btn-secondary:hover { background: #d1d5db; }
        .panel { display: none; }
        .panel.active { display: block; }
        .checkbox {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-top: 12px;
        }
        .checkbox input {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }
        .footer-note {
            margin-top: 18px;
            text-align: center;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.5;
        }
        .small-link {
            display: inline-block;
            margin-top: 12px;
            color: var(--blue);
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <div class="steps" aria-hidden="true">
            <div class="dot <?= $currentStep === 1 ? 'active' : 'done' ?>"></div>
            <div class="dot <?= $currentStep === 2 ? 'active' : '' ?>"></div>
        </div>

        <h1 class="title"><?= $currentStep === 1 ? 'Database Setup' : 'Admin Account' ?></h1>
        <p class="subtitle">
            <?= $currentStep === 1
                ? 'Enter your database credentials to begin installation.'
                : 'Create the administrator account and complete the installation.' ?>
        </p>

        <?php if ($errors): ?>
            <div class="error">
                <?= h(implode(' ', $errors)) ?>
            </div>
        <?php endif; ?>

        <div class="panel <?= $currentStep === 1 ? 'active' : '' ?>" id="step-1">
            <form method="post" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
                <input type="hidden" name="step1" value="1">

                <div class="row">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?= h($dbHost) ?>" placeholder="localhost" required>
                </div>

                <div class="row">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="<?= h($dbName) ?>" placeholder="your_database" required>
                </div>

                <div class="row">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" value="<?= h($dbUser) ?>" placeholder="database_user" required>
                </div>

                <div class="row">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= h($dbPass) ?>" placeholder="database_password" required>
                    <div class="help">All fields are required.</div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Continue</button>
            </form>
        </div>

        <div class="panel <?= $currentStep === 2 ? 'active' : '' ?>" id="step-2">
            <form method="post" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
                <input type="hidden" name="step2" value="1">

                <?php if (!empty($_SESSION['db_has_tables'])): ?>
                    <div class="warning">
                        <strong>Warning:</strong> The selected database already contains tables.
                        Continuing will remove the existing tables and replace them with the new schema.
                        <label class="checkbox">
                            <input type="checkbox" name="confirm_drop" value="1" required>
                            <span>I understand and want to delete the existing tables.</span>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <label for="admin_user">Admin Username</label>
                    <input type="text" id="admin_user" name="admin_user" value="admin" required>
                </div>

                <div class="row">
                    <label for="admin_pass">Admin Password</label>
                    <input type="password" id="admin_pass" name="admin_pass" placeholder="Minimum 6 characters" required>
                </div>

                <div class="row">
                    <label for="admin_confirm">Confirm Admin Password</label>
                    <input type="password" id="admin_confirm" name="admin_confirm" placeholder="Repeat the password" required>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="backBtn">Back</button>
                    <button type="submit" class="btn btn-primary">Install</button>
                </div>
            </form>

            <p class="footer-note">
                After installation, the site will redirect to the admin panel in <?= (int) REDIRECT_SECONDS ?> seconds.
            </p>
        </div>

        <a class="small-link" href="?reset=1">Reset</a>
    </div>
</div>

<script>
(function () {
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const backBtn = document.getElementById('backBtn');

    if (backBtn && step1 && step2) {
        backBtn.addEventListener('click', function () {
            step2.classList.remove('active');
            step1.classList.add('active');
            const firstInput = step1.querySelector('input');
            if (firstInput) firstInput.focus();
        });
    }

    // Basic client-side blocking so blank fields do not submit.
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', function (e) {
            const requiredFields = Array.from(form.querySelectorAll('input[required]'));
            for (const input of requiredFields) {
                if (!String(input.value || '').trim()) {
                    e.preventDefault();
                    input.focus();
                    alert('Please fill in all required fields.');
                    return;
                }
            }

            const pass = document.getElementById('admin_pass');
            const confirm = document.getElementById('admin_confirm');
            if (pass && confirm && form.querySelector('input[name="step2"]')) {
                if (pass.value.length < 6) {
                    e.preventDefault();
                    alert('Admin password must be at least 6 characters.');
                    pass.focus();
                    return;
                }
                if (pass.value !== confirm.value) {
                    e.preventDefault();
                    alert('Admin passwords do not match.');
                    confirm.focus();
                    return;
                }
            }
        });
    });
})();
</script>
</body>
</html>
