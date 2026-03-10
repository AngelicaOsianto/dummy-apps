
// logout.php — Hapus semua cookie TKI dari server side

// Load .env
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

$COOKIE_DOMAIN = $_ENV['COOKIE_DOMAIN'] ?? '';

// Hapus semua cookie TKI — domain & path harus sama persis dengan saat set
$cookies = ['tki_auth','tki_user_id','tki_no_id','tki_company_id','tki_user_type','tki_login_time'];

foreach ($cookies as $name) {
    setcookie($name, '', [
        'expires'  => time() - 3600,  // set ke masa lalu
        'path'     => '/',
        'domain'   => $COOKIE_DOMAIN,
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Redirect ke login
header('Location: login.html');
exit;