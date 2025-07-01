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


    // AGGIUNGI COMPONENTE
    if ($action === 'add_component') {
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');
        $nomeComponente = trim($_POST['nome_componente'] ?? '');
        $descrizione = trim($_POST['descrizione'] ?? '');
        $prezzo = floatval($_POST['prezzo'] ?? 0);
        $quantita = intval($_POST['quantita'] ?? 0);

        // Validazioni
        if (empty($nomeProgetto) || empty($nomeComponente) || empty($descrizione)) {
            echo json_encode(['success' => false, 'message' => 'Tutti i campi sono obbligatori']);
            exit;
        }

        if ($prezzo <= 0) {
            echo json_encode(['success' => false, 'message' => 'Il prezzo deve essere maggiore di zero']);
            exit;
        }

        if ($quantita <= 0) {
            echo json_encode(['success' => false, 'message' => 'La quantità deve essere maggiore di zero']);
            exit;
        }

        // Verifica che il progetto appartenga all'utente
        $progetto = $db->fetchOne("
            SELECT Nome, Tipo FROM PROGETTO 
            WHERE Nome = ? AND Email_Creatore = ?
        ", [$nomeProgetto, $userEmail]);

        if (!$progetto) {
            echo json_encode(['success' => false, 'message' => 'Progetto non trovato o non autorizzato']);
            exit;
        }

        if ($progetto['Tipo'] !== 'Hardware') {
            echo json_encode(['success' => false, 'message' => 'I componenti possono essere aggiunti solo ai progetti Hardware']);
            exit;
        }

        // Verifica che il nome componente non esista già per questo progetto
        $existingComponent = $db->fetchOne("
            SELECT Nome FROM COMPONENTE 
            WHERE Nome = ? AND Nome_Progetto = ?
        ", [$nomeComponente, $nomeProgetto]);

        if ($existingComponent) {
            echo json_encode(['success' => false, 'message' => 'Componente con questo nome già esistente per il progetto']);
            exit;
        }

        // Inserisci componente
        $db->execute("
            INSERT INTO COMPONENTE (Nome, Descrizione, Prezzo, Quantita, Nome_Progetto) 
            VALUES (?, ?, ?, ?, ?)
        ", [$nomeComponente, $descrizione, $prezzo, $quantita, $nomeProgetto]);

        echo json_encode([
            'success' => true,
            'message' => 'Componente aggiunto con successo',
            'component' => [
                'nome' => $nomeComponente,
                'descrizione' => $descrizione,
                'prezzo' => $prezzo,
                'quantita' => $quantita,
                'totale' => $prezzo * $quantita
            ]
        ]);
        exit;
    }

    // RECUPERA COMPONENTI PROGETTO
    if ($action === 'get_components') {
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        if (empty($nomeProgetto)) {
            echo json_encode(['success' => false, 'message' => 'Nome progetto obbligatorio']);
            exit;
        }

        // Verifica che il progetto appartenga all'utente
        $progetto = $db->fetchOne("
            SELECT Nome, Tipo FROM PROGETTO 
            WHERE Nome = ? AND Email_Creatore = ?
        ", [$nomeProgetto, $userEmail]);

        if (!$progetto) {
            echo json_encode(['success' => false, 'message' => 'Progetto non trovato o non autorizzato']);
            exit;
        }

        // Recupera componenti
        $components = $db->fetchAll("
            SELECT ID, Nome, Descrizione, Prezzo, Quantita, 
                   (Prezzo * Quantita) as Totale
            FROM COMPONENTE 
            WHERE Nome_Progetto = ?
            ORDER BY Nome ASC
        ", [$nomeProgetto]);

        // Calcola totale generale
        $totaleGenerale = 0;
        foreach ($components as $component) {
            $totaleGenerale += $component['Totale'];
        }

        echo json_encode([
            'success' => true,
            'components' => $components,
            'count' => count($components),
            'totale_generale' => $totaleGenerale,
            'progetto_tipo' => $progetto['Tipo']
        ]);
        exit;
    }

    // ELIMINA COMPONENTE
    if ($action === 'delete_component') {
        $componentId = intval($_POST['component_id'] ?? 0);

        if ($componentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID componente non valido']);
            exit;
        }

        // Verifica che il componente appartenga a un progetto dell'utente
        $component = $db->fetchOne("
            SELECT c.ID, c.Nome, c.Nome_Progetto 
            FROM COMPONENTE c
            JOIN PROGETTO p ON c.Nome_Progetto = p.Nome
            WHERE c.ID = ? AND p.Email_Creatore = ?
        ", [$componentId, $userEmail]);

        if (!$component) {
            echo json_encode(['success' => false, 'message' => 'Componente non trovato o non autorizzato']);
            exit;
        }

        // Elimina componente
        $db->execute("DELETE FROM COMPONENTE WHERE ID = ?", [$componentId]);

        echo json_encode([
            'success' => true,
            'message' => 'Componente "' . htmlspecialchars($component['Nome']) . '" eliminato con successo'
        ]);
        exit;
    }

    // RECUPERA COMPONENTI PER VISUALIZZAZIONE PUBBLICA
    if ($action === 'get_components_public') {
        $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

        if (empty($nomeProgetto)) {
            echo json_encode(['success' => false, 'message' => 'Nome progetto obbligatorio']);
            exit;
        }

        // Verifica che il progetto esista
        $progetto = $db->fetchOne("
            SELECT Nome, Tipo FROM PROGETTO WHERE Nome = ?
        ", [$nomeProgetto]);

        if (!$progetto) {
            echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
            exit;
        }

        // Recupera componenti
        $components = $db->fetchAll("
            SELECT Nome, Descrizione, Prezzo, Quantita, 
                   (Prezzo * Quantita) as Totale
            FROM COMPONENTE 
            WHERE Nome_Progetto = ?
            ORDER BY Nome ASC
        ", [$nomeProgetto]);

        // Calcola totale generale
        $totaleGenerale = 0;
        foreach ($components as $component) {
            $totaleGenerale += $component['Totale'];
        }

        echo json_encode([
            'success' => true,
            'components' => $components,
            'count' => count($components),
            'totale_generale' => $totaleGenerale,
            'progetto_tipo' => $progetto['Tipo']
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta']);

} catch (Exception $e) {
    error_log("Errore manage_components: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno: ' . $e->getMessage()]);
}
?>