<?php
/**
 * BOSTARTER - API Gestione Candidature Unificata
 * File: api/manage_candidature.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    SessionManager::start();

    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato - Login richiesto']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo non valido - POST richiesto']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();
    $action = $_POST['action'] ?? 'submit_candidatura'; // Default per retrocompatibilità

    // ================================================================
    // INVIA CANDIDATURA (per utenti normali)
    // ================================================================
    if ($action === 'submit_candidatura') {
        $idProfilo = intval($_POST['profilo'] ?? 0);
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        if ($idProfilo <= 0 || empty($nomeProgetto)) {
            echo json_encode(['success' => false, 'message' => 'Dati mancanti: profilo e nome progetto sono obbligatori']);
            exit;
        }

        // Verifica che il progetto esista e sia di tipo Software
        $progetto = $db->fetchOne("
            SELECT Nome, Tipo, Stato FROM PROGETTO 
            WHERE Nome = ?
        ", [$nomeProgetto]);

        if (!$progetto) {
            echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
            exit;
        }

        if ($progetto['Tipo'] !== 'Software') {
            echo json_encode(['success' => false, 'message' => 'Le candidature sono disponibili solo per progetti Software']);
            exit;
        }

        if ($progetto['Stato'] !== 'aperto') {
            echo json_encode(['success' => false, 'message' => 'Il progetto non accetta più candidature']);
            exit;
        }

        // Verifica che il profilo appartenga al progetto
        $profilo = $db->fetchOne("
            SELECT ID, Nome FROM PROFILO 
            WHERE ID = ? AND Nome_Progetto = ?
        ", [$idProfilo, $nomeProgetto]);

        if (!$profilo) {
            echo json_encode(['success' => false, 'message' => 'Profilo non valido per questo progetto']);
            exit;
        }

        // Verifica che l'utente non abbia già una candidatura per questo profilo
        $candidaturaEsistente = $db->fetchOne("
            SELECT ID FROM CANDIDATURA 
            WHERE Email_Utente = ? AND ID_Profilo = ?
        ", [$userEmail, $idProfilo]);

        if ($candidaturaEsistente) {
            echo json_encode(['success' => false, 'message' => 'Hai già inviato una candidatura per questo profilo']);
            exit;
        }

        // Inserisci candidatura usando stored procedure
        try {
            $db->callStoredProcedure('InserisciCandidatura', [$userEmail, $idProfilo]);

            echo json_encode([
                'success' => true,
                'message' => 'Candidatura inviata con successo!',
                'candidatura' => [
                    'profilo' => $profilo['Nome'],
                    'progetto' => $nomeProgetto,
                    'utente' => $userEmail
                ]
            ]);

            // Log per debug
            error_log("✅ Candidatura inviata - Utente: {$userEmail}, Profilo: {$idProfilo}, Progetto: {$nomeProgetto}");

        } catch (Exception $e) {
            error_log("❌ Errore stored procedure InserisciCandidatura: " . $e->getMessage());

            // Messaggi di errore più user-friendly
            if (strpos($e->getMessage(), 'skill') !== false) {
                echo json_encode(['success' => false, 'message' => 'Non possiedi tutte le competenze richieste per questo profilo']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore durante l\'invio della candidatura: ' . $e->getMessage()]);
            }
        }
        exit;
    }

    // ================================================================
    // GESTISCI CANDIDATURA (per creatori - accetta/rifiuta)
    // ================================================================
    if ($action === 'manage_candidatura') {
        if (!SessionManager::isCreator()) {
            echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato - Solo i creatori possono gestire candidature']);
            exit;
        }

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

        // Verifica che la candidatura esista e appartenga all'utente loggato
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

        // Verifica che la candidatura sia ancora in attesa
        if ($candidatura['Esito'] !== null) {
            $esitoAttuale = $candidatura['Esito'] ? 'accettata' : 'rifiutata';
            echo json_encode(['success' => false, 'message' => "Candidatura già {$esitoAttuale}"]);
            exit;
        }

        // Aggiorna la candidatura usando la stored procedure
        try {
            $stmt = $db->callStoredProcedure('AccettaCandidatura', [$idCandidatura, $accettataBoolean ? 1 : 0]);

            // Verifica che la stored procedure sia stata eseguita
            if ($stmt) {
                $stmt->closeCursor();
            }

            $messaggio = $accettataBoolean ? 'Candidatura accettata con successo!' : 'Candidatura rifiutata';

            // Risposta completa con dati della candidatura
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
        }
        exit;
    }

    // ================================================================
    // RECUPERA CANDIDATURE RICEVUTE (per creatori)
    // ================================================================
    if ($action === 'get_candidature_ricevute') {
        if (!SessionManager::isCreator()) {
            echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato - Solo i creatori possono visualizzare le candidature']);
            exit;
        }

        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        // Query base per tutte le candidature o per un progetto specifico
        $whereClause = "WHERE p.Email_Creatore = ?";
        $params = [$userEmail];

        if (!empty($nomeProgetto)) {
            $whereClause .= " AND p.Nome = ?";
            $params[] = $nomeProgetto;
        }

        $candidatureRicevute = $db->fetchAll("
            SELECT c.ID, c.Data_Candidatura, c.Esito, u.Nickname, u.Nome, u.Cognome,
                pr.Nome as Nome_Profilo, p.Nome as Nome_Progetto
            FROM CANDIDATURA c
            JOIN UTENTE u ON c.Email_Utente = u.Email
            JOIN PROFILO pr ON c.ID_Profilo = pr.ID
            JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
            {$whereClause}
            ORDER BY 
                CASE 
                    WHEN c.Esito IS NULL THEN 0  -- Candidature in attesa vengono prima
                    WHEN c.Esito = 1 THEN 1      -- Candidature accettate
                    ELSE 2                       -- Candidature rifiutate per ultime
                END,
                c.Data_Candidatura DESC
            LIMIT 50
        ", $params);

        // Statistiche
        $candidatureInAttesa = array_filter($candidatureRicevute, function($c) {
            return $c['Esito'] === null;
        });

        echo json_encode([
            'success' => true,
            'candidature' => $candidatureRicevute,
            'count' => count($candidatureRicevute),
            'in_attesa' => count($candidatureInAttesa),
            'progetto' => $nomeProgetto ?: 'tutti'
        ]);
        exit;
    }

    // ================================================================
    // RECUPERA CANDIDATURE INVIATE (per utenti)
    // ================================================================
    if ($action === 'get_candidature_inviate') {
        $candidatureInviate = $db->fetchAll("
            SELECT c.ID, c.Data_Candidatura, c.Esito, 
                pr.Nome as Nome_Profilo, p.Nome as Nome_Progetto,
                u_creatore.Nickname as Creatore_Nickname
            FROM CANDIDATURA c
            JOIN PROFILO pr ON c.ID_Profilo = pr.ID
            JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
            JOIN UTENTE u_creatore ON p.Email_Creatore = u_creatore.Email
            WHERE c.Email_Utente = ?
            ORDER BY c.Data_Candidatura DESC
            LIMIT 20
        ", [$userEmail]);

        echo json_encode([
            'success' => true,
            'candidature' => $candidatureInviate,
            'count' => count($candidatureInviate)
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta: ' . $action]);

} catch (Exception $e) {
    error_log("❌ Errore manage_candidature: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
?>