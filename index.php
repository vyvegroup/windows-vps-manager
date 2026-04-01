<?php
declare(strict_types=1);
/**
 * Windows VPS Manager - Web Control Panel
 * PHP 8.3+ | Mobile-First Design
 * 
 * Token được mã hóa AES-256-CBC khi lưu trữ.
 * KHÔNG chứa token plain text trong source code.
 */

// === Hằng số ===
define('APP_VERSION', '2.0.0');
define('SESSION_LIFETIME', 7200); // 2 giờ
define('CONFIG_PATH', __DIR__ . '/config.php');
define('DATA_PATH', __DIR__ . '/data');
define('CACHE_PATH', DATA_PATH . '/cache.json');

// === Bật session ===
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

// === Autoload helpers ===
require_once __DIR__ . '/helpers.php';

// === Routing ===
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$page = $_GET['page'] ?? 'dashboard';

// === Xử lý POST actions ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    match ($action) {
        'setup'       => handleSetup(),
        'login'       => handleLogin(),
        'create_vps'  => handleCreateVPS(),
        'stop_vps'    => handleStopVPS(),
        'settings'    => handleSettings(),
        'logout'      => handleLogout(),
        default       => null,
    };
    exit;
}

// === Kiểm tra cài đặt ===
$setupComplete = file_exists(CONFIG_PATH) && isEncryptedConfigValid();
if (!$setupComplete && $page !== 'setup') {
    redirect('?page=setup');
}

// === Render page ===
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🖥️ VPS Manager</title>
    <style><?= getInlineCSS() ?></style>
</head>
<body>
    <?= match ($page) {
        'setup'      => renderSetupPage(),
        'login'      => renderLoginPage(),
        'create'     => renderCreateVPSPage(),
        'vps'        => renderVPSDetailPage(),
        'settings'   => renderSettingsPage(),
        'logs'       => renderLogsPage(),
        default      => renderDashboardPage(),
    } ?>
    <script><?= getInlineJS() ?></script>
</body>
</html>
