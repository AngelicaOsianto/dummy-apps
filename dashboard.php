<?php
require_once __DIR__ . '/auth-check.php';
// $AUTH_USER sudah tersedia di sini

$sso_ok      = ($_GET['sso'] ?? '') === 'ok';
$sso_source  = $_GET['src'] ?? '';
$user_id     = $AUTH_USER['userId']    ?? '-';
$no_id       = $AUTH_USER['noId']      ?? '-';
$company_id  = $AUTH_USER['companyId'] ?? '-';
$user_type   = $AUTH_USER['userType']  ?? '-';
$login_source= $AUTH_USER['source']    ?? '-';
$login_time  = $AUTH_USER['loginTime'] ?? time();
$expires_at  = $login_time + 3600;
$initial     = strtoupper(substr($no_id, 0, 1)) ?: 'U';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dummy App PoC — Beranda</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --red:#c0392b;--red2:#e74c3c;--gold:#d4a017;--gold2:#f0c040;
  --bg:#f4f1eb;--white:#fff;--surface:#fdfcfa;
  --border:#e0d9ce;--text:#1c1a17;--muted:#7a7060;--dim:#a09880;
  --green:#1a6b3c;--green-l:#d4edda;--blue:#1a4f7a;--blue-l:#d0e8f7;
}
*,::before,::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* TOPBAR */
.topbar{background:var(--red);color:#fff;display:flex;align-items:center;
  justify-content:space-between;padding:0 28px;height:56px;
  position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(192,57,43,.3)}
.brand{display:flex;align-items:center;gap:10px}
.brand-mark{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.15);
  display:flex;align-items:center;justify-content:center;
  font-size:16px;font-weight:800;color:var(--gold2)}
.brand-name{font-size:15px;font-weight:700}
.brand-sub{font-size:10px;opacity:.7}
.topbar-right{display:flex;align-items:center;gap:12px}
.sso-pill{display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.2);border-radius:20px;padding:4px 12px;font-size:11px;
  font-family:'JetBrains Mono',monospace}
.sso-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;
  box-shadow:0 0 6px #4ade80;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
.user-chip{display:flex;align-items:center;gap:8px}
.avatar{width:32px;height:32px;border-radius:50%;
  background:linear-gradient(135deg,var(--gold),var(--gold2));
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:13px;color:var(--red);border:2px solid rgba(255,255,255,.3)}
.user-label{font-size:12px;font-weight:600}
.user-noid{font-size:10px;opacity:.7;font-family:'JetBrains Mono',monospace}
.btn-logout{padding:6px 14px;background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.2);border-radius:6px;color:#fff;
  font-size:12px;font-weight:600;cursor:pointer;transition:.2s;
  font-family:'Plus Jakarta Sans',sans-serif}
.btn-logout:hover{background:rgba(255,255,255,.25)}

/* LAYOUT */
.layout{display:flex;min-height:calc(100vh - 56px)}
.sidebar{width:210px;min-width:210px;background:var(--white);
  border-right:1px solid var(--border);padding:16px 10px}
.nav-section{font-size:9px;text-transform:uppercase;letter-spacing:1.5px;
  color:var(--dim);padding:14px 10px 6px;font-weight:600}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;
  border-radius:7px;font-size:13px;color:var(--muted);
  cursor:pointer;transition:.15s;text-decoration:none}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:rgba(192,57,43,.07);color:var(--red);font-weight:600}
.nav-icon{font-size:15px;width:20px;text-align:center}

/* MAIN */
.main{flex:1;padding:24px 28px;overflow:auto}

/* SSO BANNER */
.sso-banner{background:linear-gradient(135deg,#0d5f3a,#1a7a4a);border-radius:12px;
  padding:16px 20px;display:flex;align-items:center;gap:16px;
  margin-bottom:22px;color:#fff;animation:slideDown .4s ease;position:relative;overflow:hidden}
.sso-banner::after{content:'';position:absolute;right:-20px;top:-20px;
  width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.05)}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.banner-icon{font-size:28px;flex-shrink:0}
.banner-title{font-size:14px;font-weight:700;margin-bottom:2px}
.banner-sub{font-size:11px;opacity:.85}
.banner-close{margin-left:auto;cursor:pointer;opacity:.6;
  background:none;border:none;color:#fff;font-size:18px;padding:4px 8px;flex-shrink:0}
.banner-close:hover{opacity:1}

/* PAGE HEADER */
.page-header{margin-bottom:20px}
.page-header h1{font-size:20px;font-weight:800;letter-spacing:-.4px}
.page-header p{font-size:12px;color:var(--muted);margin-top:3px}

/* GRID */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px}

/* CARD */
.card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:18px 20px}
.card-title{font-size:14px;font-weight:700;margin-bottom:2px}
.card-sub{font-size:11px;color:var(--muted);margin-bottom:14px}

/* STAT */
.stat-val{font-size:26px;font-weight:800;letter-spacing:-1px;margin-bottom:2px}
.stat-val.red{color:var(--red)}
.stat-val.green{color:var(--green)}
.stat-val.blue{color:var(--blue)}
.stat-val.gold{color:var(--gold)}
.stat-note{font-size:11px;color:var(--dim)}
.stat-bar{height:3px;border-radius:10px;margin-top:10px;background:var(--border)}
.stat-bar-fill{height:100%;border-radius:10px}

/* TABLE */
.info-table{width:100%;font-size:12px;border-collapse:collapse}
.info-table td{padding:9px 10px;border-bottom:1px solid rgba(0,0,0,.04)}
.info-table tr:last-child td{border-bottom:none}
.info-table tr:hover td{background:#fafaf8}
.info-table td:first-child{color:var(--muted);width:45%;font-weight:500}
.info-table td:last-child{color:var(--text);font-family:'JetBrains Mono',monospace;font-size:11px}

/* CHIP */
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;
  border-radius:20px;font-size:10px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif}
.chip-green{background:var(--green-l);color:var(--green);border:1px solid rgba(26,107,60,.2)}
.chip-blue{background:var(--blue-l);color:var(--blue);border:1px solid rgba(26,79,122,.2)}
.chip-gold{background:#fef9e7;color:var(--gold);border:1px solid rgba(212,160,23,.3)}
.chip-red{background:#fdecea;color:var(--red);border:1px solid rgba(192,57,43,.2)}

/* DEBUG PANEL */
.debug{background:#0d1117;border-radius:10px;padding:18px;
  font-family:'JetBrains Mono',monospace;font-size:10px;color:#8b949e;margin-top:16px;
  line-height:1.9;position:relative}
.debug-hdr{font-size:11px;font-weight:600;color:#cdd6f4;margin-bottom:10px;
  display:flex;align-items:center;gap:8px}
.debug-badge{font-size:9px;background:rgba(192,57,43,.25);color:#ff6b6b;
  padding:2px 8px;border-radius:10px;border:1px solid rgba(192,57,43,.3)}
.dk{color:#7ee8fa;min-width:140px;display:inline-block}
.dv{color:#98c379}
.dv.yellow{color:#e5c07b}
.dv.red{color:#e06c75}
.dv.dim{color:#5c6370}

/* STATUS ROW */
.status-row{display:flex;align-items:center;justify-content:space-between;
  padding:9px 0;border-bottom:1px solid rgba(0,0,0,.04);font-size:12px}
.status-row:last-child{border-bottom:none}
.status-label{color:var(--muted)}

/* POC TAG */
.poc-tag{display:inline-flex;align-items:center;gap:6px;
  font-size:10px;color:var(--muted);background:var(--surface);
  border:1px solid var(--border);padding:3px 10px;border-radius:6px;
  font-family:'JetBrains Mono',monospace;margin-bottom:14px}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <div class="brand">
    <div class="brand-mark">D</div>
    <div>
      <div class="brand-name">Dummy App PoC</div>
      <div class="brand-sub">Universitas Hasanuddin — Uji Coba Integrasi Super Apps</div>
    </div>
  </div>
  <div class="topbar-right">
    <div class="sso-pill">
      <div class="sso-dot"></div>
      SSO via Super Apps
    </div>
    <div class="user-chip">
      <div class="avatar"><?= htmlspecialchars($initial) ?></div>
      <div>
        <div class="user-label">noId: <?= htmlspecialchars($no_id) ?></div>
        <div class="user-noid"><?= htmlspecialchars($user_type) ?></div>
      </div>
    </div>
    <button class="btn-logout" onclick="doLogout()">Keluar</button>
  </div>
</header>

<div class="layout">
  <!-- SIDEBAR -->
  <nav class="sidebar">
    <div class="nav-section">PoC Menu</div>
    <a class="nav-item active" href="#">
      <span class="nav-icon">⬡</span> Beranda
    </a>
    <a class="nav-item" href="#">
      <span class="nav-icon">🔍</span> Debug Info
    </a>
    <a class="nav-item" href="#">
      <span class="nav-icon">🍪</span> Cek Cookie
    </a>
    <div class="nav-section">Simulasi</div>
    <a class="nav-item" href="#">
      <span class="nav-icon">📋</span> Fitur A (Dummy)
    </a>
    <a class="nav-item" href="#">
      <span class="nav-icon">📊</span> Fitur B (Dummy)
    </a>
    <a class="nav-item" href="#">
      <span class="nav-icon">📁</span> Fitur C (Dummy)
    </a>
    <div class="nav-section">Akun</div>
    <a class="nav-item" href="login.html">
      <span class="nav-icon">🔓</span> Login Manual
    </a>
    <a class="nav-item" href="#" onclick="doLogout();return false">
      <span class="nav-icon">🚪</span> Keluar
    </a>
  </nav>

  <!-- MAIN CONTENT -->
  <main class="main">

    <!-- SSO SUCCESS BANNER -->
    <?php if ($sso_ok): ?>
    <div class="sso-banner" id="sso-banner">
      <div class="banner-icon">✅</div>
      <div>
        <div class="banner-title">SSO Berhasil! — Login via Super Apps (<?= htmlspecialchars($sso_source) ?>)</div>
        <div class="banner-sub">Anda masuk tanpa memasukkan password. Token divalidasi ke TKI Gateway. Sesi aktif 1 jam.</div>
      </div>
      <button class="banner-close" onclick="document.getElementById('sso-banner').remove()">×</button>
    </div>
    <?php endif; ?>

    <div class="poc-tag">⚗ PROOF OF CONCEPT — Bukan Aplikasi Produksi</div>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <h1>Selamat Datang di Dummy App PoC 👋</h1>
      <p>Login berhasil via Super Apps TKI — <?= date('l, d F Y, H:i') ?> WITA</p>
    </div>

    <!-- STATS -->
    <div class="g3">
      <div class="card">
        <div class="stat-note">Status Sesi</div>
        <div class="stat-val green">Aktif</div>
        <div class="stat-note">Via <?= ($login_source === 'cookie') ? 'Super Apps SSO' : 'Session PHP' ?></div>
        <div class="stat-bar"><div class="stat-bar-fill" style="width:100%;background:#1a6b3c"></div></div>
      </div>
      <div class="card">
        <div class="stat-note">Token Status</div>
        <div class="stat-val blue">Valid</div>
        <div class="stat-note">Divalidasi ke TKI Gateway ✓</div>
        <div class="stat-bar"><div class="stat-bar-fill" style="width:100%;background:#1a4f7a"></div></div>
      </div>
      <div class="card">
        <div class="stat-note">Sesi Berakhir</div>
        <div class="stat-val gold" id="countdown">--:--</div>
        <div class="stat-note"><?= date('H:i:s', $expires_at) ?> WITA</div>
        <div class="stat-bar"><div class="stat-bar-fill" id="session-bar" style="width:100%;background:var(--gold)"></div></div>
      </div>
    </div>

    <!-- USER DATA + SESSION STATUS -->
    <div class="g2">
      <!-- Data dari Token TKI -->
      <div class="card">
        <div class="card-title">📦 Data dari Token TKI</div>
        <div class="card-sub">Informasi yang diterima setelah validasi one_time_access_token</div>
        <table class="info-table">
          <tr><td>userId</td><td><?= htmlspecialchars($user_id) ?></td></tr>
          <tr><td>noId</td><td><?= htmlspecialchars($no_id) ?></td></tr>
          <tr><td>companyId</td><td><?= htmlspecialchars($company_id) ?></td></tr>
          <tr><td>userType</td><td><span class="chip chip-gold"><?= htmlspecialchars($user_type) ?></span></td></tr>
          <tr><td>Login Source</td><td><span class="chip chip-blue"><?= htmlspecialchars($login_source) ?></span></td></tr>
          <tr><td>Login Time</td><td><?= date('Y-m-d H:i:s', $login_time) ?></td></tr>
          <tr><td>Expires At</td><td><?= date('Y-m-d H:i:s', $expires_at) ?></td></tr>
        </table>
      </div>

      <!-- Status Integrasi -->
      <div class="card">
        <div class="card-title">🔗 Status Integrasi PoC</div>
        <div class="card-sub">Checklist komponen yang berhasil berjalan</div>
        <div class="status-row"><span class="status-label">redirect.php online</span><span class="chip chip-green">✓ OK</span></div>
        <div class="status-row"><span class="status-label">Token diterima dari Super Apps</span><span class="chip chip-green">✓ OK</span></div>
        <div class="status-row"><span class="status-label">Validasi ke TKI Gateway</span><span class="chip chip-green">✓ OK</span></div>
        <div class="status-row"><span class="status-label">Data user diekstrak</span><span class="chip chip-green">✓ OK</span></div>
        <div class="status-row"><span class="status-label">Cookie di-set</span><span class="chip chip-green">✓ OK</span></div>
        <div class="status-row"><span class="status-label">auth-check.php berjalan</span><span class="chip chip-green">✓ OK</span></div>
        <div class="status-row"><span class="status-label">Auto-login (tanpa password)</span><span class="chip chip-green">✓ BERHASIL</span></div>
      </div>
    </div>

    <!-- COOKIE INSPECTOR -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">🍪 Cookie Inspector</div>
      <div class="card-sub">Cookie TKI yang aktif di browser Anda</div>
      <div id="cookie-display" style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted)">
        Memuat...
      </div>
    </div>

    <!-- DEBUG PANEL -->
    <div class="debug">
      <div class="debug-hdr">
        🔧 Debug Panel
        <span class="debug-badge">HAPUS DI PRODUKSI</span>
      </div>
      <div><span class="dk">environment</span><span class="dv yellow">SANDBOX / PoC</span></div>
      <div><span class="dk">auth_source</span><span class="dv"><?= htmlspecialchars($login_source) ?></span></div>
      <div><span class="dk">tki_user_id</span><span class="dv"><?= htmlspecialchars($user_id) ?></span></div>
      <div><span class="dk">tki_no_id</span><span class="dv"><?= htmlspecialchars($no_id) ?></span></div>
      <div><span class="dk">tki_company_id</span><span class="dv"><?= htmlspecialchars($company_id) ?></span></div>
      <div><span class="dk">tki_user_type</span><span class="dv"><?= htmlspecialchars($user_type) ?></span></div>
      <div><span class="dk">session_expires</span><span class="dv yellow"><?= date('Y-m-d H:i:s', $expires_at) ?></span></div>
      <div><span class="dk">sso_param</span><span class="dv"><?= htmlspecialchars($_GET['sso'] ?? '-') ?></span></div>
      <div><span class="dk">server_time</span><span class="dv dim"><?= date('Y-m-d H:i:s') ?></span></div>
      <div><span class="dk">php_version</span><span class="dv dim"><?= PHP_VERSION ?></span></div>
    </div>

  </main>
</div>

<script>
// ── Cookie Inspector ─────────────────────────────────────────
function getCookies() {
  const cookies = document.cookie.split(';').map(c => c.trim());
  const tki = cookies.filter(c => c.startsWith('tki_'));
  const el = document.getElementById('cookie-display');
  if (tki.length === 0) {
    el.innerHTML = '<span style="color:#e06c75">Tidak ada cookie TKI ditemukan.</span>';
    return;
  }
  el.innerHTML = tki.map(c => {
    const [k, v] = c.split('=');
    return `<div style="display:flex;gap:12px;padding:4px 0;border-bottom:1px solid rgba(0,0,0,.05)">
      <span style="color:var(--blue);min-width:160px">${k}</span>
      <span style="color:var(--green)">${decodeURIComponent(v)}</span>
    </div>`;
  }).join('');
}
getCookies();

// ── Countdown timer ──────────────────────────────────────────
const expiresAt = <?= $expires_at ?> * 1000;
function updateCountdown() {
  const remaining = Math.max(0, expiresAt - Date.now());
  const m = Math.floor(remaining / 60000);
  const s = Math.floor((remaining % 60000) / 1000);
  document.getElementById('countdown').textContent =
    String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  const pct = (remaining / 3600000) * 100;
  const bar = document.getElementById('session-bar');
  if (bar) {
    bar.style.width = pct + '%';
    bar.style.background = pct > 50 ? 'var(--gold)' : pct > 20 ? '#e67e22' : '#c0392b';
  }
  if (remaining <= 0) {
    document.getElementById('countdown').textContent = 'EXPIRED';
  }
}
updateCountdown();
setInterval(updateCountdown, 1000);

// ── Logout ────────────────────────────────────────────────────
function doLogout() {
  ['tki_auth','tki_user_id','tki_no_id','tki_company_id','tki_user_type','tki_login_time']
    .forEach(n => {
      document.cookie = n + '=;expires=Thu,01 Jan 1970 00:00:00 GMT;path=/';
    });
  window.location.href = 'login.html';
}
</script>
</body>
</html>
