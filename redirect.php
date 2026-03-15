<?php
/**
 * ============================================================
 *  redirect.php — TKI Super Apps → Aplikasi Unhas
 *  Domain  : https://redirecttki.unhas.ac.id/redirect.php
 *            (PoC: https://dummy-apps-production.up.railway.app/redirect.php)
 *
 *  Alur:
 *  1. Super Apps buka URL ini:
 *     ?redirect_to=01&one_time_access_token=xxxx&identifier=yyyy
 *  2. Validasi token ke Gateway TKI
 *  3. Jika valid → dapat companyId, userType, userId, noId
 *  4. Cek user di DB Unhas (dinonaktifkan di PoC)
 *  5. Set cookie (domain & path sesuai redirect_to)
 *  6. Redirect ke aplikasi tujuan
 * ============================================================
 */

// ── Load .env (lokal) ────────────────────────────────────────
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

// ── KONFIGURASI ──────────────────────────────────────────────
$TKI_VALIDATE_URL   = $_ENV['TKI_VALIDATE_URL']   ?? 'https://api.solusinegeri.com/gateway/api/authentication/one-time-access-token/MEMBER/validate';
$TKI_API_KEY        = $_ENV['TKI_API_KEY']        ?? '';
$TKI_INBOUND_TOKEN  = $_ENV['TKI_INBOUND_TOKEN']  ?? ''; // token untuk request KE TKI (dari Solusi Negeri)
$TKI_OUTBOUND_TOKEN = $_ENV['TKI_OUTBOUND_TOKEN'] ?? ''; // token dari TKI ke kita — untuk verifikasi request masuk
$TKI_BASIC_USERNAME = $_ENV['TKI_BASIC_USERNAME'] ?? '';
$TKI_BASIC_PASSWORD = $_ENV['TKI_BASIC_PASSWORD'] ?? '';
$APP_ENV            = $_ENV['APP_ENV']            ?? 'staging';
$TKI_COMPANY_ID     = $_ENV['TKI_COMPANY_ID']     ?? '';
$COOKIE_TTL         = (int)($_ENV['COOKIE_TTL']   ?? 3600);
$DUMMY_APP_URL      = $_ENV['DUMMY_APP_URL']      ?? 'https://dummy-apps-production.up.railway.app/dashboard.php';

/**
 * PETA REDIRECT
 * Kode → [url tujuan, cookie domain, cookie path, label]
 *
 * cookie domain: domain tempat cookie berlaku
 * cookie path  : path/subdomain yang bisa baca cookie
 *
 * Spesifikasi Unhas:
 *   - domain = sesuai aplikasi tujuan (misal .unhas.ac.id)
 *   - path   = /child_domain atau /sub_domain
 */
$REDIRECT_MAP = [
    // Kode 99 = PoC Dummy App (Railway)
    '99' => [
        'url'    => $DUMMY_APP_URL,
        'domain' => $_ENV['COOKIE_DOMAIN'] ?? '',  // kosong = domain saat ini
        'path'   => '/',
        'label'  => 'Dummy App PoC',
    ],

    // Produksi — aktifkan setelah PoC berhasil:
    // '01' => [
    //     'url'    => 'https://app.unhas.ac.id/',
    //     'domain' => '.unhas.ac.id',
    //     'path'   => '/',
    //     'label'  => 'Aplikasi Utama',
    // ],
    // '02' => [
    //     'url'    => 'https://sikola.unhas.ac.id/',
    //     'domain' => '.unhas.ac.id',
    //     'path'   => '/sikola',
    //     'label'  => 'SIKOLA E-Learning',
    // ],
    // '03' => [
    //     'url'    => 'https://sipantau.unhas.ac.id/',
    //     'domain' => '.unhas.ac.id',
    //     'path'   => '/sipantau',
    //     'label'  => 'SiPantau',
    // ],
    // '04' => [
    //     'url'    => 'https://portalid.unhas.ac.id/',
    //     'domain' => '.unhas.ac.id',
    //     'path'   => '/portalid',
    //     'label'  => 'PortalID',
    // ],
];

// ── VALIDASI OUTBOUND TOKEN ───────────────────────────────────
// Memastikan request benar-benar datang dari Super Apps TKI
// Aktifkan setelah Solusi Negeri konfirmasi nama header yang dipakai
//
// $incoming_outbound = $_SERVER['HTTP_X_OUTBOUND_TOKEN']
//                   ?? $_SERVER['HTTP_X_API_KEY']
//                   ?? '';
// if (!empty($TKI_OUTBOUND_TOKEN) && $incoming_outbound !== $TKI_OUTBOUND_TOKEN) {
//     render_error(403, 'Request Tidak Sah',
//         'Request tidak berasal dari Super Apps TKI.',
//         'Hubungi Tim IT Unhas.',
//         $APP_ENV
//     );
//     exit;
// }

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
        'Hubungi Tim IT Unhas untuk mendaftarkan kode redirect.',
        $APP_ENV
    );
    exit;
}

$target = $REDIRECT_MAP[$redirect_to];

// ── LANGKAH 2: VALIDASI TOKEN KE GATEWAY TKI ────────────────
// Gunakan TKI_INBOUND_TOKEN jika ada, fallback ke TKI_API_KEY
$auth_token = !empty($TKI_INBOUND_TOKEN) ? $TKI_INBOUND_TOKEN : $TKI_API_KEY;

$result = validate_tki_token(
    $TKI_VALIDATE_URL,
    $one_time_token,
    $identifier,
    $auth_token,
    $TKI_BASIC_USERNAME,
    $TKI_BASIC_PASSWORD
);

if ($result === null) {
    render_error(503, 'Gateway TKI Tidak Dapat Dihubungi',
        'Server tidak dapat terhubung ke Gateway TKI.',
        'Periksa koneksi server atau hubungi Solusi Negeri.',
        $APP_ENV
    );
    exit;
}

// ── LANGKAH 3: CEK RESPONSE TKI ─────────────────────────────
// Format response yang diharapkan:
// {
//   "status_code": 200,
//   "type": "SUCCESS_VALIDATE_TOKEN",
//   "message": "",
//   "data": {
//     "companyId": "6344ee8e91d97e8214cd5f25",
//     "userType": "MEMBER",
//     "userId": "645318d128e774db3439b066",
//     "noId": "123123123"
//   }
// }

$is_valid = false;
$data     = [];

if (
    isset($result['status_code'], $result['type']) &&
    $result['status_code'] === 200 &&
    $result['type'] === 'SUCCESS_VALIDATE_TOKEN'
) {
    $is_valid = true;
    $data     = $result['data'] ?? [];
} elseif (isset($result['data']) && !empty($result['data'])) {
    // Fallback jika format sedikit berbeda
    $is_valid = true;
    $data     = $result['data'];
}

if (!$is_valid) {
    $err_msg = $result['message'] ?? ($result['error'] ?? 'Token tidak valid atau sudah kadaluwarsa.');
    render_error(401, 'Token Tidak Valid',
        $err_msg,
        'Token hanya berlaku sekali. Kembali ke Super Apps dan coba lagi.',
        $APP_ENV
    );
    exit;
}

// ── EKSTRAK DATA USER ─────────────────────────────────────────
$company_id = $data['companyId'] ?? '';
$user_type  = $data['userType']  ?? 'MEMBER';
$user_id    = $data['userId']    ?? '';
$no_id      = $data['noId']      ?? '';

// ── LANGKAH 4: CEK USER DI DB UNHAS ─────────────────────────
// DINONAKTIFKAN di PoC — aktifkan di produksi
//
// $user_exists = check_user_in_db_unhas($user_id, $no_id);
// if (!$user_exists) {
//     render_error(403, 'Akun Tidak Ditemukan di Sistem Unhas',
//         "userId={$user_id} / noId={$no_id} tidak terdaftar.",
//         'Hubungi Direktorat TIK Unhas.',
//         $APP_ENV
//     );
//     exit;
// }

// ── LANGKAH 5: SET COOKIE ─────────────────────────────────────
// domain dan path diambil dari REDIRECT_MAP sesuai spesifikasi:
// domain = domain aplikasi tujuan
// path   = /child_domain atau /sub_domain

$cookie_opts = [
    'expires'  => time() + $COOKIE_TTL,
    'path'     => $target['path'],    // dari REDIRECT_MAP
    'domain'   => $target['domain'],  // dari REDIRECT_MAP
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
];

setcookie('tki_auth',       '1',              $cookie_opts);
setcookie('tki_user_id',    $user_id,         $cookie_opts);
setcookie('tki_no_id',      $no_id,           $cookie_opts);
setcookie('tki_company_id', $company_id,      $cookie_opts);
setcookie('tki_user_type',  $user_type,       $cookie_opts);
setcookie('tki_login_time', (string)time(),   $cookie_opts);

// ── LOG ───────────────────────────────────────────────────────
log_access($user_id, $no_id, $company_id, $redirect_to, $target['label'], $target['url']);

// ── LANGKAH 6: REDIRECT ───────────────────────────────────────
$sep       = strpos($target['url'], '?') !== false ? '&' : '?';
$final_url = $target['url'] . $sep . 'sso=ok&src=superapp';

header('Location: ' . $final_url);
exit;


// ════════════════════════════════════════════════════════════
//  FUNGSI-FUNGSI
// ════════════════════════════════════════════════════════════

function validate_tki_token(
    string $url,
    string $token,
    string $identifier,
    string $api_key    = '',
    string $basic_user = '',
    string $basic_pass = ''
): ?array {

    $payload = json_encode([
        'one_time_access_token' => $token,
        'identifier'            => $identifier,
    ]);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (!empty($api_key)) {
        $headers[] = 'Authorization: Bearer ' . $api_key;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if (!empty($basic_user)) {
        curl_setopt($ch, CURLOPT_USERPWD,  $basic_user . ':' . $basic_pass);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }

    $response  = curl_exec($ch);
    $curl_err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($curl_err || $response === false) {
        error_log('[TKI-Redirect] cURL error: ' . $curl_err);
        return null;
    }

    error_log('[TKI-Redirect] HTTP ' . $http_code . ' | ' . substr($response, 0, 300));

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function check_user_in_db_unhas(string $user_id, string $no_id): bool
{
    // Implementasi produksi (contoh PDO):
    // $pdo  = new PDO('mysql:host=DB_HOST;dbname=unhas_db', DB_USER, DB_PASS);
    // $stmt = $pdo->prepare(
    //     "SELECT id FROM mahasiswa WHERE tki_user_id = :uid OR nim = :nid LIMIT 1"
    // );
    // $stmt->execute([':uid' => $user_id, ':nid' => $no_id]);
    // return (bool) $stmt->fetch();
    return true; // PoC: semua user dianggap terdaftar
}

function log_access(
    string $user_id, string $no_id, string $company_id,
    string $redirect_to, string $label, string $target
): void {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) @mkdir($log_dir, 0750, true);
    $line = sprintf(
        "[%s] userId=%s noId=%s companyId=%s redirect_to=%s label=%s target=%s ip=%s\n",
        date('Y-m-d H:i:s'), $user_id ?: '-', $no_id ?: '-',
        $company_id ?: '-', $redirect_to, $label, $target,
        $_SERVER['REMOTE_ADDR'] ?? '-'
    );
    @file_put_contents($log_dir . '/access.log', $line, FILE_APPEND | LOCK_EX);
}

function render_error(int $code, string $title, string $detail, string $hint = '', string $env = 'sandbox'): void
{
    http_response_code($code);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $code ?> — TKI Redirect</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'DM Sans',Arial,sans-serif;min-height:100vh;display:flex;
    align-items:center;justify-content:center;background:#F5F3EE;padding:20px}
  .card{background:#fff;border-radius:14px;padding:40px 36px;max-width:480px;
    width:100%;border-top:4px solid #C0392B}
  .code{font-size:52px;font-weight:700;color:#C0392B;line-height:1;margin-bottom:12px}
  .title{font-size:18px;font-weight:600;color:#1A1916;margin-bottom:6px}
  .detail{font-size:13.5px;color:#8A8680;line-height:1.6;margin-bottom:12px}
  .hint{font-size:13px;color:#1A4A6B;background:#D5E5F0;border:1px solid #B0C8DC;
    padding:10px 14px;border-radius:7px;line-height:1.6}
  .env-badge{display:inline-block;margin-top:14px;padding:3px 10px;border-radius:20px;
    font-size:10px;font-weight:600;background:#F5EDD6;color:#B8860B;
    border:1px solid #DDD0A8;font-family:'Courier New',monospace}
  .back{margin-top:18px;display:inline-block;padding:9px 20px;background:#C0392B;
    color:#fff;border-radius:7px;text-decoration:none;font-size:13px;font-weight:600}
</style>
</head>
<body>
<div class="card">
  <div class="code"><?= $code ?></div>
  <div class="title"><?= htmlspecialchars($title) ?></div>
  <div class="detail"><?= htmlspecialchars($detail) ?></div>
  <?php if ($hint): ?>
    <div class="hint">💡 <?= htmlspecialchars($hint) ?></div>
  <?php endif; ?>
  <?php if (in_array($env, ['sandbox','staging'])): ?>
    <div class="env-badge">⚙ Environment: <?= strtoupper($env) ?></div>
  <?php endif; ?>
  <br>
  <a href="javascript:history.back()" class="back">← Kembali</a>
</div>
</body>
</html>
<?php
}