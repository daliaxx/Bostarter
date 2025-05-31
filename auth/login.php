<?php
/**
 * BOSTARTER - Login Handler
 * File: auth/login.php
 */

require_once '../config/database.php';

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::jsonResponse(false, 'Metodo non consentito');
}

try {
    // Input sanitization
    $email = Utils::sanitize($_POST['email'] ?? '');
    $password = Utils::sanitize($_POST['password'] ?? '');

    // Validazione
    if (empty($email) || empty($password)) {
        Utils::jsonResponse(false, 'Email e password sono obbligatori');
    }

    if (!Utils::validateEmail($email)) {
        Utils::jsonResponse(false, 'Formato email non valido');
    }

    $db = Database::getInstance();

    // Verifica utente esistente
    $user = $db->fetchOne("SELECT * FROM UTENTE WHERE Email = ?", [$email]);
    if (!$user) {
        Utils::jsonResponse(false, 'Credenziali non valide');
    }

    // Verifica password (nel tuo DB attuale sono in chiaro)
    if ($password !== $user['Password']) {
        Utils::jsonResponse(false, 'Credenziali non valide');
    }

    // Verifica ruoli
    $isAdmin = $db->fetchOne("SELECT 1 FROM AMMINISTRATORE WHERE Email = ?", [$email]) !== false;
    $creatorData = $db->fetchOne("SELECT Nr_Progetti, Affidabilita FROM CREATORE WHERE Email = ?", [$email]);
    $isCreator = $creatorData !== false;

    // Imposta sessione
    SessionManager::set('user_email', $user['Email']);
    SessionManager::set('user_nickname', $user['Nickname']);
    SessionManager::set('user_nome', $user['Nome']);
    SessionManager::set('user_cognome', $user['Cognome']);
    SessionManager::set('is_admin', $isAdmin);
    SessionManager::set('is_creator', $isCreator);

    if ($isCreator) {
        SessionManager::set('creator_progetti', $creatorData['Nr_Progetti']);
        SessionManager::set('creator_affidabilita', $creatorData['Affidabilita']);
    }

    // Log
    error_log("✅ Login: $email");

    // Determina redirect
    $redirect = '../public/projects.php';
    if ($isAdmin) {
        $redirect = '../public/dashboard/admin_dashboard.php';
    } elseif ($isCreator) {
        $redirect = '../public/dashboard/creator_dashboard.php';
    }

    Utils::jsonResponse(true, 'Login effettuato con successo!', [
        'user' => [
            'email' => $user['Email'],
            'nickname' => $user['Nickname'],
            'nome' => $user['Nome'],
            'cognome' => $user['Cognome'],
            'is_admin' => $isAdmin,
            'is_creator' => $isCreator
        ]
    ], $redirect);

} catch (Exception $e) {
    error_log("❌ Errore login: " . $e->getMessage());
    Utils::jsonResponse(false, 'Errore interno. Riprova più tardi.');
}
?>