<?php
/**
 * BOSTARTER - Logout
 * File: auth/logout.php
 */

require_once '../config/database.php';

// Avvia sessione
SessionManager::start();

// Log del logout (opzionale)
if (SessionManager::isLoggedIn()) {
    $userEmail = SessionManager::getUserEmail();
    error_log("✅ Logout: $userEmail");
}

// Distruggi sessione
SessionManager::destroy();

// Redirect alla home
Utils::redirect('../index.html');
?>