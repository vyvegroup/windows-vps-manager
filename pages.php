<?php
/**
 * Page Renderers
 */

use function PHPSTORM_META\type;

// --- SETUP PAGE ---
function renderSetupPage(): string
{
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_error']);
    
    $encKey = bin2hex(random_bytes(16));
    
    return '
    <div class="setup-container">
        <div class="setup-box">
            <div class="setup-logo">🖥️</div>
            <div class="setup-title">VPS Manager</div>
            <p class="setup-desc">Quản lý Windows VPS qua GitHub Actions</p>
            
            ' . ($error ? '<div class="flash flash-error">⚠️ ' . htmlspecialchars($error) . '</div>' : '') . '
            
            <form method="POST" action="?action=setup" style="text-align:left">
                <div class="form-group">
                    <label>🔑 GitHub Personal Access Token</label>
                    <input type="password" name="github_token" class="form-control" 
                           placeholder="ghp_xxxxxxxxxxxx" required>
                    <div class="form-hint">Token sẽ được mã hóa AES-256-CBC trước khi lưu</div>
                </div>
                
                <div class="form-group">
                    <label>📁 GitHub Repository</label>
                    <input type="text" name="github_repo" class="form-control" 
                           placeholder="username/repo-name" required>
                </div>
                
                <div class="form-group">
                    <label>🔐 Mật khẩu truy cập app (lưu lại!)</label>
                    <input type="text" name="encryption_key" class="form-control" 
                           value="' . htmlspecialchars($encKey) . '" required>
                    <div class="form-hint">Dùng làm mật khẩu đăng nhập và key mã hóa. Hãy ghi nhớ!</div>
                </div>
                
                <button type="submit" class="btn btn-success" style="margin-top:8px">
                    ✨ Khởi tạo & Kết nối
                </button>
            </form>
            
            <p class="footer" style="margin-top:24px">
                Token được mã hóa và lưu cục bộ — KHÔNG gửi lên GitHub
            </p>
        </div>
    </div>';
}

// --- LOGIN PAGE ---
function renderLoginPage(): string
{
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_error']);
    
    return '
    <div class="setup-container">
        <div class="setup-box">
            <div class="setup-logo">🔒</div>
            <div class="setup-title">Đăng nhập</div>
            
            ' . ($error ? '<div class="flash flash-error">⚠️ ' . htmlspecialchars($error) . '</div>' : '') . '
            
            <form method="POST" action="?action=login" style="text-align:left">
                <div class="form-group">
                    <label>🔐 Mật khẩu</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Nhập mật khẩu..." required autofocus>
                    <div class="form-hint">Mật khẩu bạn đã tạo ở bước cài đặt</div>
                </div>
                
                <button type="submit" class="btn btn-primary">🔓 Đăng nhập</button>
            </form>
            
            <p style="margin-top:16px; text-align:center">
                <a href="?page=setup" class="btn-ghost">⚙️ Cài đặt lại</a>
            </p>
        </div>
    </div>';
}

// --- DASHBOARD ---
function renderDashboardPage(): string
{
    if (!isLoggedIn()) redirect('?page=login');
    
    $success = $_SESSION['flash_success'] ?? '';
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    
    // Lấy danh sách VPS đang chạy
    $running = githubApiCall('/workflows/provision-vps.yml/runs?per_page=50');
    $runs = $running['data']['workflow_runs'] ?? [];
    
    // Thống kê
    $activeCount = 0;
    $totalCount = count($runs);
    foreach ($runs as $r) {
        if (in_array($r['status'], ['in_progress', 'queued'])) $activeCount++;
    }
    $failedCount = count(array_filter($runs, fn($r) => ($r['conclusion'] ?? '') === 'failure'));
    
    $flashHtml = '';
    if ($success) $flashHtml .= '<div class="flash flash-success">✅ ' . htmlspecialchars($success) . '</div>';
    if ($error)   $flashHtml .= '<div class="flash flash-error">⚠️ ' . htmlspecialchars($error) . '</div>';
    
    $vpsListHtml = '';
    if (empty($runs)) {
        $vpsListHtml = '
        <div class="empty-state">
            <div class="empty-icon">🖥️</div>
            <div class="empty-text">Chưa có VPS nào được tạo</div>
            <a href="?page=create" class="btn btn-primary" style="margin-top:16px; width:auto; display:inline-flex;">
                ➕ Tạo VPS đầu tiên
            </a>
        </div>';
    } else {
        foreach ($runs as $run) {
            $isRunning = in_array($run['status'], ['in_progress', 'queued']);
            $statusClass = $isRunning ? 'running' : (($run['conclusion'] ?? '') === 'failure' ? 'failed' : 'stopped');
            $badgeClass = $isRunning ? 'badge-green' : (($run['conclusion'] ?? '') === 'failure' ? 'badge-red' : 'badge-blue');
            
            $vpsListHtml .= '
            <div class="vps-item ' . $statusClass . '">
                <div style="display:flex; justify-content:space-between; align-items:start">
                    <div class="vps-name">🖥️ ' . htmlspecialchars($run['display_title'] ?? 'VPS') . '</div>
                    <span class="badge ' . $badgeClass . '">' . getVPSStatusText($run['status']) . '</span>
                </div>
                <div class="vps-meta">
                    <span>🔖 Run #' . $run['run_number'] . '</span>
                    <span>🕐 ' . formatDuration($run['created_at'], $run['updated_at'] ?? null) . '</span>
                    <span>📅 ' . date('d/m H:i', strtotime($run['created_at'])) . '</span>
                </div>
                <div class="vps-actions">
                    <a href="' . htmlspecialchars($run['html_url']) . '" target="_blank" class="btn btn-ghost btn-sm">
                        📋 GitHub Logs
                    </a>';
            
            if ($isRunning) {
                $vpsListHtml .= '
                    <form method="POST" action="?action=stop_vps" style="display:inline" onsubmit="return confirm(\'Dừng VPS này?\')">
                        <input type="hidden" name="run_id" value="' . $run['id'] . '">
                        <button type="submit" class="btn btn-danger btn-sm">🛑 Dừng</button>
                    </form>';
            }
            
            $vpsListHtml .= '
                </div>
            </div>';
        }
    }
    
    return '
    <div class="container">
        <div class="header">
            <h1>🖥️ <span>VPS</span> Manager</h1>
            <div class="header-actions">
                <a href="?page=create" class="btn-icon" title="Tạo VPS mới">➕</a>
                <a href="?page=settings" class="btn-icon" title="Cài đặt">⚙️</a>
                <form method="POST" action="?action=logout" style="display:inline">
                    <button type="submit" class="btn-icon" title="Đăng xuất">🚪</button>
                </form>
            </div>
        </div>
        
        ' . $flashHtml . '
        
        <div class="stats-grid">
            <div class="stat-card stat-green">
                <div class="stat-value">' . $activeCount . '</div>
                <div class="stat-label">Đang chạy</div>
            </div>
            <div class="stat-card stat-red">
                <div class="stat-value">' . $failedCount . '</div>
                <div class="stat-label">Lỗi</div>
            </div>
            <div class="stat-card stat-blue">
                <div class="stat-value">' . $totalCount . '</div>
                <div class="stat-label">Tổng VPS</div>
            </div>
            <div class="stat-card stat-orange">
                <div class="stat-value">' . (360 * $activeCount) . '</div>
                <div class="stat-label">Phút còn lại</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>📋 Danh sách VPS</h2>
                <span class="badge badge-blue" id="refresh-badge">Tự làm mới</span>
            </div>
            <div id="vps-list">' . $vpsListHtml . '</div>
        </div>
        
        <div class="footer">VPS Manager v' . APP_VERSION . ' • PHP 8.3</div>
    </div>';
}

// --- CREATE VPS ---
function renderCreateVPSPage(): string
{
    if (!isLoggedIn()) redirect('?page=login');
    
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_error']);
    
    return '
    <div class="container">
        <div class="header">
            <h1>➕ <span>Tạo</span> VPS mới</h1>
            <a href="?page=dashboard" class="btn-icon">←</a>
        </div>
        
        ' . ($error ? '<div class="flash flash-error">⚠️ ' . htmlspecialchars($error) . '</div>' : '') . '
        
        <form method="POST" action="?action=create_vps">
            <div class="card">
                <div class="form-group">
                    <label>🖥️ Tên VPS</label>
                    <input type="text" name="vps_name" class="form-control" 
                           value="windows-vps-' . date('Ymd-His') . '" placeholder="Nhập tên VPS...">
                </div>
                
                <div class="form-group">
                    <label>🔑 Mật khẩu RDP</label>
                    <input type="text" name="rdp_password" class="form-control" id="rdp-pwd" 
                           placeholder="Mật khẩu RDP (tối thiểu 8 ký tự)" required minlength="8">
                    <div class="form-hint">Dùng cho kết nối Remote Desktop (username: runneradmin)</div>
                </div>
                
                <div class="form-group">
                    <label>⏱️ Thời gian sống (phút)</label>
                    <select name="vps_lifetime" class="form-control">
                        <option value="30">30 phút</option>
                        <option value="60" selected>1 giờ</option>
                        <option value="120">2 giờ</option>
                        <option value="180">3 giờ</option>
                        <option value="240">4 giờ</option>
                        <option value="300">5 giờ</option>
                        <option value="360">6 giờ (tối đa)</option>
                    </select>
                    <div class="form-hint">GitHub Actions giới hạn tối đa 6 giờ cho job</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h2>🌐 Ngrok Tunnel (Tùy chọn)</h2></div>
                <div class="form-group">
                    <label>🔑 Ngrok Auth Token</label>
                    <input type="text" name="ngrok_token" class="form-control" 
                           placeholder="Nhập Ngrok token để RDP từ xa">
                    <div class="form-hint">
                        GitHub Actions không cho RDP trực tiếp.<br>
                        Dùng Ngrok để tạo tunnel RDP: 
                        <a href="https://dashboard.ngrok.com/get-started/your-authtoken" target="_blank">
                            Lấy token miễn phí →
                        </a>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success" onclick="this.disabled=true; this.innerHTML='<span class=\'spinner\'></span> Đang tạo...'">
                🚀 Tạo Windows VPS
            </button>
        </form>
        
        <div class="footer">
            <a href="?page=dashboard">← Quay lại Dashboard</a>
        </div>
    </div>';
}

// --- VPS DETAIL ---
function renderVPSDetailPage(): string
{
    if (!isLoggedIn()) redirect('?page=login');
    
    $runId = (int)($_GET['run_id'] ?? 0);
    if ($runId <= 0) redirect('?page=dashboard');
    
    $run = githubApiCall("/runs/{$runId}");
    if ($run['error']) redirect('?page=dashboard');
    
    $r = $run['data'];
    $jobs = getRunJobs($runId);
    $jobsList = $jobs['data']['jobs'] ?? [];
    
    return '
    <div class="container container-wide">
        <div class="header">
            <h1>📋 <span>Chi tiết</span> VPS</h1>
            <a href="?page=dashboard" class="btn-icon">←</a>
        </div>
        
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px">
                <h2>' . htmlspecialchars($r['display_title'] ?? 'VPS') . '</h2>
                <span class="badge ' . (in_array($r['status'], ['in_progress','queued']) ? 'badge-green' : 'badge-blue') . '">
                    ' . getVPSStatusText($r['status']) . '
                </span>
            </div>
            <div class="vps-meta" style="margin-bottom:16px">
                <span>🔖 Run #' . $r['run_number'] . '</span>
                <span>🆔 ID: ' . $r['id'] . '</span>
                <span>📅 ' . date('d/m/Y H:i:s', strtotime($r['created_at'])) . '</span>
                <span>🕐 ' . formatDuration($r['created_at'], $r['updated_at'] ?? null) . '</span>
            </div>
            <a href="' . htmlspecialchars($r['html_url']) . '" target="_blank" class="btn btn-ghost btn-sm">
                📋 Xem trên GitHub →
            </a>
        </div>
        
        <div class="card">
            <div class="card-header"><h2>⚙️ Jobs</h2></div>';
    
    foreach ($jobsList as $job) {
        $statusIcon = match ($job['status']) {
            'in_progress' => '🟢', 'completed' => ($job['conclusion'] === 'success' ? '✅' : '❌'),
            'queued' => '🟡', default => '⚪'
        };
        $statusClass = match ($job['conclusion'] ?? '') {
            'success' => 'badge-green', 'failure' => 'badge-red', default => 'badge-blue'
        };
        $statusClass = $job['status'] === 'in_progress' ? 'badge-green' : $statusClass;
        
        echo '
            <div style="margin-bottom:12px; padding:12px; background:var(--bg3); border-radius:8px">
                <div style="display:flex; justify-content:space-between; align-items:center">
                    <strong>' . $statusIcon . ' ' . htmlspecialchars($job['name']) . '</strong>
                    <span class="badge ' . $statusClass . '">' . htmlspecialchars($job['status']) . '</span>
                </div>
                <div style="font-size:.8rem; color:var(--text2); margin-top:4px">
                    Bắt đầu: ' . date('H:i:s', strtotime($job['started_at'] ?? 'now')) . '
                    ' . ($job['completed_at'] ? '• Kết thúc: ' . date('H:i:s', strtotime($job['completed_at'])) : '') . '
                </div>
            </div>';
    }
    
    return '
            </div>
        </div>
    </div>';
}

// --- SETTINGS ---
function renderSettingsPage(): string
{
    if (!isLoggedIn()) redirect('?page=login');
    
    $success = $_SESSION['flash_success'] ?? '';
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    
    $config = require CONFIG_PATH;
    $repo = $config['github_repo'] ?? '';
    $user = $config['github_user'] ?? '';
    $created = $config['created_at'] ?? '';
    
    return '
    <div class="container">
        <div class="header">
            <h1>⚙️ <span>Cài đặt</span></h1>
            <a href="?page=dashboard" class="btn-icon">←</a>
        </div>
        
        ' . ($success ? '<div class="flash flash-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        ' . ($error ? '<div class="flash flash-error">⚠️ ' . htmlspecialchars($error) . '</div>' : '') . '
        
        <div class="card">
            <div class="card-header"><h2>📊 Thông tin hệ thống</h2></div>
            <div style="font-size:.9rem; line-height:2">
                <div>👤 GitHub User: <strong>' . htmlspecialchars($user) . '</strong></div>
                <div>📁 Repository: <strong>' . htmlspecialchars($repo) . '</strong></div>
                <div>📅 Cài đặt: <strong>' . htmlspecialchars($created) . '</strong></div>
                <div>🔒 Token: <span class="badge badge-green">Đã mã hóa AES-256-CBC ✓</span></div>
                <div>🌐 App Version: <strong>v' . APP_VERSION . '</strong></div>
            </div>
        </div>
        
        <form method="POST" action="?action=settings">
            <div class="card">
                <div class="card-header"><h2>🔑 Cập nhật Token</h2></div>
                <div class="form-group">
                    <label>GitHub Personal Access Token mới</label>
                    <input type="password" name="github_token" class="form-control" placeholder="Để trống nếu không đổi">
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h2>🌐 Ngrok Token</h2></div>
                <div class="form-group">
                    <label>Ngrok Auth Token mặc định</label>
                    <input type="text" name="ngrok_token" class="form-control" placeholder="Nhập Ngrok token">
                    <div class="form-hint">Sẽ được dùng mặc định khi tạo VPS mới</div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Lưu cài đặt</button>
        </form>
        
        <div class="card" style="margin-top:24px">
            <div class="card-header"><h2>⚠️ Danger Zone</h2></div>
            <p style="font-size:.85rem; color:var(--text2); margin-bottom:12px">
                Xóa config và cài đặt lại từ đầu. Dữ liệu VPS đang chạy không bị ảnh hưởng.
            </p>
            <a href="?page=setup" class="btn btn-danger btn-sm">🗑️ Reset cài đặt</a>
        </div>
        
        <div class="footer"><a href="?page=dashboard">← Quay lại Dashboard</a></div>
    </div>';
}

// --- LOGS ---
function renderLogsPage(): string
{
    if (!isLoggedIn()) redirect('?page=login');
    // Placeholder
    return '
    <div class="container">
        <div class="header">
            <h1>📜 <span>Logs</span></h1>
            <a href="?page=dashboard" class="btn-icon">←</a>
        </div>
        <div class="card">
            <div class="card-header"><h2>Hoạt động gần đây</h2></div>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-text">Xem logs chi tiết trên GitHub Actions</div>
            </div>
        </div>
    </div>';
}
