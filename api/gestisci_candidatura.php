<?php
/**
 * BOSTARTER - Gestione Candidature (Accetta/Rifiuta) - VERSIONE CORRETTA
 * File: api/gestisci_candidatura.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

try {
    // ✅ Verifica login e che sia creatore
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato - Login richiesto']);
        exit;
    }

    if (!SessionManager::isCreator()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato - Solo i creatori possono gestire candidature']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo non valido - POST richiesto']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();

    // ✅ Validazione input migliorata
    $idCandidatura = intval($_POST['id_candidatura'] ?? 0);
    $accettata = $_POST['accettata'] ?? '';

    // Debug per capire cosa arriva
    error_log("🔍 Gestione candidatura - ID: $idCandidatura, Accettata: '$accettata', User: $userEmail");

    if ($idCandidatura <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID candidatura non valido']);
        exit;
    }

    // Converti accettata in boolean
    $accettataBoolean = ($accettata === '1' || $accettata === 'true' || $accettata === true);

    // ✅ Verifica che la candidatura esista e appartenga all'utente loggato
    $candidatura = $db->fetchOne("
        SELECT 
            c.ID, 
            c.Email_Utente, 
            c.Esito,
            pr.Nome as Nome_Profilo, 
            p.Nome as Nome_Progetto, 
            p.Email_Creatore,
            u.Nickname,
            u.Nome,
            u.Cognome
        FROM CANDIDATURA c
        JOIN PROFILO pr ON c.ID_Profilo = pr.ID
        JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
        JOIN UTENTE u ON c.Email_Utente = u.Email
        WHERE c.ID = ? AND p.Email_Creatore = ?
    ", [$idCandidatura, $userEmail]);

    if (!$candidatura) {
        echo json_encode(['success' => false, 'message' => 'Candidatura non trovata o non autorizzato']);
        exit;
    }

    // ✅ Verifica che la candidatura sia ancora in attesa
    if ($candidatura['Esito'] !== null) {
        $esitoAttuale = $candidatura['Esito'] ? 'accettata' : 'rifiutata';
        echo json_encode(['success' => false, 'message' => "Candidatura già {$esitoAttuale}"]);
        exit;
    }

    // ✅ Aggiorna la candidatura usando la stored procedure
    try {
        $stmt = $db->callStoredProcedure('AccettaCandidatura', [$idCandidatura, $accettataBoolean ? 1 : 0]);

        // Verifica che la stored procedure sia stata eseguita
        if ($stmt) {
            $stmt->closeCursor();
        }

        $messaggio = $accettataBoolean ? 'Candidatura accettata con successo!' : 'Candidatura rifiutata';

        // ✅ Risposta completa con dati della candidatura
        echo json_encode([
            'success' => true,
            'message' => $messaggio,
            'candidatura' => [
                'id' => $idCandidatura,
                'utente' => $candidatura['Email_Utente'],
                'nickname' => $candidatura['Nickname'],
                'nome_completo' => $candidatura['Nome'] . ' ' . $candidatura['Cognome'],
                'profilo' => $candidatura['Nome_Profilo'],
                'progetto' => $candidatura['Nome_Progetto'],
                'esito' => $accettataBoolean
            ]
        ]);

        // Log per debug
        error_log("✅ Candidatura {$idCandidatura} " . ($accettataBoolean ? 'accettata' : 'rifiutata') . " da {$userEmail}");

    } catch (Exception $e) {
        error_log("❌ Errore stored procedure AccettaCandidatura: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento della candidatura']);
        exit;
    }

} catch (Exception $e) {
    error_log("❌ Errore gestione candidatura: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>