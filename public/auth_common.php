<?php
require_once __DIR__ . '/config.php';

function url2ai_auth_start_session() {
    if (session_status() !== PHP_SESSION_NONE) { return; }
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', AIGM_COOKIE_DOMAIN);
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function url2ai_auth_current_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'airadio-scripted-mv.exbridge.jp';
    $uri = $_SERVER['REQUEST_URI'] ?? '/lyrics-extractor.php';
    return $scheme . '://' . $host . $uri;
}

function url2ai_auth_login_url($return = '') {
    $return = $return !== '' ? $return : url2ai_auth_current_url();
    return AIGM_BASE_URL . '/aiknowledgesns.php?aks_login=1&return=' . urlencode($return);
}

function url2ai_auth_logout_url($return = '') {
    $return = $return !== '' ? $return : url2ai_auth_current_url();
    return AIGM_BASE_URL . '/aiknowledgesns.php?aks_logout=1&return=' . urlencode($return);
}

function url2ai_auth_bootstrap() {
    url2ai_auth_start_session();
    $session_user = isset($_SESSION['session_username']) ? $_SESSION['session_username'] : '';
    $logged_in = !empty($_SESSION['session_access_token']) && $session_user !== '';
    return array(
        'logged_in' => $logged_in,
        'session_user' => $session_user,
        'is_admin' => ($session_user === AIGM_ADMIN),
        'login_url' => url2ai_auth_login_url(),
        'logout_url' => url2ai_auth_logout_url(),
    );
}
