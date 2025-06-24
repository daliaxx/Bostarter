<?php
/**
 * BOSTARTER - Gestione Candidature (Accetta/Rifiuta)
 * File: api/gestisci_candidatura.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

try {
    // Verifica login e che sia creatore
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo non valido']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();

    $idCandidatura = intval($_POST['id_candidatura'] ?? 0);
    $accettata = $_POST['accettata'] === '1'; // true se accettata, false se rifiutata

    if ($idCandidatura <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID candidatura non valido']);
        exit;
    }

    // Verifica che la candidatura esista e che il progetto appartenga all'utente loggato
    $candidatura = $db->fetchOne("
        SELECT c.ID, c.Email_Utente, pr.Nome as Nome_Profilo, p.Nome as Nome_Progetto, p.Email_Creatore
        FROM CANDIDATURA c
        JOIN PROFILO pr ON c.ID_Profilo = pr.ID
        JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
        WHERE c.ID = ? AND p.Email_Creatore = ?
    ", [$idCandidatura, $userEmail]);

    if (!$candidatura) {
        echo json_encode(['success' => false, 'message' => 'Candidatura non trovata o non autorizzato']);
        exit;
    }

    // Aggiorna la candidatura usando la stored procedure
    $db->callStoredProcedure('AccettaCandidatura', [$idCandidatura, $accettata ? 1 : 0]);

    $messaggio = $accettata ? 'Candidatura accettata con successo!' : 'Candidatura rifiutata';

    echo json_encode([
        'success' => true,
        'message' => $messaggio,
        'candidatura' => [
            'id' => $idCandidatura,
            'utente' => $candidatura['Email_Utente'],
            'profilo' => $candidatura['Nome_Profilo'],
            'progetto' => $candidatura['Nome_Progetto'],
            'esito' => $accettata
        ]
    ]);

} catch (Exception $e) {
    error_log("Errore gestione candidatura: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno: ' . $e->getMessage()]);
}
?>