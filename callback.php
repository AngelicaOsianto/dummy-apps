<?php

// ===== CONFIG KEYCLOAK =====
$client_id     = 'dummy-app';
$client_secret = 'LWeSVS8dXjX9LnNHtCvtL3MUlMz5u5oG';
$redirect_uri  = 'https://dummy-apps-production.up.railway.app/callback.php';

$token_url = 'https://dummy-apps-production.up.railway.app/realms/dummy-realm/protocol/openid-connect/token';
$userinfo_url = 'https://dummy-apps-production.up.railway.app/realms/dummy-realm/protocol/openid-connect/userinfo';

// ===== 1. CEK CODE =====
if (!isset($_GET['code'])) {
    die('Tidak ada code');
}

$code = $_GET['code'];

// ===== 2. TUKAR CODE → TOKEN =====
$data = [
    'grant_type'    => 'authorization_code',
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'code'          => $code,
    'redirect_uri'  => $redirect_uri,
];

$ch = curl_init($token_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($data),
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if (!isset($result['access_token'])) {
    die('Gagal ambil access token');
}

$access_token = $result['access_token'];

// ===== 3. AMBIL USER INFO =====
$ch = curl_init($userinfo_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $access_token
    ],
]);

$userinfo = curl_exec($ch);
$user = json_decode($userinfo, true);

// ===== 4. SIMPAN SESSION =====
session_start();
$_SESSION['user'] = $user;

// ===== 5. REDIRECT KE DASHBOARD =====
header('Location: /dashboard.php');
echo "<pre>";
print_r($result);
echo "</pre>";
exit;
