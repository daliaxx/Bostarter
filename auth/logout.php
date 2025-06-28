<?php
/**
 * BOSTARTER - Logout
 * File: auth/logout.php
 */

require_once '../config/database.php';

// Intestazioni per prevenire il caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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