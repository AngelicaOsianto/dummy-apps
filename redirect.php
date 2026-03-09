<?php
/**
 * ============================================================
 *  redirect.php — TKI Super Apps → Dummy App PoC
 *  Deploy di: https://[your-domain]/redirect.php
 *
 *  Cara kerja:
 *  Super Apps buka → redirect.php?redirect_to=99
 *                                &one_time_access_token=TOKEN
 *                                &identifier=ID
 *  Script ini validasi token ke TKI → set cookie → redirect
 * ============================================================
 */

// ── Load .env ────────────────────────────────────────────────
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

// ── KONFIGURASI (dari .env atau fallback) ────────────────────
$TKI_VALIDATE_URL = $_ENV['TKI_VALIDATE_URL']
    ?? 'https://api.stg.solusinegeri.com/gateway/api/authentication/one-time-access-token_with_auth/validate';
$COOKIE_DOMAIN    = $_ENV['COOKIE_DOMAIN'] ?? '';      // kosong = current domain saja
$COOKIE_TTL       = (int)($_ENV['COOKIE_TTL'] ?? 3600);
$APP_ENV          = $_ENV['APP_ENV'] ?? 'sandbox';
$APP_NAME         = $_ENV['APP_NAME'] ?? 'Dummy App PoC Unhas';
$DUMMY_APP_URL    = $_ENV['DUMMY_APP_URL'] ?? '/dashboard.php';

// Peta kode redirect → URL tujuan
$REDIRECT_MAP = [
    '99' => $DUMMY_APP_URL,                          // kode khusus PoC
    // Tambahkan nanti setelah PoC berhasil:
    // '01' => 'https://app.unhas.ac.id/',
    // '02' => 'https://sikola.unhas.ac.id/',
];

// ── AMBIL PARAMETER ──────────────────────────────────────────
$redirect_to    = trim($_GET['redirect_to']           ?? '');
$one_time_token = trim($_GET['one_time_access_token'] ?? '');
$identifier     = trim($_GET['identifier']            ?? '');

// ── VALIDASI PARAMETER ───────────────────────────────────────
if (empty($redirect_to) || empty($one_time_token) || empty($identifier)) {
    render_error(400, 'Parameter Tidak Lengkap',
        'URL harus mengandung: redirect_to, one_time_access_token, identifier.',
        'Jika Anda melihat halaman ini, berarti redirect.php sudah aktif dan berjalan dengan benar. ✅',
        $APP_ENV
    );
    exit;
}

if (!isset($REDIRECT_MAP[$redirect_to])) {
    render_error(404, 'Kode Redirect Tidak Dikenal',
        "Kode redirect_to=\"{$redirect_to}\" tidak terdaftar.",
        'Hubungi Tim IT Unhas untuk mendaftarkan kode redirect yang sesuai.',
        $APP_ENV
    );
    exit;
}

$target_url = $REDIRECT_MAP[$redirect_to];

// ── VALIDASI TOKEN KE TKI GATEWAY ────────────────────────────
$result = validate_tki_token($TKI_VALIDATE_URL, $one_time_token, $identifier);

if ($result === null) {
    render_error(503, 'Gateway TKI Tidak Dapat Dihubungi',
        'Tidak dapat memvalidasi token ke server TKI.',
        'Periksa koneksi server ke ' . $TKI_VALIDATE_URL . ' atau coba beberapa saat lagi.',
        $APP_ENV
    );
    exit;
}

if (($result['status_code'] ?? 0) !== 200 || ($result['type'] ?? '') !== 'SUCCESS_VALIDATE_TOKEN') {
    $err_msg = $result['message'] ?? 'Token tidak valid atau sudah kadaluwarsa.';
    render_error(401, 'Token Tidak Valid',
        $err_msg,
        'Token one_time_access_token hanya berlaku sekali dan dalam waktu singkat. Kembali ke Super Apps dan coba lagi.',
        $APP_ENV
    );
    exit;
}

// ── TOKEN VALID → EKSTRAK DATA USER ──────────────────────────
$data       = $result['data'] ?? [];
$company_id = $data['companyId'] ?? '';
$user_type  = $data['userType']  ?? '';
$user_id    = $data['userId']    ?? '';
$no_id      = $data['noId']      ?? '';

// ── SET COOKIE ───────────────────────────────────────────────
$cookie_opts = [
    'expires'  => time() + $COOKIE_TTL,
    'path'     => '/',
    'domain'   => $COOKIE_DOMAIN,
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
];

setcookie('tki_auth',       '1',         $cookie_opts);
setcookie('tki_user_id',    $user_id,    $cookie_opts);
setcookie('tki_no_id',      $no_id,      $cookie_opts);
setcookie('tki_company_id', $company_id, $cookie_opts);
setcookie('tki_user_type',  $user_type,  $cookie_opts);
setcookie('tki_login_time', time(),      $cookie_opts);

// ── LOG AKSES ────────────────────────────────────────────────
log_access($user_id, $no_id, $redirect_to, $target_url);

// ── REDIRECT KE APLIKASI TUJUAN ──────────────────────────────
// Tambahkan parameter sukses agar dashboard bisa tampilkan banner
$sep = (strpos($target_url, '?') !== false) ? '&' : '?';
$final_url = $target_url . $sep . 'sso=ok&src=superapp';

header('Location: ' . $final_url);
exit;

// ════════════════════════════════════════════════════════════
//  FUNGSI
// ════════════════════════════════════════════════════════════

function validate_tki_token(string $url, string $token, string $identifier): ?array
{
    $payload = json_encode([
        'one_time_access_token' => $token,
        'identifier'            => $identifier,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err || $response === false) {
        error_log('[TKI-PoC] cURL error: ' . $curl_err);
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function log_access(string $user_id, string $no_id, string $redirect_to, string $target): void
{
    $log_dir  = __DIR__ . '/logs';
    $log_file = $log_dir . '/access.log';

    if (!is_dir($log_dir)) @mkdir($log_dir, 0750, true);

    $line = sprintf(
        "[%s] userId=%s noId=%s redirect_to=%s target=%s ip=%s ua=%s\n",
        date('Y-m-d H:i:s'),
        $user_id ?: '-',
        $no_id   ?: '-',
        $redirect_to,
        $target,
        $_SERVER['REMOTE_ADDR']     ?? '-',
        substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 80)
    );

    @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

function render_error(int $code, string $title, string $detail, string $hint = '', string $env = 'sandbox'): void
{
    http_response_code($code);
    $is_sandbox = ($env === 'sandbox' || $env === 'staging');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $code ?> — TKI Redirect PoC</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;
    justify-content:center;background:#f5f5f5}
  .wrap{background:#fff;border-radius:12px;padding:40px;max-width:520px;width:92%;
    box-shadow:0 4px 24px rgba(0,0,0,.08);border-top:4px solid #c0392b}
  .code{font-size:56px;font-weight:800;color:#c0392b;line-height:1}
  .title{font-size:20px;font-weight:700;margin:12px 0 8px;color:#1a1a1a}
  .detail{font-size:14px;color:#555;line-height:1.6;margin-bottom:12px}
  .hint{font-size:13px;color:#2e86c1;background:#ebf5fb;border:1px solid #aed6f1;
    padding:10px 14px;border-radius:6px;line-height:1.6}
  .env-badge{display:inline-block;margin-top:16px;padding:3px 10px;border-radius:12px;
    font-size:11px;font-weight:600;background:#fef9e7;color:#c8870a;border:1px solid #f0c040}
  .back{margin-top:20px;display:inline-block;padding:9px 20px;background:#c0392b;
    color:#fff;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <div class="code"><?= $code ?></div>
  <div class="title"><?= htmlspecialchars($title) ?></div>
  <div class="detail"><?= htmlspecialchars($detail) ?></div>
  <?php if ($hint): ?>
    <div class="hint">💡 <?= htmlspecialchars($hint) ?></div>
  <?php endif; ?>
  <?php if ($is_sandbox): ?>
    <div class="env-badge">⚙ Environment: <?= strtoupper($env) ?></div>
  <?php endif; ?>
  <a href="javascript:history.back()" class="back">← Kembali</a>
</div>
</body>
</html>
<?php
}
