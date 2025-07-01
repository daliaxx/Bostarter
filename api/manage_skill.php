<?php
require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log("🔍 manage_skill.php chiamato - Method: " . $_SERVER['REQUEST_METHOD'] . " Action: " . ($_POST['action'] ?? 'nessuna'));

try {
    SessionManager::start();
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Effettua il login.']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();
    $isAdmin = SessionManager::isAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo di richiesta non valido.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // FUNZIONI ESISTENTI PER UTENTI NORMALI
    if ($action === 'add_skill') {
        $competenza = trim($_POST['competenza'] ?? '');
        $livello = $_POST['livello'] ?? '';

        if (empty($competenza)) {
            echo json_encode(['success' => false, 'message' => 'Il nome della competenza non può essere vuoto.']);
            exit;
        }

        if (empty($livello) || !in_array($livello, ['1', '2', '3', '4', '5'])) {
            echo json_encode(['success' => false, 'message' => 'Livello non valido.']);
            exit;
        }

        // Assicura che la skill esista nel sistema (usa admin di default)
        $db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);

        // Aggiunge la skill al curriculum dell'utente
        $db->callStoredProcedure('InserisciSkillCurriculum', [$userEmail, $competenza, $livello]);

        echo json_encode(['success' => true, 'message' => "Skill inserita correttamente."]);
        exit;
    }

    // NUOVE FUNZIONI PER AMMINISTRATORI
    if ($action === 'get_all_skills') {
        // Recupera tutte le competenze uniche (solo admin)
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Accesso negato. Solo gli amministratori possono visualizzare tutte le competenze.']);
            exit;
        }

        $skills = $db->fetchAll("
            SELECT DISTINCT Competenza 
            FROM SKILL 
            ORDER BY Competenza ASC
        ");

        echo json_encode([
            'success' => true,
            'skills' => $skills,
            'message' => 'Competenze recuperate con successo.',
            'count' => count($skills)
        ]);
        exit;
    }

    if ($action === 'add_new_competence') {
        // Aggiunge una nuova competenza al sistema (solo admin)
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Accesso negato. Solo gli amministratori possono aggiungere nuove competenze.']);
            exit;
        }

        $competenza = trim($_POST['competenza'] ?? '');

        if (empty($competenza)) {
            echo json_encode(['success' => false, 'message' => 'Il nome della competenza non può essere vuoto.']);
            exit;
        }

        if (strlen($competenza) < 2) {
            echo json_encode(['success' => false, 'message' => 'Il nome della competenza deve essere di almeno 2 caratteri.']);
            exit;
        }

        if (strlen($competenza) > 100) {
            echo json_encode(['success' => false, 'message' => 'Il nome della competenza non può superare i 100 caratteri.']);
            exit;
        }

        // Verifica se la competenza esiste già
        $existingSkill = $db->fetchOne("
            SELECT Competenza 
            FROM SKILL 
            WHERE Competenza = ? 
            LIMIT 1
        ", [$competenza]);

        if ($existingSkill) {
            echo json_encode(['success' => false, 'message' => 'La competenza "' . htmlspecialchars($competenza) . '" esiste già nel sistema.']);
            exit;
        }

        // Inserimento della competenza con tutti i livelli da 1 a 5 usando InserisciSkillAdmin
        try {
            $db->beginTransaction();

            for ($livello = 1; $livello <= 5; $livello++) {
                // Usa la procedura specifica per admin che accetta l'email dell'admin
                $db->callStoredProcedure('InserisciSkillAdmin', [$competenza, $livello, $userEmail]);
            }

            $db->commit();

            // Log dell'operazione
            error_log("Admin {$userEmail} ha aggiunto la competenza: {$competenza} (livelli 1-5)");

            echo json_encode([
                'success' => true,
                'message' => 'Competenza "' . htmlspecialchars($competenza) . '" aggiunta con successo (livelli 1-5).',
                'competenza' => $competenza
            ]);

        } catch (Exception $e) {
            $db->rollback();
            error_log("Errore aggiunta competenza {$competenza}: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'inserimento: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_available_skills') {
        // Recupera competenze disponibili per il form utente
        $skills = $db->fetchAll("
            SELECT DISTINCT Competenza 
            FROM SKILL 
            ORDER BY Competenza ASC
        ");

        echo json_encode([
            'success' => true,
            'skills' => $skills
        ]);
        exit;
    }

    // Elimina competenza (solo admin)
    if ($action === 'delete_competence') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Accesso negato. Solo gli amministratori possono eliminare competenze.']);
            exit;
        }

        $competenza = trim($_POST['competenza'] ?? '');

        if (empty($competenza)) {
            echo json_encode(['success' => false, 'message' => 'Il nome della competenza è obbligatorio.']);
            exit;
        }

        // Verifica se la competenza è utilizzata in curriculum
        $usedInCurriculum = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM SKILL_CURRICULUM 
            WHERE Competenza = ?
        ", [$competenza]);

        if ($usedInCurriculum['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: competenza utilizzata in ' . $usedInCurriculum['count'] . ' curriculum.']);
            exit;
        }

        // Verifica se la competenza è utilizzata in skill richieste
        $usedInRequired = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM SKILL_RICHIESTA 
            WHERE Competenza = ?
        ", [$competenza]);

        if ($usedInRequired['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: competenza richiesta in ' . $usedInRequired['count'] . ' profili.']);
            exit;
        }

        try {
            // Elimina tutti i livelli della competenza
            $db->execute("DELETE FROM SKILL WHERE Competenza = ?", [$competenza]);

            echo json_encode([
                'success' => true,
                'message' => 'Competenza "' . htmlspecialchars($competenza) . '" eliminata con successo.'
            ]);

        } catch (Exception $e) {
            error_log("Errore eliminazione competenza {$competenza}: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione: ' . $e->getMessage()]);
        }
        exit;
    }

    // Azione non riconosciuta
    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta: ' . $action]);

} catch (Exception $e) {
    error_log("Errore manage_skill.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server: ' . $e->getMessage()]);
}
?>