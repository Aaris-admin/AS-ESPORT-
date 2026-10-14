<?php
/**
 * AS ESPORT - Core configuration bootstrap.
 *
 * Loaded by every entry point. Establishes the IST timezone contract for the
 * whole application: PHP renders in Asia/Kolkata and MySQL sessions run at
 * +05:30 so DATETIME values are always stored as wall-clock IST.
 */

// ---- Timezone contract (CRITICAL) -----------------------------------------
date_default_timezone_set('Asia/Kolkata');
define('APP_TIMEZONE', 'Asia/Kolkata');
define('DB_TIMEZONE_OFFSET', '+05:30');

// ---- Environment ----------------------------------------------------------
// APP_ENV=production must be set in the real deployment. Anything else keeps
// verbose error reporting on for local work.
define('APP_ENV', getenv('APP_ENV') ?: 'development');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ---- Database -------------------------------------------------------------
// Override any of these with real environment variables in production.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'asesport');
define('DB_USER', getenv('DB_USER') ?: 'asesport');
define('DB_PASS', getenv('DB_PASS') ?: 'asesport_pass');

// ---- Site -----------------------------------------------------------------
define('SITE_NAME', 'AS ESPORT');
define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));

// Official support channels.
define('SUPPORT_WHATSAPP', 'https://wa.me/918393833973');
define('SUPPORT_EMAIL', 'help.asesprot@gmail.com');
define('SUPPORT_INSTAGRAM', 'https://www.instagram.com/as_esports_tournament_');
define('SUPPORT_YOUTUBE', 'https://www.youtube.com/@AsEsportoffcial');
define('PROMO_WHATSAPP_CHANNEL', 'https://whatsapp.com/channel/0029VbDBww6CBtxHx3WoZA2W');
define('PROMO_TELEGRAM', 'https://t.me/asesport_official');

// ---- Session hardening ----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_name('ASESPORT_SESS');
    session_start();
}

// ---- Global helpers -------------------------------------------------------
require_once __DIR__ . '/common/functions.php';

// ---- Database handle ------------------------------------------------------
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    // Pin the session clock so NOW() and stored DATETIME agree with IST.
    $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    if (APP_ENV === 'production') {
        http_response_code(500);
        exit('Service temporarily unavailable.');
    }
    http_response_code(500);
    exit('Database connection failed: ' . $e->getMessage());
}
