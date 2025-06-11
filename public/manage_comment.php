<?php
require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

try {
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Devi essere loggato per commentare.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo di richiesta non valido.']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();

    $nomeProgetto = $_POST['nome_progetto'] ?? '';
    $testoCommento = trim($_POST['testo_commento'] ?? '');

    if (empty($nomeProgetto) || empty($testoCommento)) {
        echo json_encode(['success' => false, 'message' => 'Nome progetto e testo del commento sono obbligatori.']);
        exit;
    }

    if (strlen($testoCommento) > 500) {
        echo json_encode(['success' => false, 'message' => 'Il commento non può superare i 500 caratteri.']);
        exit;
    }

    $progettoEsiste = $db->fetchOne("SELECT Nome FROM PROGETTO WHERE Nome = ?", [$nomeProgetto]);
    if (!$progettoEsiste) {
        echo json_encode(['success' => false, 'message' => 'Progetto non trovato.']);
        exit;
    }

    $db->callStoredProcedure('InserisciCommento', [
        $userEmail, 
        $nomeProgetto, 
        $testoCommento
    ]);

    echo json_encode(['success' => true, 'message' => 'Commento aggiunto con successo!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
}
?>
