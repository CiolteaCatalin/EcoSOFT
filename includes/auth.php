<?php
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

if (session_status() !== PHP_SESSION_ACTIVE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');

    session_start();
}

require_once __DIR__ . '/functions.php';

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('/login.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Timeout sesiune
    |--------------------------------------------------------------------------
    */

    $timeout = 8 * 60 * 60;

    if (
        !empty($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > $timeout
    ) {

        session_unset();
        session_destroy();

        redirect('/login.php');
    }

    $_SESSION['last_activity'] = time();
}


function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
    ];
}