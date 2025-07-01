<?php

require_once '../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

// Verifica login
if (!SessionManager::isLoggedIn()) {
    header("Location: ../index.html");
    exit;
}

// Recupera il nome del progetto
$nomeProgetto = $_POST['nome_progetto'] ?? $_GET['name'] ?? null;

if (empty($nomeProgetto)) {
    header("Location: projects/projects.php?error=progetto_non_specificato");
    exit;
}

$emailUtente = SessionManager::getUserEmail();
$db = Database::getInstance();

// Verifica che il progetto esista
$progetto = $db->fetchOne("SELECT Nome, Stato FROM PROGETTO WHERE Nome = ?", [$nomeProgetto]);
if (!$progetto) {
    header("Location: projects/project_detail.php?name=" . urlencode($nomeProgetto) . "&error=progetto_non_trovato");
    exit;
}

// Verifica che il progetto abbia reward (obbligatorie)
$rewards = $db->fetchAll("SELECT Codice, Descrizione FROM REWARD WHERE Nome_Progetto = ? ORDER BY Codice", [$nomeProgetto]);

if (empty($rewards)) {
    header("Location: projects/project_detail.php?name=" . urlencode($nomeProgetto) . "&error=no_rewards");
    exit;
}

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importo = floatval($_POST['importo'] ?? 0);
    $codiceReward = trim($_POST['codice_reward'] ?? '');

    $errore = null;

    if ($importo <= 0) {
        $errore = "importo_non_valido";
    } elseif (empty($codiceReward)) {
        $errore = "reward_obbligatoria";
    } else {
        // Verifica che la reward selezionata esista per questo progetto
        $rewardEsiste = $db->fetchOne("SELECT Codice FROM REWARD WHERE Codice = ? AND Nome_Progetto = ?", [$codiceReward, $nomeProgetto]);
        if (!$rewardEsiste) {
            $errore = "reward_non_valida";
        }
    }

    if ($errore) {
        header("Location: projects/project_detail.php?name=" . urlencode($nomeProgetto) . "&error=" . $errore);
        exit;
    }

    try {
        // Chiama la stored procedure con reward obbligatoria
        $stmt = $db->callStoredProcedure('FinanziaProgetto', [
            $emailUtente,
            $nomeProgetto,
            $importo,
            $codiceReward
        ]);

        // Log per debug
        error_log("Finanziamento inserito - Utente: $emailUtente, Progetto: $nomeProgetto, Importo: $importo, Reward: $codiceReward");

        // Redirect con successo
        header("Location: ../public/projects/project_detail.php?name=" . urlencode($nomeProgetto) . "&success=finanziato");
        exit;

    } catch (Exception $e) {
        error_log("Errore finanziamento: " . $e->getMessage());
        header("Location: projects/project_detail.php?name=" . urlencode($nomeProgetto) . "&error=errore_server");
        exit;
    }
} else {
    // Se non è POST, redirect alla pagina progetto
    header("Location: projects/project_detail.php?name=" . urlencode($nomeProgetto));
    exit;
}
?>