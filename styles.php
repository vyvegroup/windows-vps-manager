<?php
/**
 * CSS - Mobile-First Design System
 */

return <<<'CSS'
/* ===== RESET & BASE ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #0f1117;
    --bg2: #1a1d2e;
    --bg3: #252836;
    --bg4: #2d3044;
    --text: #e4e6f0;
    --text2: #9499b3;
    --accent: #6c5ce7;
    --accent2: #a29bfe;
    --green: #00d2a0;
    --green2: #00b894;
    --red: #ff6b6b;
    --orange: #fdcb6e;
    --blue: #74b9ff;
    --radius: 16px;
    --radius2: 10px;
    --shadow: 0 4px 24px rgba(0,0,0,.3);
    --transition: .25s ease;
}
html { font-size: 16px; scroll-behavior: smooth; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}
a { color: var(--accent2); text-decoration: none; }
input, select, textarea, button { font-family: inherit; font-size: 1rem; }

/* ===== LAYOUT ===== */
.container { max-width: 480px; margin: 0 auto; padding: 16px; }
.container-wide { max-width: 720px; }

/* ===== HEADER ===== */
.header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 0; margin-bottom: 16px;
}
.header h1 { font-size: 1.4rem; font-weight: 700; }
.header h1 span { color: var(--accent2); }
.header-actions { display: flex; gap: 8px; }

/* ===== CARDS ===== */
.card {
    background: var(--bg2);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: var(--shadow);
    border: 1px solid rgba(255,255,255,.04);
}
.card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.card-header h2 { font-size: 1.1rem; font-weight: 600; }

/* ===== STATS ===== */
.stats-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
    margin-bottom: 16px;
}
.stat-card {
    background: var(--bg3);
    border-radius: var(--radius2);
    padding: 16px;
    text-align: center;
}
.stat-value { font-size: 1.8rem; font-weight: 800; }
.stat-label { font-size: .8rem; color: var(--text2); margin-top: 4px; }
.stat-green .stat-value { color: var(--green); }
.stat-red .stat-value { color: var(--red); }
.stat-blue .stat-value { color: var(--blue); }
.stat-orange .stat-value { color: var(--orange); }

/* ===== FORMS ===== */
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block; font-size: .85rem; font-weight: 600;
    color: var(--text2); margin-bottom: 6px;
}
.form-control {
    width: 100%; padding: 12px 16px;
    background: var(--bg3); border: 2px solid var(--bg4);
    border-radius: var(--radius2); color: var(--text);
    transition: border-color var(--transition);
    outline: none;
}
.form-control:focus { border-color: var(--accent); }
.form-control::placeholder { color: var(--text2); opacity: .5; }
.form-hint { font-size: .75rem; color: var(--text2); margin-top: 4px; }

/* ===== BUTTONS ===== */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 12px 24px; border-radius: var(--radius2);
    font-weight: 600; font-size: .95rem;
    cursor: pointer; border: none;
    transition: all var(--transition);
    text-decoration: none;
}
.btn:active { transform: scale(.97); }
.btn-primary {
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    color: #fff; width: 100%;
}
.btn-primary:hover { box-shadow: 0 4px 20px rgba(108,92,231,.4); }
.btn-success {
    background: linear-gradient(135deg, var(--green2), var(--green));
    color: #fff; width: 100%;
}
.btn-success:hover { box-shadow: 0 4px 20px rgba(0,210,160,.4); }
.btn-danger {
    background: linear-gradient(135deg, #e74c3c, var(--red));
    color: #fff;
}
.btn-danger:hover { box-shadow: 0 4px 20px rgba(255,107,107,.4); }
.btn-ghost {
    background: transparent; color: var(--text2);
    padding: 8px 16px; font-size: .85rem;
}
.btn-ghost:hover { color: var(--text); background: var(--bg3); }
.btn-sm { padding: 8px 14px; font-size: .82rem; }
.btn-icon {
    width: 40px; height: 40px; padding: 0;
    border-radius: 50%; background: var(--bg3);
    color: var(--text2); border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.btn-icon:hover { background: var(--bg4); color: var(--text); }

/* ===== VPS LIST ===== */
.vps-item {
    background: var(--bg3);
    border-radius: var(--radius2);
    padding: 16px; margin-bottom: 12px;
    border-left: 4px solid var(--accent);
    transition: all var(--transition);
}
.vps-item:hover { background: var(--bg4); }
.vps-item.running { border-left-color: var(--green); }
.vps-item.stopped { border-left-color: var(--red); }
.vps-item.failed { border-left-color: var(--red); }
.vps-name { font-weight: 700; font-size: 1rem; margin-bottom: 4px; }
.vps-meta { display: flex; flex-wrap: wrap; gap: 12px; font-size: .82rem; color: var(--text2); }
.vps-meta span { display: flex; align-items: center; gap: 4px; }
.vps-actions { display: flex; gap: 8px; margin-top: 12px; }

/* ===== FLASH MESSAGES ===== */
.flash {
    padding: 14px 18px; border-radius: var(--radius2);
    margin-bottom: 16px; font-size: .9rem;
    animation: slideIn .3s ease;
}
.flash-success { background: rgba(0,210,160,.12); border: 1px solid rgba(0,210,160,.25); color: var(--green); }
.flash-error { background: rgba(255,107,107,.12); border: 1px solid rgba(255,107,107,.25); color: var(--red); }
.flash-info { background: rgba(116,185,255,.12); border: 1px solid rgba(116,185,255,.25); color: var(--blue); }

/* ===== SETUP / LOGIN ===== */
.setup-container {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; min-height: 100vh; padding: 24px;
}
.setup-box {
    width: 100%; max-width: 420px; text-align: center;
}
.setup-logo { font-size: 4rem; margin-bottom: 8px; }
.setup-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 8px; }
.setup-desc { color: var(--text2); margin-bottom: 32px; font-size: .95rem; }

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.6);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 16px;
    opacity: 0; pointer-events: none; transition: opacity var(--transition);
}
.modal-overlay.active { opacity: 1; pointer-events: all; }
.modal {
    background: var(--bg2); border-radius: var(--radius);
    padding: 24px; width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
}

/* ===== TABS ===== */
.tabs {
    display: flex; gap: 4px; background: var(--bg3);
    border-radius: var(--radius2); padding: 4px; margin-bottom: 16px;
}
.tab {
    flex: 1; padding: 10px; text-align: center;
    border-radius: 8px; font-size: .85rem; font-weight: 600;
    color: var(--text2); cursor: pointer;
    transition: all var(--transition);
}
.tab.active { background: var(--accent); color: #fff; }

/* ===== LOG VIEWER ===== */
.log-box {
    background: var(--bg); border-radius: var(--radius2);
    padding: 16px; font-family: 'Courier New', monospace;
    font-size: .78rem; line-height: 1.7;
    max-height: 400px; overflow-y: auto;
    white-space: pre-wrap; word-break: break-all;
    color: var(--text2);
}

/* ===== LOADING ===== */
.spinner {
    width: 24px; height: 24px; border: 3px solid var(--bg4);
    border-top-color: var(--accent); border-radius: 50%;
    animation: spin .8s linear infinite; display: inline-block;
}
.loading-text { color: var(--text2); font-size: .85rem; margin-top: 8px; }

/* ===== EMPTY STATE ===== */
.empty-state { text-align: center; padding: 40px 20px; }
.empty-icon { font-size: 3rem; margin-bottom: 12px; opacity: .5; }
.empty-text { color: var(--text2); font-size: .9rem; }

/* ===== BADGE ===== */
.badge {
    display: inline-block; padding: 3px 10px;
    border-radius: 20px; font-size: .75rem; font-weight: 600;
}
.badge-green { background: rgba(0,210,160,.15); color: var(--green); }
.badge-red { background: rgba(255,107,107,.15); color: var(--red); }
.badge-blue { background: rgba(116,185,255,.15); color: var(--blue); }
.badge-orange { background: rgba(253,203,110,.15); color: var(--orange); }

/* ===== FOOTER ===== */
.footer { text-align: center; padding: 24px 0; color: var(--text2); font-size: .78rem; }

/* ===== ANIMATIONS ===== */
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes slideIn {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes fadeIn {
    from { opacity: 0; transform: scale(.96); }
    to { opacity: 1; transform: scale(1); }
}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--bg4); border-radius: 3px; }

/* ===== RESPONSIVE ===== */
@media (min-width: 768px) {
    .container { padding: 24px; }
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
    .vps-meta { gap: 16px; }
}

CSS;
