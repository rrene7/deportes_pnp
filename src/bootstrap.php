<?php

declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Falta config/config.php. Ejecute bash install.sh desde Git Bash o use install.php.');
}

$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'America/Panama');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('carrera5k_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'use_strict_mode' => true,
    ]);
}

$configuredBase = rtrim((string)($config['app']['base_url'] ?? ''), '/');
if ($configuredBase !== '') {
    define('APP_BASE_URL', $configuredBase);
} else {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $relative = ($docRoot !== '' && str_starts_with(strtolower($appRoot), strtolower($docRoot)))
        ? str_replace('\\', '/', substr($appRoot, strlen($docRoot)))
        : '';
    define('APP_BASE_URL', rtrim($relative, '/'));
}

require_once APP_ROOT . '/src/helpers.php';
require_once APP_ROOT . '/src/auth.php';

$db = $config['db'];
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    exit('No se pudo conectar con MySQL. Revise config/config.php o vuelva a ejecutar bash install.sh.');
}
