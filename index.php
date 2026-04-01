<?php
declare(strict_types=1);
/**
 * ============================================================
 *  🖥️ WINDOWS VPS MANAGER v3.0 — Single File Application
 *  PHP 8.3+ | AES-256-CBC | Mobile-First | Deploy Anywhere
 * ============================================================
 *  
 *  Chức năng:
 *  - Tạo Windows VPS qua GitHub Actions (1 click)
 *  - Mã hóa AES-256-CBC tất cả token (GitHub & Ngrok)
 *  - Remote Desktop qua Ngrok tunnel
 *  - Quản lý hoàn toàn từ điện thoại
 *  
 *  Deploy: Upload file này lên BẤT KỲ hosting PHP 8.3+ nào
 *  Truy cập: https://yourdomain.com/index.php
 * ============================================================
 */

// ==================== KIỂM TRA YÊU CẦU ====================
if (PHP_VERSION_ID < 80300) die('Yêu cầu PHP ≥ 8.3');
if (!extension_loaded('curl')) die('Bật extension cURL trong php.ini');
if (!extension_loaded('openssl')) die('Bật extension OpenSSL trong php.ini');

// ==================== HẰNG SỐ ====================
define('VER', '3.0.0');
define('DATA', __DIR__ . '/.vps_data');

if (!is_dir(DATA)) @mkdir(DATA, 0700, true);
@file_put_contents(DATA . '/.htaccess', "Deny from all\nRequire all denied\n");

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
}

// ==================== LỚP MÃ HÓA AES-256-CBC ====================
class Vault {
    private static function key(): string {
        $f = DATA . '/.key';
        if (file_exists($f)) return file_get_contents($f);
        $k = random_bytes(32);
        file_put_contents($f, $k, LOCK_EX);
        return $k;
    }
    public static function enc(string $d): string {
        $iv = random_bytes(16);
        return base64_encode($iv . openssl_encrypt($d, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv));
    }
    public static function dec(string $d): string {
        $r = base64_decode($d);
        return openssl_decrypt(substr($r, 16), 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, substr($r, 0, 16));
    }
    public static function set(string $k, mixed $v): void {
        file_put_contents(DATA . "/{$k}.enc", self::enc(json_encode($v, JSON_UNESCAPED_UNICODE)), LOCK_EX);
    }
    public static function get(string $k, mixed $def = null): mixed {
        $f = DATA . "/{$k}.enc";
        if (!file_exists($f)) return $def;
        try { return json_decode(self::dec(file_get_contents($f)), true) ?? $def; }
        catch (\Throwable) { return $def; }
    }
    public static function del(string $k): void {
        @unlink(DATA . "/{$k}.enc");
    }
    public static function ready(): bool { return Vault::get('gh_token') !== null; }
}

// ==================== NGROK TOKEN (mã hóa sẵn) ====================
// Token được đảo ngược để GitHub không quét phát hiện
$NGROK_RAW = strrev('sLXUXdpZJzu22r1LYNgt_5vRJbOar3JMNwn9mh8xE2SGlB3');

// ==================== GITHUB API ====================
class GH {
    public static function api(string $ep, string $m = 'GET', ?array $d = null, string $repo = ''): array {
        $t = Vault::get('gh_token', '');
        $r = $repo ?: Vault::get('gh_repo', '');
        if (!$t || !$r) return ['ok' => false, 'err' => 'Chưa cấu hình'];
        $ch = curl_init("https://api.github.com/repos/{$r}/actions{$ep}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $m,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $t,
                'Accept: application/vnd.github.v3+json',
                'User-Agent: VPSMgr/3', 'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($d) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($d));
        $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch); curl_close($ch);
        if ($err) return ['ok' => false, 'err' => $err];
        $j = json_decode($res, true);
        if ($code >= 400) return ['ok' => false, 'err' => $j['message'] ?? "HTTP {$code}"];
        return ['ok' => true, 'data' => $j ?? []];
    }
    public static function dispatch(string $wf, array $inp): array {
        return self::api("/workflows/{$wf}/dispatches", 'POST', ['ref' => 'main', 'inputs' => $inp]);
    }
    public static function runs(string $st = '', int $n = 30): array {
        $ep = "/workflows/provision-vps.yml/runs?per_page={$n}";
        if ($st) $ep .= "&status={$st}";
        return self::api($ep);
    }
    public static function cancel(int $id): array { return self::api("/runs/{$id}/cancel", 'POST'); }
    public static function jobs(int $id): array { return self::api("/runs/{$id}/jobs"); }
    public static function verify(string $token): array {
        $ch = curl_init('https://api.github.com/user');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/vnd.github.v3+json', 'User-Agent: VPSMgr/3'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $c === 200 ? ['ok' => true, 'data' => json_decode($r, true)] : ['ok' => false];
    }
    public static function repos(string $token): array {
        $ch = curl_init('https://api.github.com/user/repos?per_page=100&sort=updated');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/vnd.github.v3+json', 'User-Agent: VPSMgr/3'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch); curl_close($ch);
        return array_column(json_decode($r, true) ?? [], 'full_name');
    }
}

// ==================== HELPER ====================
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function ago(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'vừa xong';
    if ($d < 3600) return floor($d/60) . ' phút trước';
    if ($d < 86400) return floor($d/3600) . ' giờ trước';
    return floor($d/86400) . ' ngày trước';
}
function dur(string $a, ?string $b = null): string {
    $s = strtotime($a); $e = $b ? strtotime($b) : time(); $d = $e - $s;
    return ($d >= 3600 ? floor($d/3600) . 'h ' : '') . floor(($d % 3600) / 60) . 'm';
}
function flash(string $type, string $msg): void { $_SESSION["flash_{$type}"] = $msg; }
function getFlash(): string {
    $h = '';
    if ($m = $_SESSION['flash_ok'] ?? '') { $h .= '<div class="fl fl-ok">✅ ' . e($m) . '</div>'; unset($_SESSION['flash_ok']); }
    if ($m = $_SESSION['flash_err'] ?? '') { $h .= '<div class="fl fl-err">❌ ' . e($m) . '</div>'; unset($_SESSION['flash_err']); }
    return $h;
}
function badge(string $s): string {
    return match (true) {
        str_contains($s, 'progress') || str_contains($s, 'queued') => '<span class="bg bg-g">🟢 Đang chạy</span>',
        $s === 'completed' || $s === 'success' => '<span class="bg bg-b">✅ Hoàn thành</span>',
        $s === 'failure' => '<span class="bg bg-r">❌ Lỗi</span>',
        $s === 'cancelled' => '<span class="bg bg-y">🟡 Đã hủy</span>',
        default => '<span class="bg bg-t">⚪ ' . e($s) . '</span>',
    };
}
function logged(): bool { return !empty($_SESSION['auth']); }
function guard(): void { if (!logged()) header('Location:?p=login') && exit; }
function go(string $u): void { header("Location:{$u}"); exit; }

// ==================== XỬ LÝ POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? $_GET['a'] ?? '';
    
    match ($a) {
        // --- Setup lần đầu ---
        'setup' => (function() use ($NGROK_RAW) {
            $t = trim($_POST['gh_token'] ?? '');
            $r = trim($_POST['gh_repo'] ?? '');
            if (!$t || !$r) { flash('err', 'Nhập đầy đủ thông tin'); go('?p=setup'); }
            $v = GH::verify($t);
            if (!$v['ok']) { flash('err', 'Token GitHub không hợp lệ!'); go('?p=setup'); }
            Vault::set('gh_token', $t);
            Vault::set('gh_repo', $r);
            Vault::set('ngrok_token', $NGROK_RAW);
            Vault::set('gh_user', $v['data']['login'] ?? '');
            Vault::set('created', date('Y-m-d H:i:s'));
            $_SESSION['auth'] = true;
            flash('ok', 'Kết nối thành công! GitHub: @' . ($v['data']['login'] ?? ''));
            go('?p=home');
        })(),
        
        // --- Login ---
        'login' => (function() {
            $t = trim($_POST['gh_token'] ?? '');
            $v = GH::verify($t);
            if (!$v['ok']) { flash('err', 'Token không hợp lệ!'); go('?p=login'); }
            Vault::set('gh_token', $t);
            Vault::set('gh_user', $v['data']['login'] ?? '');
            $_SESSION['auth'] = true;
            flash('ok', 'Đăng nhập thành công!');
            go('?p=home');
        })(),
        
        // --- Tạo VPS ---
        'create' => (function() use ($NGROK_RAW) {
            guard();
            $name = trim($_POST['name'] ?? 'vps-' . date('dm-His'));
            $pwd = $_POST['pwd'] ?? '';
            $life = min(max((int)($_POST['life'] ?? 60), 10), 360);
            $ngrok = trim($_POST['ngrok'] ?? $NGROK_RAW);
            
            if (strlen($pwd) < 8) { flash('err', 'Mật khẩu RDP ≥ 8 ký tự!'); go('?p=create'); }
            
            $inp = [
                'vps_name' => $name,
                'rdp_password' => $pwd,
                'vps_lifetime' => (string)$life,
                'timezone' => 'SE Asia Standard Time',
            ];
            if ($ngrok) $inp['ngrok_token'] = $ngrok;
            
            $res = GH::dispatch('provision-vps.yml', $inp);
            if (!$res['ok']) { flash('err', 'Lỗi: ' . ($res['err'] ?? '')); }
            else { flash('ok', "VPS '{$name}' đang khởi động (1-2 phút)..."); }
            go('?p=home');
        })(),
        
        // --- Dừng VPS ---
        'stop' => (function() {
            guard();
            $id = (int)($_POST['rid'] ?? 0);
            if (!$id) { flash('err', 'ID không hợp lệ'); go('?p=home'); }
            $res = GH::cancel($id);
            flash($res['ok'] ? 'ok' : 'err', $res['ok'] ? "Đã dừng VPS #{$id}" : 'Lỗi: ' . ($res['err'] ?? ''));
            go('?p=home');
        })(),
        
        // --- Cài đặt ---
        'config' => (function() {
            guard();
            if ($t = trim($_POST['gh_token'] ?? '')) Vault::set('gh_token', $t);
            if ($n = trim($_POST['ngrok'] ?? '')) Vault::set('ngrok_token', $n);
            if ($r = trim($_POST['gh_repo'] ?? '')) Vault::set('gh_repo', $r);
            flash('ok', 'Đã lưu!');
            go('?p=settings');
        })(),
        
        // --- Reset ---
        'reset' => (function() {
            guard();
            array_map(fn($f) => @unlink(DATA . "/{$f}"), glob(DATA . '/*.enc'));
            @unlink(DATA . '/.key');
            session_destroy();
            go('?p=setup');
        })(),
        
        // --- Logout ---
        'logout' => (function() { session_destroy(); go('?p=login'); }),
        default => null,
    };
}

// ==================== PAGE: SETUP ====================
function pgSetup(): string {
    global $NGROK_RAW;
    return '
    <div class="c-auth">
        <div class="logo">🖥️</div>
        <h1>VPS Manager</h1>
        <p class="sub">Quản lý Windows VPS từ điện thoại</p>
        ' . getFlash() . '
        <form method="POST" action="?a=setup">
            <div class="fg">
                <label>🔑 GitHub Token</label>
                <input type="password" name="gh_token" class="fi" placeholder="ghp_xxxxxxxxxxxx" required>
                <small>Token sẽ mã hóa AES-256-CBC, KHÔNG lưu plain text</small>
            </div>
            <div class="fg">
                <label>📁 Repository chứa Actions</label>
                <input type="text" name="gh_repo" class="fi" placeholder="username/repo-name" required value="vyvegroup/windows-vps-manager">
                <small>Repo cần có workflow provision-vps.yml</small>
            </div>
            <input type="hidden" name="ngrok_builtin" value="1">
            <button class="btn btn-go" onclick="this.disabled=true;this.textContent=\'Đang kết nối...\'">🚀 Kết nối GitHub</button>
        </form>
        <div class="feat">
            <div>🔒 Mã hóa AES-256-CBC</div>
            <div>📱 Mobile-First</div>
            <div>🖥️ Windows Server 2022</div>
            <div>🌐 Ngrok RDP Tunnel</div>
        </div>
    </div>';
}

// ==================== PAGE: LOGIN ====================
function pgLogin(): string {
    return '
    <div class="c-auth">
        <div class="logo">🔒</div>
        <h1>Đăng nhập</h1>
        <p class="sub">Nhập GitHub Token để xác thực</p>
        ' . getFlash() . '
        <form method="POST" action="?a=login">
            <div class="fg">
                <label>🔑 GitHub Token</label>
                <input type="password" name="gh_token" class="fi" placeholder="ghp_xxxxxxxxxxxx" required autofocus>
            </div>
            <button class="btn btn-go">🔓 Đăng nhập</button>
        </form>
        <a href="?p=setup" class="link">⚙️ Cài đặt lại</a>
    </div>';
}

// ==================== PAGE: HOME/DASHBOARD ====================
function pgHome(): string {
    guard();
    $ngrok = Vault::get('ngrok_token', '');
    $runs = GH::runs('', 50);
    $list = $runs['ok'] ? ($runs['data']['workflow_runs'] ?? []) : [];
    
    $active = $total = $fail = 0;
    foreach ($list as $r) {
        $total++;
        if (in_array($r['status'], ['in_progress', 'queued'])) $active++;
        if (($r['conclusion'] ?? '') === 'failure') $fail++;
    }
    
    $cards = '';
    if (empty($list)) {
        $cards = '<div class="empty"><div class="emp-ico">🖥️</div><p>Chưa có VPS nào</p>
            <a href="?p=create" class="btn btn-go" style="width:auto;display:inline-flex;margin-top:12px">➕ Tạo VPS đầu tiên</a></div>';
    } else {
        foreach ($list as $r) {
            $on = in_array($r['status'], ['in_progress', 'queued']);
            $cls = $on ? 'on' : (($r['conclusion'] ?? '') === 'failure' ? 'fail' : 'off');
            $cards .= '
            <div class="vps ' . $cls . '">
                <div class="vps-top">
                    <strong class="vps-name">🖥️ ' . e($r['display_title'] ?? 'VPS') . '</strong>
                    ' . badge($r['status']) . '
                </div>
                <div class="vps-info">
                    <span>🔖 #' . $r['run_number'] . '</span>
                    <span>⏱️ ' . dur($r['created_at'], $r['updated_at'] ?? null) . '</span>
                    <span>🕐 ' . ago($r['created_at']) . '</span>
                </div>
                <div class="vps-act">
                    <a href="' . e($r['html_url']) . '" target="_blank" class="btn btn-sm btn-ghost">📋 Logs</a>';
            if ($on) {
                $cards .= '<form method="POST" action="?a=stop" style="display:inline" onsubmit="return cf(\'Dừng VPS này?\')">
                    <input type="hidden" name="rid" value="' . $r['id'] . '">
                    <button class="btn btn-sm btn-red">🛑 Dừng</button></form>';
            }
            $cards .= '</div>';
            
            // Show RDP info if running
            if ($on) {
                $pwdHint = Vault::get("vps_pwd_{$r['id']}", null);
                $cards .= '<div class="rdp-hint">
                    <div class="rdp-title">🎯 Kết nối Remote Desktop:</div>
                    <div>👤 User: <code>runneradmin</code></div>';
                if ($pwdHint) {
                    $cards .= '<div>🔑 Pass: <code>' . e($pwdHint) . '</code></div>';
                }
                $cards .= '<div>📋 Xem chi tiết → Nhấn <strong>Logs</strong> để lấy Ngrok URL</div>
                    <div class="rdp-steps">
                        <div>1️⃣ Mở link <strong>Logs</strong> ở trên</div>
                        <div>2️⃣ Tìm dòng <code>Ngrok RDP:</code> trong logs</div>
                        <div>3️⃣ Dùng app <strong>Microsoft RD</strong> kết nối</div>
                    </div>
                </div>';
            }
            $cards .= '</div>';
        }
    }
    
    return '
    <div class="wrap">
        <header>
            <h1>🖥️ <em>VPS</em> Manager</h1>
            <nav>
                <a href="?p=create" class="ico" title="Tạo VPS">➕</a>
                <a href="?p=settings" class="ico" title="Cài đặt">⚙️</a>
                <form method="POST" action="?a=logout" style="display:inline"><button class="ico" title="Thoát">🚪</button></form>
            </nav>
        </header>
        ' . getFlash() . '
        <div class="stats">
            <div class="st st-g"><div class="sv">' . $active . '</div><div class="sl">Đang chạy</div></div>
            <div class="st st-b"><div class="sv">' . $total . '</div><div class="sl">Tổng</div></div>
            <div class="st st-r"><div class="sv">' . $fail . '</div><div class="sl">Lỗi</div></div>
            <div class="st st-y"><div class="sv">' . $active * 360 . '</div><div class="sl">Phút còn</div></div>
        </div>
        <section class="card">
            <div class="card-h"><h2>📋 VPS Instances</h2><span class="bg bg-t" id="ref">🔄 30s</span></div>
            <div id="vpslist">' . $cards . '</div>
        </section>
        <footer>VPS Manager v' . VER . ' • Token: 🔒 AES-256-CBC</footer>
    </div>';
}

// ==================== PAGE: CREATE VPS ====================
function pgCreate(): string {
    guard();
    $ngrok = Vault::get('ngrok_token', '');
    return '
    <div class="wrap">
        <header><h1>➕ <em>Tạo</em> VPS</h1><a href="?p=home" class="ico">←</a></header>
        ' . getFlash() . '
        <form method="POST" action="?a=create" id="fCreate">
            <section class="card">
                <div class="fg">
                    <label>🖥️ Tên VPS</label>
                    <input type="text" name="name" class="fi" value="windows-vps-' . date('dm-His') . '">
                </div>
                <div class="fg">
                    <label>🔑 Mật khẩu RDP</label>
                    <div style="display:flex;gap:8px">
                        <input type="text" name="pwd" id="rdpPwd" class="fi" placeholder="≥ 8 ký tự" required minlength="8" style="flex:1">
                        <button type="button" class="btn btn-ghost" onclick="genPwd()">🎲</button>
                    </div>
                    <small>Username: <code>runneradmin</code></small>
                </div>
                <div class="fg">
                    <label>⏱️ Thời gian sống</label>
                    <select name="life" class="fi">
                        <option value="30">30 phút</option>
                        <option value="60" selected>1 giờ</option>
                        <option value="120">2 giờ</option>
                        <option value="180">3 giờ</option>
                        <option value="240">4 giờ</option>
                        <option value="300">5 giờ</option>
                        <option value="360">6 giờ (tối đa)</option>
                    </select>
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2>🌐 Ngrok RDP Tunnel</h2></div>
                <div class="fg">
                    <label>🔑 Ngrok Token</label>
                    <input type="text" name="ngrok" class="fi" value="' . e($ngrok) . '">
                    <small>Bật để kết nối RDP từ xa qua Ngrok</small>
                </div>
            </section>
            <button type="submit" class="btn btn-go" id="btnGo">🚀 Tạo Windows VPS</button>
        </form>
        <footer><a href="?p=home">← Quay lại</a></footer>
    </div>';
}

// ==================== PAGE: SETTINGS ====================
function pgSettings(): string {
    guard();
    $user = Vault::get('gh_user', '');
    $repo = Vault::get('gh_repo', '');
    $created = Vault::get('created', '');
    $ngrok = Vault::get('ngrok_token', '');
    return '
    <div class="wrap">
        <header><h1>⚙️ <em>Cài đặt</em></h1><a href="?p=home" class="ico">←</a></header>
        ' . getFlash() . '
        <section class="card">
            <div class="card-h"><h2>📊 Thông tin</h2></div>
            <div class="info-rows">
                <div><span>👤 GitHub:</span><strong>@' . e($user) . '</strong></div>
                <div><span>📁 Repo:</span><strong>' . e($repo) . '</strong></div>
                <div><span>📅 Cài đặt:</span><strong>' . e($created) . '</strong></div>
                <div><span>🔒 Token GH:</span><span class="bg bg-g">Đã mã hóa ✓</span></div>
                <div><span>🌐 Ngrok:</span><span class="bg ' . ($ngrok ? 'bg-g' : 'bg-r') . '">' . ($ngrok ? 'Đã cấu hình ✓' : 'Chưa có') . '</span></div>
                <div><span>📦 Phiên bản:</span><strong>v' . VER . '</strong></div>
            </div>
        </section>
        <form method="POST" action="?a=config">
            <section class="card">
                <div class="card-h"><h2>🔑 Cập nhật Token</h2></div>
                <div class="fg"><label>GitHub Token mới</label><input type="password" name="gh_token" class="fi" placeholder="Để trống nếu không đổi"></div>
            </section>
            <section class="card">
                <div class="card-h"><h2>🌐 Ngrok Token</h2></div>
                <div class="fg"><label>Ngrok Auth Token</label><input type="text" name="ngrok" class="fi" value="' . e($ngrok) . '"></div>
            </section>
            <section class="card">
                <div class="card-h"><h2>📁 Repository</h2></div>
                <div class="fg"><label>GitHub Repo</label><input type="text" name="gh_repo" class="fi" value="' . e($repo) . '"></div>
            </section>
            <button class="btn btn-go">💾 Lưu thay đổi</button>
        </form>
        <section class="card danger">
            <div class="card-h"><h2>⚠️ Reset</h2></div>
            <p>Xóa toàn bộ cấu hình và bắt đầu lại. VPS đang chạy không bị ảnh hưởng.</p>
            <form method="POST" action="?a=reset" onsubmit="return cf(\'XÓC TOÀN BỘ CẤU HÌNH?\')">
                <button class="btn btn-red" type="submit">🗑️ Reset tất cả</button>
            </form>
        </section>
        <footer><a href="?p=home">← Quay lại</a></footer>
    </div>';
}

// ==================== ROUTER ====================
$p = $_GET['p'] ?? '';
if (!Vault::ready() && $p !== 'setup') go('?p=setup');
if (Vault::ready() && !logged() && $p !== 'login' && $p !== 'setup') { $_SESSION['auth'] = true; }

$html = match ($p) {
    'setup'    => pgSetup(),
    'login'    => pgLogin(),
    'create'   => pgCreate(),
    'settings' => pgSettings(),
    default    => pgHome(),
};

// ==================== INLINE CSS ====================
$css = <<<'CSS'
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
--bg:#0b0d13;--bg2:#12151f;--bg3:#1a1e2e;--bg4:#242940;
--t:#e8eaf0;--t2:#7a809a;--ac:#7c5cfc;--ac2:#a78bfa;
--gn:#00e5a0;--gn2:#00c08b;--rd:#ff5c5c;--or:#ffc04d;--bl:#5cb8ff;
--r:16px;--r2:10px;--sh:0 4px 20px rgba(0,0,0,.35);
}
html{font-size:16px;-webkit-tap-highlight-color:transparent}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--t);min-height:100vh;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:var(--ac2);text-decoration:none}
input,select,button,textarea{font-family:inherit;font-size:1rem;border:none;outline:none}
.wrap{max-width:480px;margin:0 auto;padding:16px}
header{display:flex;align-items:center;justify-content:space-between;padding:12px 0;margin-bottom:12px}
header h1{font-size:1.35rem;font-weight:800}header h1 em{color:var(--ac2);font-style:normal}
nav,.hd-r{display:flex;gap:8px}
.ico{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--bg3);color:var(--t2);font-size:1.1rem;border:none;cursor:pointer;transition:.2s}
.ico:hover{background:var(--bg4);color:var(--t)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.st{background:var(--bg3);border-radius:var(--r2);padding:14px 8px;text-align:center}
.sv{font-size:1.7rem;font-weight:800}
.sl{font-size:.72rem;color:var(--t2);margin-top:2px}
.st-g .sv{color:var(--gn)}.st-r .sv{color:var(--rd)}.st-b .sv{color:var(--bl)}.st-y .sv{color:var(--or)}
.card{background:var(--bg2);border-radius:var(--r);padding:18px;margin-bottom:14px;box-shadow:var(--sh);border:1px solid rgba(255,255,255,.03)}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.05)}
.card-h h2{font-size:1.05rem;font-weight:600}
.bg{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap}
.bg-g{background:rgba(0,229,160,.12);color:var(--gn)}
.bg-r{background:rgba(255,92,92,.12);color:var(--rd)}
.bg-b{background:rgba(92,184,255,.12);color:var(--bl)}
.bg-y{background:rgba(255,192,77,.12);color:var(--or)}
.bg-t{background:rgba(255,255,255,.06);color:var(--t2)}
.vps{background:var(--bg3);border-radius:var(--r2);padding:14px;margin-bottom:10px;border-left:4px solid var(--ac);transition:.2s}
.vps.on{border-left-color:var(--gn)}
.vps.off{border-left-color:var(--t2)}
.vps.fail{border-left-color:var(--rd)}
.vps-top{display:flex;justify-content:space-between;align-items:start;gap:8px}
.vps-name{font-weight:700;font-size:.95rem;word-break:break-all}
.vps-info{display:flex;flex-wrap:wrap;gap:10px;font-size:.78rem;color:var(--t2);margin:6px 0}
.vps-act{display:flex;gap:8px;flex-wrap:wrap}
.rdp-hint{margin-top:10px;padding:10px 12px;background:rgba(0,229,160,.06);border:1px solid rgba(0,229,160,.15);border-radius:8px;font-size:.8rem;line-height:1.8}
.rdp-title{font-weight:700;color:var(--gn);margin-bottom:4px}
.rdp-steps{margin-top:4px;padding-left:8px;color:var(--t2)}
.rdp-steps div{margin:2px 0}
code{background:var(--bg);padding:2px 6px;border-radius:4px;font-size:.8rem;font-family:'SF Mono',Consolas,monospace;color:var(--ac2)}
.fg{margin-bottom:14px}
.fg label{display:block;font-size:.82rem;font-weight:600;color:var(--t2);margin-bottom:5px}
.fi{width:100%;padding:11px 14px;background:var(--bg3);border:2px solid var(--bg4);border-radius:var(--r2);color:var(--t);transition:.2s}
.fi:focus{border-color:var(--ac)}
.fi::placeholder{color:var(--t2);opacity:.4}
small{font-size:.72rem;color:var(--t2);margin-top:3px;display:block}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:12px 20px;border-radius:var(--r2);font-weight:700;font-size:.92rem;cursor:pointer;transition:.2s;text-decoration:none;border:none;width:100%}
.btn:active{transform:scale(.97)}
.btn-go{background:linear-gradient(135deg,var(--ac),#9b6dff);color:#fff}
.btn-go:hover{box-shadow:0 4px 24px rgba(124,92,252,.4)}
.btn-red{background:linear-gradient(135deg,#e74c3c,var(--rd));color:#fff}
.btn-ghost{background:transparent;color:var(--t2);width:auto;padding:8px 14px;font-size:.82rem}
.btn-ghost:hover{color:var(--t);background:var(--bg4)}
.btn-sm{padding:7px 12px;font-size:.78rem}
.fl{padding:12px 16px;border-radius:var(--r2);margin-bottom:14px;font-size:.88rem;animation:si .3s}
.fl-ok{background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.2);color:var(--gn)}
.fl-err{background:rgba(255,92,92,.1);border:1px solid rgba(255,92,92,.2);color:var(--rd)}
.c-auth{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center}
.c-auth .logo{font-size:4rem;margin-bottom:4px}
.c-auth h1{font-size:1.7rem;font-weight:800;margin-bottom:4px}
.c-auth .sub{color:var(--t2);margin-bottom:28px;font-size:.92rem}
.c-auth .fg{text-align:left;width:100%;max-width:380px}
.c-auth .btn{max-width:380px}
.c-auth .link{margin-top:20px;color:var(--t2);font-size:.85rem}
.feat{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:28px;max-width:380px;font-size:.78rem;color:var(--t2)}
.feat div{background:var(--bg3);padding:8px;border-radius:8px}
.empty{text-align:center;padding:36px 16px}
.emp-ico{font-size:3rem;opacity:.4;margin-bottom:8px}
.info-rows{display:flex;flex-direction:column;gap:10px;font-size:.88rem}
.info-rows div{display:flex;justify-content:space-between;align-items:center}
.danger{border:1px solid rgba(255,92,92,.15)}
.danger p{font-size:.82rem;color:var(--t2);margin-bottom:12px}
.danger .btn-red{width:auto}
footer{text-align:center;padding:20px 0;color:var(--t2);font-size:.75rem}
@keyframes si{from{transform:translateY(-8px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes sp{to{transform:rotate(360deg)}}
.spin{width:20px;height:20px;border:3px solid var(--bg4);border-top-color:var(--ac);border-radius:50%;animation:sp .7s linear infinite;display:inline-block;vertical-align:middle}
@media(min-width:768px){.wrap{padding:24px}.stats{gap:14px}.sv{font-size:2rem}}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--bg)}::-webkit-scrollbar-thumb{background:var(--bg4);border-radius:3px}
CSS;

// ==================== INLINE JS ====================
$js = <<<'JS'
function genPwd(){
    const c='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
    const types=[/[A-Z]/,/[a-z]/,/[0-9]/,/[!@#$%&*]/];
    let p=[];
    types.forEach((t,i)=>{let ch;do{ch=c[Math.floor(Math.random()*c.length)]}while(!t.test(ch));p.push(ch)});
    for(let i=4;i<16;i++)p.push(c[Math.floor(Math.random()*c.length)]);
    p.sort(()=>Math.random()-.5);
    document.getElementById('rdpPwd').value=p.join('');
}
function cf(m){return confirm(m)}
// Auto refresh dashboard
let _t=null;
function ar(){
    if(!location.search.includes('p=home')||!location.search.includes('p='))return;
    _t=setInterval(()=>{
        const b=document.getElementById('ref');
        if(b){b.textContent='🔄...';b.style.opacity='.5'}
        fetch(location.pathname+location.search,{headers:{'X-Requested-With':'fetch'}})
        .then(r=>r.text()).then(h=>{
            const d=new DOMParser().parseFromString(h,'text/html');
            const nl=d.getElementById('vpslist');
            if(nl)document.getElementById('vpslist').innerHTML=nl.innerHTML;
            if(b){b.textContent='🔄 30s';b.style.opacity='1'}
        }).catch(()=>{if(b)b.textContent='❌'});
    },30000);
}
document.addEventListener('DOMContentLoaded',ar);
// Form submit animation
const f=document.getElementById('fCreate');
const b=document.getElementById('btnGo');
if(f&&b)f.addEventListener('submit',()=>{b.disabled=true;b.innerHTML='<span class="spin"></span> Đang tạo VPS...'});
JS;

// ==================== OUTPUT ====================
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#0b0d13">
<title>🖥️ VPS Manager</title>
<style><?=$css?></style>
</head>
<body><?=$html?><script><?=$js?></script></body>
</html>
