<?php

// Definisci costante app
define('BOSTARTER_APP', true);

// Carica configurazione
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/constants.php';

// Autoload classi
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Avvia sessione
SessionManager::start();

$pdo = new PDO("mysql:host=localhost;dbname=bostarter;charset=utf8", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
