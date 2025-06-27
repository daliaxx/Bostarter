<?php
/**
 * BOSTARTER - Login Handler per utenti
 */

require_once '../config/bootstrap.php'; // include tutto (config, database, session, utils)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::jsonResponse(false, 'Metodo non consentito');
}

try {
    // Sanitizzazione input
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        Utils::jsonResponse(false, 'Inserisci email e password');
    }

    if (!Utils::validateEmail($email)) {
        Utils::jsonResponse(false, 'Email non valida');
    }

    $db = Database::getInstance();

    // Verifica credenziali tramite stored procedure
    $stmt = $db->callStoredProcedure('LoginUtente', [$email, $password]);
    $result = $stmt->fetch();
    $stmt->closeCursor(); // <-- fondamentale

    if (!$result || $result['Email'] === null) {
        Utils::jsonResponse(false, 'Credenziali non valide');
    }

    // Recupera dati dell'utente
    $user = $db->fetchOne("
        SELECT Email, Nickname, Nome, Cognome 
        FROM UTENTE 
        WHERE Email = ?
    ", [$email]);

    if (!$user) {
        Utils::jsonResponse(false, 'Errore nel recupero dati utente');
    }

    // Verifica ruoli
    $admin = $db->fetchOne("SELECT Codice_Sicurezza FROM AMMINISTRATORE WHERE Email = ?", [$email]);
    $isAdmin = $db->fetchOne("SELECT 1 FROM AMMINISTRATORE WHERE Email = ?", [$email]) !== false;
    if ($isAdmin) {
        Utils::jsonResponse(false, 'Gli amministratori devono usare il login dedicato.');
    }
    $isCreator = false;
    $creatorData = null;

    if ($db->fetchOne("SELECT 1 FROM CREATORE WHERE Email = ?", [$email])) {
        $isCreator = true;
        $creatorData = $db->fetchOne("SELECT Nr_Progetti, Affidabilita FROM CREATORE WHERE Email = ?", [$email]);
    }

    // Imposta sessione
    SessionManager::set('user_email', $user['Email']);
    SessionManager::set('user_nickname', $user['Nickname']);
    SessionManager::set('user_nome', $user['Nome']);
    SessionManager::set('user_cognome', $user['Cognome']);
    SessionManager::set('is_admin', $isAdmin);
    SessionManager::set('is_creator', $isCreator);

    if ($isCreator && $creatorData) {
        SessionManager::set('creator_progetti', $creatorData['Nr_Progetti']);
        SessionManager::set('creator_affidabilita', $creatorData['Affidabilita']);
    }

    // Determina redirect
    $redirect = '/Bostarter/public/projects/projects.php';
    if ($isAdmin) {
        $redirect = '/Bostarter/public/dashboard/admin_dashboard.php';
    } elseif ($isCreator) {
        $redirect = '/Bostarter/public/dashboard/creator_dashboard.php';
    }

    // Risposta JSON al frontend
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
    Utils::jsonResponse(false, 'Errore interno: ' . $e->getMessage());
}
