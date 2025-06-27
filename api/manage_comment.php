<?php
require_once '../config/database.php';

SessionManager::start();

try {
    if (!SessionManager::isLoggedIn()) {
        // Se è AJAX, manda JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Devi essere loggato per commentare.']);
            exit;
        }
        // Se è form normale, redirect
        header('Location: ../index.html');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../public/projects.php');
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();

    $nomeProgetto = $_POST['nome_progetto'] ?? '';
    $testoCommento = trim($_POST['testo_commento'] ?? '');

    if (empty($nomeProgetto) || empty($testoCommento)) {
        // Se è AJAX, manda JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Nome progetto e testo del commento sono obbligatori.']);
            exit;
        }
        // Se è form normale, redirect con errore
        header('Location: ../public/projects/project_detail.php?name=' . urlencode($nomeProgetto) . '&error=dati_mancanti');
        exit;
    }

    if (strlen($testoCommento) > 500) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Il commento non può superare i 500 caratteri.']);
            exit;
        }
        header('Location: ../public/projects/project_detail.php?name=' . urlencode($nomeProgetto) . '&error=commento_troppo_lungo');
        exit;
    }

    $progettoEsiste = $db->fetchOne("SELECT Nome FROM PROGETTO WHERE Nome = ?", [$nomeProgetto]);
    if (!$progettoEsiste) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Progetto non trovato.']);
            exit;
        }
        header('Location: ../public/projects.php?error=progetto_non_trovato');
        exit;
    }

    $db->callStoredProcedure('InserisciCommento', [
        $userEmail,
        $nomeProgetto,
        $testoCommento
    ]);

    // Se è richiesta AJAX, manda JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Commento aggiunto con successo!']);
        exit;
    }

    // Se è form normale, redirect con successo
    header('Location: ../public/projects/project_detail.php?name=' . urlencode($nomeProgetto) . '&success=commento_aggiunto');
    exit;

} catch (Exception $e) {
    error_log("Errore commento: " . $e->getMessage());

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
        exit;
    }

    $nomeProgetto = $_POST['nome_progetto'] ?? '';
    header('Location: ../public/projects/project_detail.php?name=' . urlencode($nomeProgetto) . '&error=errore_inserimento');
    exit;
}
?>