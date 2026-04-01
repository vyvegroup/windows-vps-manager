/**
 * JavaScript - VPS Manager
 */

// === Auto-refresh dashboard ===
let refreshInterval = null;

function startAutoRefresh(intervalMs = 30000) {
    if (window.location.search.includes('page=dashboard')) {
        refreshInterval = setInterval(() => {
            const badge = document.getElementById('refresh-badge');
            if (badge) {
                badge.textContent = 'Đang làm mới...';
                badge.style.opacity = '0.6';
            }
            fetch(window.location.href)
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newVpsList = doc.getElementById('vps-list');
                    if (newVpsList) {
                        document.getElementById('vps-list').innerHTML = newVpsList.innerHTML;
                    }
                    if (badge) {
                        badge.textContent = 'Tự làm mới ✓';
                        setTimeout(() => { badge.style.opacity = '1'; }, 1000);
                    }
                })
                .catch(() => {
                    if (badge) badge.textContent = 'Lỗi';
                });
        }, intervalMs);
    }
}

// === Password generator ===
function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
    const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lower = 'abcdefghijklmnopqrstuvwxyz';
    const num = '0123456789';
    const spec = '!@#$%&*';
    
    let pwd = '';
    pwd += upper[Math.floor(Math.random() * upper.length)];
    pwd += lower[Math.floor(Math.random() * lower.length)];
    pwd += num[Math.floor(Math.random() * num.length)];
    pwd += spec[Math.floor(Math.random() * spec.length)];
    
    for (let i = 4; i < 16; i++) {
        pwd += chars[Math.floor(Math.random() * chars.length)];
    }
    
    // Shuffle
    pwd = pwd.split('').sort(() => Math.random() - 0.5).join('');
    
    const input = document.getElementById('rdp-pwd');
    if (input) input.value = pwd;
}

// === Copy to clipboard ===
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = '✓ Đã copy';
        setTimeout(() => btn.textContent = orig, 1500);
    });
}

// === Confirm actions ===
function confirmAction(msg) {
    return confirm(msg);
}

// === Init ===
document.addEventListener('DOMContentLoaded', () => {
    startAutoRefresh();
    
    // Add password generator button if on create page
    const pwdInput = document.getElementById('rdp-pwd');
    if (pwdInput) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-ghost btn-sm';
        btn.textContent = '🎲 Random';
        btn.style.marginTop = '8px';
        btn.onclick = generatePassword;
        pwdInput.parentNode.insertBefore(btn, pwdInput.nextSibling);
    }
});
