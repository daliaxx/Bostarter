<?php
require_once '../../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    SessionManager::start();
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Effettua il login.']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo di richiesta non valido.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

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

        // Esegui direttamente le due stored procedure
        $db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);
        $db->callStoredProcedure('InserisciSkillCurriculum', [$userEmail, $competenza, $livello]);

        echo json_encode(['success' => true, 'message' => "Skill inserita correttamente."]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
}
?>
