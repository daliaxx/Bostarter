<?php
/**
 * BOSTARTER - API Elimina Reward
 * File: api/delete_reward.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    SessionManager::start();

    if (!SessionManager::isLoggedIn() || !SessionManager::isCreator()) {
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metodo non valido']);
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();
    $codice = trim($_POST['codice'] ?? '');

    if (empty($codice)) {
        echo json_encode(['success' => false, 'message' => 'Codice reward obbligatorio']);
        exit;
    }

    // Verifica che la reward appartenga a un progetto dell'utente
    $reward = $db->fetchOne("
        SELECT r.Codice, r.Nome_Progetto 
        FROM REWARD r
        JOIN PROGETTO p ON r.Nome_Progetto = p.Nome
        WHERE r.Codice = ? AND p.Email_Creatore = ?
    ", [$codice, $userEmail]);

    if (!$reward) {
        echo json_encode(['success' => false, 'message' => 'Reward non trovata o non autorizzata']);
        exit;
    }

    // Verifica se la reward è stata scelta in qualche finanziamento
    $usedReward = $db->fetchOne("
        SELECT COUNT(*) as count FROM FINANZIAMENTO 
        WHERE Codice_Reward = ?
    ", [$codice]);

    if ($usedReward['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Impossibile eliminare: reward già scelta da alcuni finanziatori']);
        exit;
    }

    // Elimina la reward
    $db->execute("DELETE FROM REWARD WHERE Codice = ?", [$codice]);

    echo json_encode(['success' => true, 'message' => 'Reward eliminata con successo']);

} catch (Exception $e) {
    error_log("Errore delete_reward: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno: ' . $e->getMessage()]);
}
?>