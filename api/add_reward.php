<?php
/**
 * BOSTARTER - API Aggiungi Reward
 * File: api/add_reward.php
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
    $descrizione = trim($_POST['descrizione'] ?? '');
    $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

    // Validazioni
    if (empty($codice) || empty($descrizione) || empty($nomeProgetto)) {
        echo json_encode(['success' => false, 'message' => 'Tutti i campi sono obbligatori']);
        exit;
    }

    // Verifica che il progetto appartenga all'utente
    $progetto = $db->fetchOne("
        SELECT Nome FROM PROGETTO 
        WHERE Nome = ? AND Email_Creatore = ?
    ", [$nomeProgetto, $userEmail]);

    if (!$progetto) {
        echo json_encode(['success' => false, 'message' => 'Progetto non trovato o non autorizzato']);
        exit;
    }

    // Verifica che il codice reward non esista già
    $existingReward = $db->fetchOne("
        SELECT Codice FROM REWARD WHERE Codice = ?
    ", [$codice]);

    if ($existingReward) {
        echo json_encode(['success' => false, 'message' => 'Codice reward già esistente']);
        exit;
    }

    // Inserisci la reward
    $db->execute("
        INSERT INTO REWARD (Codice, Descrizione, Foto, Nome_Progetto) 
        VALUES (?, ?, 'default_reward.jpg', ?)
    ", [$codice, $descrizione, $nomeProgetto]);

    echo json_encode(['success' => true, 'message' => 'Reward aggiunta con successo']);

} catch (Exception $e) {
    error_log("Errore add_reward: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno: ' . $e->getMessage()]);
}
?>