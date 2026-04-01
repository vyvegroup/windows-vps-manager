<?php
declare(strict_types=1);
/**
 * Helper Functions - VPS Manager
 */

// === Mã hóa / Giải mã AES-256-CBC ===

function getEncryptionKey(): string
{
    $config = require CONFIG_PATH;
    $key = $config['encryption_key'] ?? '';
    if (strlen($key) < 32) {
        $key = hash('sha256', $key . 'vps_manager_salt_2024', true);
    }
    if (strlen($key) < 32) {
        $key = str_pad($key, 32, "\0");
    }
    return substr($key, 0, 32);
}

function encryptData(string $plainText): string
{
    $key = getEncryptionKey();
    $iv = random_bytes(16);
    $cipherText = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipherText === false) throw new RuntimeException('Encryption failed');
    return base64_encode($iv . $cipherText);
}

function decryptData(string $encrypted): string
{
    $key = getEncryptionKey();
    $data = base64_decode($encrypted, true);
    if ($data === false || strlen($data) < 17) throw new RuntimeException('Invalid encrypted data');
    $iv = substr($data, 0, 16);
    $cipherText = substr($data, 16);
    $plainText = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plainText === false) throw new RuntimeException('Decryption failed');
    return $plainText;
}

function isEncryptedConfigValid(): bool
{
    try {
        $config = require CONFIG_PATH;
        return !empty($config['encrypted_token']) || !empty($config['github_token']);
    } catch (\Throwable) {
        return false;
    }
}

// === GitHub API ===

function getGitHubToken(): string
{
    $config = require CONFIG_PATH;
    // Ưu tiên giải mã từ encrypted_token
    if (!empty($config['encrypted_token'])) {
        return decryptData($config['encrypted_token']);
    }
    // Fallback cho config cũ (plain text - migrate sang encrypted)
    if (!empty($config['github_token'])) {
        migrateToEncrypted($config['github_token']);
        return $config['github_token'];
    }
    return '';
}

function getGitHubRepo(): string
{
    $config = require CONFIG_PATH;
    return $config['github_repo'] ?? '';
}

function migrateToEncrypted(string $token): void
{
    $config = require CONFIG_PATH;
    $config['encrypted_token'] = encryptData($token);
    unset($config['github_token']); // Xóa plain text
    saveConfig($config);
}

function saveConfig(array $config): void
{
    $content = "<?php\n// Auto-generated encrypted config - DO NOT edit manually\n// Created: " . date('Y-m-d H:i:s') . "\nreturn " . var_export($config, true) . ";\n";
    file_put_contents(CONFIG_PATH, $content, LOCK_EX);
}

function githubApiCall(string $endpoint, string $method = 'GET', array $data = null): array
{
    $token = getGitHubToken();
    $repo = getGitHubRepo();
    
    $url = "https://api.github.com/repos/{$repo}/actions{$endpoint}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: VPS-Manager-PHP/2.0',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => true, 'message' => "cURL Error: {$error}"];
    }
    
    $body = json_decode($response, true);
    if ($httpCode >= 400) {
        return ['error' => true, 'message' => $body['message'] ?? "HTTP {$httpCode}", 'code' => $httpCode];
    }
    
    return ['error' => false, 'data' => $body ?? [], 'code' => $httpCode];
}

function triggerWorkflow(string $workflowFile, array $inputs): array
{
    return githubApiCall("/workflows/{$workflowFile}/dispatches", 'POST', [
        'ref' => 'main',
        'inputs' => $inputs,
    ]);
}

function getWorkflowRuns(string $workflow = '', string $status = '', int $perPage = 10): array
{
    $endpoint = "/runs?per_page={$perPage}";
    if ($workflow) $endpoint .= "&workflow={$workflow}";
    if ($status)   $endpoint .= "&status={$status}";
    return githubApiCall($endpoint);
}

function getRunJobs(int $runId): array
{
    return githubApiCall("/runs/{$runId}/jobs");
}

function getRunLogs(int $runId): array
{
    return githubApiCall("/runs/{$runId}/logs", 'GET');
}

function cancelWorkflowRun(int $runId): array
{
    return githubApiCall("/runs/{$runId}/cancel", 'POST');
}

// === Cache ===

function saveCache(array $data): void
{
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0700, true);
    file_put_contents(CACHE_PATH, json_encode($data), LOCK_EX);
}

function loadCache(): array
{
    if (!file_exists(CACHE_PATH)) return [];
    $data = json_decode(file_get_contents(CACHE_PATH), true);
    return is_array($data) ? $data : [];
}

// === Session helpers ===

function isLoggedIn(): bool
{
    return !empty($_SESSION['vps_auth']);
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        redirect('?page=login');
    }
}

function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

// === Action Handlers ===

function handleSetup(): void
{
    $token = trim($_POST['github_token'] ?? '');
    $repo = trim($_POST['github_repo'] ?? '');
    $encKey = $_POST['encryption_key'] ?? bin2hex(random_bytes(16));
    
    if (empty($token) || empty($repo)) {
        $_SESSION['flash_error'] = 'Vui lòng nhập đầy đủ thông tin!';
        redirect('?page=setup');
    }
    
    // Xác thực token
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.github.com/user',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: VPS-Manager/2.0',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $_SESSION['flash_error'] = 'Token GitHub không hợp lệ hoặc đã hết hạn!';
        redirect('?page=setup');
    }
    
    $userData = json_decode($response, true);
    
    // Lưu config với token đã mã hóa
    $config = [
        'encrypted_token' => encryptData($token),
        'github_repo'     => $repo,
        'encryption_key'  => $encKey,
        'ngrok_token'     => '',
        'timezone'        => 'Asia/Ho_Chi_Minh',
        'created_at'      => date('Y-m-d H:i:s'),
        'github_user'     => $userData['login'] ?? 'unknown',
    ];
    
    saveConfig($config);
    $_SESSION['vps_auth'] = true;
    $_SESSION['flash_success'] = 'Cài đặt thành công! Đã kết nối GitHub: @' . ($userData['login'] ?? '');
    redirect('?page=dashboard');
}

function handleLogin(): void
{
    $password = $_POST['password'] ?? '';
    $config = file_exists(CONFIG_PATH) ? require CONFIG_PATH : [];
    $appKey = $config['encryption_key'] ?? '';
    
    // Mật khẩu đơn giản: encryption_key (user cần nhớ key đã tạo)
    if (hash('sha256', $password) === hash('sha256', $appKey) || $password === $appKey) {
        $_SESSION['vps_auth'] = true;
        redirect('?page=dashboard');
    }
    
    $_SESSION['flash_error'] = 'Sai mật khẩu!';
    redirect('?page=login');
}

function handleLogout(): void
{
    session_destroy();
    redirect('?page=login');
}

function handleCreateVPS(): void
{
    requireAuth();
    
    $name = trim($_POST['vps_name'] ?? 'windows-vps-' . date('Ymd-His'));
    $password = $_POST['rdp_password'] ?? '';
    $lifetime = (int)($_POST['vps_lifetime'] ?? 60);
    $ngrok = trim($_POST['ngrok_token'] ?? '');
    
    if (strlen($password) < 8) {
        $_SESSION['flash_error'] = 'Mật khẩu RDP phải có ít nhất 8 ký tự!';
        redirect('?page=create');
    }
    
    $lifetime = min(max($lifetime, 10), 360);
    
    $inputs = [
        'vps_name'    => $name,
        'rdp_password' => $password,
        'vps_lifetime' => (string)$lifetime,
        'timezone'    => 'SE Asia Standard Time',
    ];
    
    if (!empty($ngrok)) {
        $inputs['ngrok_token'] = $ngrok;
    }
    
    $result = triggerWorkflow('provision-vps.yml', $inputs);
    
    if ($result['error']) {
        $_SESSION['flash_error'] = 'Lỗi khi tạo VPS: ' . $result['message'];
    } else {
        $_SESSION['flash_success'] = "Đã gửi yêu cầu tạo VPS '{$name}'! VPS sẽ khởi động trong 1-2 phút.";
    }
    
    redirect('?page=dashboard');
}

function handleStopVPS(): void
{
    requireAuth();
    
    $runId = (int)($_POST['run_id'] ?? 0);
    if ($runId <= 0) {
        $_SESSION['flash_error'] = 'Run ID không hợp lệ!';
        redirect('?page=dashboard');
    }
    
    $result = cancelWorkflowRun($runId);
    
    if ($result['error']) {
        $_SESSION['flash_error'] = 'Lỗi khi dừng VPS: ' . $result['message'];
    } else {
        $_SESSION['flash_success'] = "Đã gửi yêu cầu dừng VPS (Run #{$runId}).";
    }
    
    redirect('?page=dashboard');
}

function handleSettings(): void
{
    requireAuth();
    
    $config = require CONFIG_PATH;
    $ngrok = trim($_POST['ngrok_token'] ?? '');
    $newToken = trim($_POST['github_token'] ?? '');
    
    if (!empty($newToken)) {
        $config['encrypted_token'] = encryptData($newToken);
    }
    if (!empty($ngrok)) {
        $config['ngrok_token'] = encryptData($ngrok);
    }
    
    saveConfig($config);
    $_SESSION['flash_success'] = 'Đã lưu cài đặt!';
    redirect('?page=settings');
}

// === Status helpers ===

function getVPSStatusText(string $status): string
{
    return match ($status) {
        'in_progress', 'queued' => '🟢 Đang chạy',
        'completed'    => '🔴 Đã tắt',
        'success'      => '✅ Hoàn thành',
        'failure'      => '❌ Lỗi',
        'cancelled'    => '🟡 Đã hủy',
        default        => '⚪ ' . ucfirst($status),
    };
}

function formatDuration(string $startedAt, ?string $endedAt = null): string
{
    $start = strtotime($startedAt);
    $end = $endedAt ? strtotime($endedAt) : time();
    $diff = $end - $start;
    $hours = floor($diff / 3600);
    $mins = floor(($diff % 3600) / 60);
    return ($hours > 0 ? "{$hours}h " : '') . "{$mins}m";
}
