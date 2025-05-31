<?php
/**
 * BOSTARTER - Registration Handler
 * File: auth/register.php
 */

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::jsonResponse(false, 'Metodo non consentito');
}

try {
    // Input sanitization
    $data = [
        'email' => Utils::sanitize($_POST['email'] ?? ''),
        'nickname' => Utils::sanitize($_POST['nickname'] ?? ''),
        'password' => Utils::sanitize($_POST['password'] ?? ''),
        'password_confirm' => Utils::sanitize($_POST['password_confirm'] ?? ''),
        'nome' => Utils::sanitize($_POST['nome'] ?? ''),
        'cognome' => Utils::sanitize($_POST['cognome'] ?? ''),
        'data_nascita' => Utils::sanitize($_POST['data_nascita'] ?? ''),
        'luogo_nascita' => Utils::sanitize($_POST['luogo_nascita'] ?? ''),
        'is_creator' => isset($_POST['is_creator'])
    ];

    // Validazione
    $errors = [];

    foreach (['email', 'nickname', 'password', 'nome', 'cognome', 'data_nascita', 'luogo_nascita'] as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' è obbligatorio';
        }
    }

    if (!Utils::validateEmail($data['email'])) {
        $errors[] = 'Formato email non valido';
    }

    if (strlen($data['password']) < 6) {
        $errors[] = 'La password deve essere di almeno 6 caratteri';
    }

    if ($data['password'] !== $data['password_confirm']) {
        $errors[] = 'Le password non corrispondono';
    }

    if (strlen($data['nickname']) < 3) {
        $errors[] = 'Il nickname deve essere di almeno 3 caratteri';
    }

    // Validazione data nascita
    $dateObj = DateTime::createFromFormat('Y-m-d', $data['data_nascita']);
    if (!$dateObj) {
        $errors[] = 'Formato data di nascita non valido';
    } else {
        $age = (new DateTime())->diff($dateObj)->y;
        if ($age < 13) $errors[] = 'Devi avere almeno 13 anni';
        if ($age > 120) $errors[] = 'Data di nascita non valida';
    }

    if (!empty($errors)) {
        Utils::jsonResponse(false, implode('. ', $errors));
    }

    $db = Database::getInstance();

    // Verifica duplicati
    $existing = $db->fetchOne("SELECT Email FROM UTENTE WHERE Email = ? OR Nickname = ?",
        [$data['email'], $data['nickname']]);
    if ($existing) {
        Utils::jsonResponse(false, 'Email o nickname già esistenti');
    }

    // Registrazione
    $db->beginTransaction();

    try {
        // Registra utente base
        $stmt = $db->callStoredProcedure('RegistraUtente', [
            $data['email'],
            $data['nickname'],
            $data['password'], // In produzione: Utils::hashPassword($data['password'])
            $data['nome'],
            $data['cognome'],
            $data['data_nascita'],
            $data['luogo_nascita']
        ]);

        // Se vuole essere creatore
        if ($data['is_creator']) {
            $db->execute("INSERT INTO CREATORE (Email, Nr_Progetti, Affidabilita) VALUES (?, 0, 0)",
                [$data['email']]);
        }

        $db->commit();

        error_log("✅ Registrazione: " . $data['email'] . ($data['is_creator'] ? ' (Creator)' : ''));

        Utils::jsonResponse(true, 'Registrazione completata! Ora puoi effettuare il login.', [
            'user' => [
                'email' => $data['email'],
                'nickname' => $data['nickname'],
                'nome' => $data['nome'],
                'is_creator' => $data['is_creator']
            ]
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("❌ Errore registrazione: " . $e->getMessage());

    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        Utils::jsonResponse(false, 'Email o nickname già esistenti');
    } else {
        Utils::jsonResponse(false, 'Errore durante la registrazione. Riprova più tardi.');
    }
}
?>