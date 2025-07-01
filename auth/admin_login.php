<?php

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::jsonResponse(false, 'Metodo non consentito');
}

try {
    // Input
    $email = $_POST['admin_email'] ?? '';
    $password = $_POST['admin_password'] ?? '';
    $codiceSicurezza = $_POST['admin_code'] ?? '';

    // Validazione
    if (empty($email) || empty($password) || empty($codiceSicurezza)) {
        Utils::jsonResponse(false, 'Tutti i campi sono obbligatori per l\'accesso amministratore');
    }
    if (!Utils::validateEmail($email)) {
        Utils::jsonResponse(false, 'Formato email non valido');
    }

    $db = Database::getInstance();

    // Verifica credenziali con stored procedure
    $stmt = $db->callStoredProcedure('LoginAmministratore', [$email, $password, $codiceSicurezza]);
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if (!$result || $result['Email'] === null) {
        Utils::jsonResponse(false, 'Credenziali amministratore non valide');
    }

    // Recupera dati
    $admin = $db->fetchOne("
        SELECT u.Email, u.Nickname, u.Nome, u.Cognome, a.Codice_Sicurezza 
        FROM UTENTE u 
        JOIN AMMINISTRATORE a ON u.Email = a.Email 
        WHERE u.Email = ?
    ", [$email]);

    if (!$admin) {
        Utils::jsonResponse(false, 'Errore nel recupero dati amministratore');
    }

    // Controlla se è anche creatore
    $isCreator = $db->fetchOne("SELECT 1 FROM CREATORE WHERE Email = ?", [$email]) !== false;

    if ($isCreator) {
        $creatorData = $db->fetchOne("SELECT Nr_Progetti, Affidabilita FROM CREATORE WHERE Email = ?", [$email]);
    }

    SessionManager::set('user_email', $admin['Email']);
    SessionManager::set('user_nickname', $admin['Nickname']);
    SessionManager::set('user_nome', $admin['Nome']);
    SessionManager::set('user_cognome', $admin['Cognome']);
    SessionManager::set('is_admin', true);
    SessionManager::set('is_creator', $isCreator);
    SessionManager::set('admin_code', $admin['Codice_Sicurezza']);
    SessionManager::set('login_time', time()); // Timestamp di login

    // Rigenera ID sessione per sicurezza
    SessionManager::regenerateSession();

    if ($isCreator && !empty($creatorData)) {
        SessionManager::set('creator_progetti', $creatorData['Nr_Progetti']);
        SessionManager::set('creator_affidabilita', $creatorData['Affidabilita']);
    }


    Utils::jsonResponse(true, 'Accesso amministratore effettuato con successo!', [
        'user' => [
            'email' => $admin['Email'],
            'nickname' => $admin['Nickname'],
            'nome' => $admin['Nome'],
            'cognome' => $admin['Cognome'],
            'is_admin' => true,
            'is_creator' => $isCreator
        ]
    ], '/Bostarter/public/dashboard/admin_dashboard.php');

} catch (Exception $e) {
    Utils::jsonResponse(false, 'Errore interno del server. Riprova più tardi.');
}
