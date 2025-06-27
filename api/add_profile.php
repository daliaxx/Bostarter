<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * BOSTARTER - API per aggiungere un nuovo profilo richiesto e le sue skill.
 * File: api/add_profile.php
 */

require_once '../config/database.php'; // Usa il percorso relativo corretto

// Assicurati che solo i creatori loggati possano accedere a questa API
// SessionManager::requireLogin('../../index.html'); // Commentato se database.php gestisce la sessione
// SessionManager::requireCreator(); // Commentato se database.php gestisce la sessione

$db = Database::getInstance();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectName = trim($_POST['project_name'] ?? '');
    $profileName = trim($_POST['profile_name'] ?? '');
    $profileSkills = $_POST['profile_skills'] ?? [];

    if (empty($projectName) || empty($profileName)) {
        $response['message'] = 'Nome progetto o nome profilo mancante.';
        echo json_encode($response);
        exit;
    }

    if (empty($profileSkills)) {
        $response['message'] = 'Almeno una skill è richiesta per il profilo.';
        echo json_encode($response);
        exit;
    }

    // Verifica che il progetto appartenga all'utente loggato
    $userEmail = SessionManager::getUserEmail(); // Ottiene l'email utente dalla sessione
    $projectCheck = $db->fetchOne("
        SELECT Nome, Tipo FROM PROGETTO
        WHERE Nome = ? AND Email_Creatore = ? AND Tipo = 'Software'
    ", [$projectName, $userEmail]);

    if (!$projectCheck) {
        $response['message'] = 'Progetto non trovato, non autorizzato o non è un progetto Software.';
        echo json_encode($response);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Inserisci il nuovo profilo e ottieni il suo ID
        // La stored procedure InserisciProfiloRichiesto dovrebbe restituire l'ID inserito
        $stmt = $db->callStoredProcedure('InserisciProfiloRichiesto', [$profileName, $projectName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $profileId = $result['ID_Profilo'] ?? null;

        if (!$profileId) {
            throw new Exception("Impossibile ottenere l'ID del profilo appena inserito.");
        }

        $stmt->closeCursor(); // Chiudi il cursore dello statement

        // 2. Inserisci le skill richieste per il profilo
        foreach ($profileSkills as $skill) {
            $competenza = trim($skill['competenza'] ?? '');
            $livello = intval($skill['livello'] ?? 0);

            if (empty($competenza) || $livello < 0 || $livello > 5) {
                throw new Exception("Dati skill non validi: competenza '$competenza', livello '$livello'.");
            }

            // Inserisci la skill nella tabella SKILL se non esiste già
            $db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);

            // Inserisci la skill richiesta associandola al profilo
            $db->callStoredProcedure('InserisciSkillRichiesta', [$profileId, $competenza, $livello]);
        }

        $db->commit();
        $response['success'] = true;
        $response['message'] = 'Profilo e skill aggiunti con successo!';

    } catch (Exception $e) {
        $db->rollback();
        $response['message'] = 'Errore del database: ' . $e->getMessage();
        // Per debug, logga l'errore completo
        error_log('Errore in add_profile.php: ' . $e->getMessage());
    }
} else {
    $response['message'] = 'Metodo di richiesta non valido.';
}

echo json_encode($response);
?>