<?php
require_once '../config/bootstrap.php';

header('Content-Type: application/json');
session_start();

if (!SessionManager::get('user_email')) {
    echo json_encode([
        'success' => false,
        'message' => 'Utente non autenticato'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'email' => SessionManager::get('user_email'),
        'nickname' => SessionManager::get('user_nickname'),
        'is_admin' => SessionManager::get('is_admin'),
        'is_creator' => SessionManager::get('is_creator')
    ]
]);
exit;
