<?php
/**
 * Configurazione Base BOSTARTER
 * File di configurazione principale del sistema
 */

// Configurazione errori per sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurazione database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'BOSTARTER');

// Configurazione applicazione
define('APP_NAME', 'BOSTARTER');
define('APP_URL', 'http://localhost/bostarter');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('BASE_PATH', __DIR__ . '/..');

// Sicurezza
define('SESSION_TIMEOUT', 3600); // 1 ora
define('HASH_ALGO', 'sha256');

// Debug (cambia a false in produzione)
define('DEBUG', true);

// Timezone
date_default_timezone_set('Europe/Rome');

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload delle classi
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Funzioni helper globali
function debug($data, $die = false) {
    if (DEBUG) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        if ($die) die();
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_email']);
}

function getCurrentUser() {
    return $_SESSION['user_data'] ?? null;
}

// Log degli errori
function logError($message, $context = []) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log(json_encode($log), 3, BASE_PATH . '/logs/error.log');
}

// Crea directory necessarie se non esistono
$directories = [
    UPLOAD_DIR,
    BASE_PATH . '/logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
