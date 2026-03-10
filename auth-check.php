<?php
/**
 * auth-check.php — Middleware cek cookie TKI
 * Include di bagian PALING ATAS setiap halaman yang butuh login.
 * Setelah include, variabel $AUTH_USER tersedia.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

define('LOGIN_PAGE', '/login.html');
define('SESSION_TTL', 3600);

// 1. Cek session PHP aktif
if (!empty($_SESSION['tki_user_id']) && time() < ($_SESSION['tki_expires'] ?? 0)) {
    $AUTH_USER = [
        'userId'    => $_SESSION['tki_user_id'],
        'noId'      => $_SESSION['tki_no_id']      ?? '',
        'companyId' => $_SESSION['tki_company_id'] ?? '',
        'userType'  => $_SESSION['tki_user_type']  ?? '',
        'loginTime' => $_SESSION['tki_login_time'] ?? 0,
        'source'    => 'session',
    ];
    return;
}

// 2. Cek cookie SUPER APPS
if (($_COOKIE['tki_auth'] ?? '') === '1' && !empty($_COOKIE['tki_user_id'])) {
    $_SESSION['tki_user_id']    = $_COOKIE['tki_user_id'];
    $_SESSION['tki_no_id']      = $_COOKIE['tki_no_id']      ?? '';
    $_SESSION['tki_company_id'] = $_COOKIE['tki_company_id'] ?? '';
    $_SESSION['tki_user_type']  = $_COOKIE['tki_user_type']  ?? '';
    $_SESSION['tki_login_time'] = $_COOKIE['tki_login_time'] ?? time();
    $_SESSION['tki_expires']    = time() + SESSION_TTL;

    $AUTH_USER = [
        'userId'    => $_SESSION['tki_user_id'],
        'noId'      => $_SESSION['tki_no_id'],
        'companyId' => $_SESSION['tki_company_id'],
        'userType'  => $_SESSION['tki_user_type'],
        'loginTime' => $_SESSION['tki_login_time'],
        'source'    => 'cookie',
    ];
    return;
}

// 3. Tidak ada session/cookie → redirect ke login
$next = urlencode((isset($_SERVER['HTTPS']) ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . ($_SERVER['REQUEST_URI'] ?? '/'));
header('Location: ' . LOGIN_PAGE . '?next=' . $next);
exit;
