<?php

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
    $action = $_POST['action'] ?? '';

    // AGGIUNGI REWARD
    if ($action === 'add_reward') {
        $codice = trim($_POST['codice'] ?? '');
        $descrizione = trim($_POST['descrizione'] ?? '');
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        // Validazioni
        if (empty($codice) || empty($descrizione) || empty($nomeProgetto)) {
            echo json_encode(['success' => false, 'message' => 'Tutti i campi sono obbligatori']);
            exit;
        }

        if (strlen($codice) < 2 || strlen($codice) > 50) {
            echo json_encode(['success' => false, 'message' => 'Il codice deve essere tra 2 e 50 caratteri']);
            exit;
        }

        if (strlen($descrizione) < 5 || strlen($descrizione) > 500) {
            echo json_encode(['success' => false, 'message' => 'La descrizione deve essere tra 5 e 500 caratteri']);
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

        // Gestione upload foto
        $fotoPath = 'img/default_reward.jpg';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../img/';
            $fileName = uniqid('reward_') . '_' . basename($_FILES['foto']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
                $fotoPath = 'img/' . $fileName;
            }
        }

        // Inserimento della reward
        $db->execute("CALL InserisciReward(?, ?, ?, ?)", [$codice, $descrizione, $fotoPath, $nomeProgetto]);

        echo json_encode([
            'success' => true,
            'message' => 'Reward aggiunta con successo',
            'reward' => [
                'codice' => $codice,
                'descrizione' => $descrizione,
                'nome_progetto' => $nomeProgetto
            ]
        ]);
        exit;
    }

    // ELIMINA REWARD
    if ($action === 'delete_reward') {
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

        echo json_encode([
            'success' => true,
            'message' => 'Reward eliminata con successo',
            'deleted_reward' => $codice
        ]);
        exit;
    }

    // RECUPERA REWARD DI UN PROGETTO
    if ($action === 'get_rewards') {
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        if (empty($nomeProgetto)) {
            echo json_encode(['success' => false, 'message' => 'Nome progetto obbligatorio']);
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

        // Recupera le reward del progetto
        $rewards = $db->fetchAll("
            SELECT Codice, Descrizione, Foto
            FROM REWARD 
            WHERE Nome_Progetto = ?
            ORDER BY Codice ASC
        ", [$nomeProgetto]);

        echo json_encode([
            'success' => true,
            'rewards' => $rewards,
            'count' => count($rewards),
            'progetto' => $nomeProgetto
        ]);
        exit;
    }

    // RECUPERA REWARD PER VISUALIZZAZIONE PUBBLICA
    if ($action === 'get_rewards_public') {
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        if (empty($nomeProgetto)) {
            echo json_encode(['success' => false, 'message' => 'Nome progetto obbligatorio']);
            exit;
        }

        // Verifica che il progetto esista (non serve essere il creatore)
        $progetto = $db->fetchOne("
            SELECT Nome FROM PROGETTO WHERE Nome = ?
        ", [$nomeProgetto]);

        if (!$progetto) {
            echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
            exit;
        }

        // Recupera le reward del progetto
        $rewards = $db->fetchAll("
            SELECT Codice, Descrizione
            FROM REWARD 
            WHERE Nome_Progetto = ?
            ORDER BY Codice ASC
        ", [$nomeProgetto]);

        echo json_encode([
            'success' => true,
            'rewards' => $rewards,
            'count' => count($rewards)
        ]);
        exit;
    }

    // VERIFICA AVAILABILITY REWARD
    if ($action === 'check_code_availability') {
        $codice = trim($_POST['codice'] ?? '');

        if (empty($codice)) {
            echo json_encode(['success' => false, 'message' => 'Codice obbligatorio']);
            exit;
        }

        // Verifica se il codice esiste già
        $existingReward = $db->fetchOne("
            SELECT Codice FROM REWARD WHERE Codice = ?
        ", [$codice]);

        echo json_encode([
            'success' => true,
            'available' => $existingReward === false,
            'message' => $existingReward ? 'Codice già in uso' : 'Codice disponibile'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta: ' . $action]);

} catch (Exception $e) {
    error_log("Errore manage_rewards: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno: ' . $e->getMessage()]);
}
?>