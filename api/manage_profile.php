<?php

require_once '../config/database.php'; 

$db = Database::getInstance();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

function json_exit($arr) {
    echo json_encode($arr);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'add')) {
    // AGGIUNTA PROFILO
    $projectName = trim($_POST['project_name'] ?? '');
    $profileName = trim($_POST['profile_name'] ?? '');
    $profileSkills = $_POST['profile_skills'] ?? [];

    if (empty($projectName) || empty($profileName)) {
        json_exit(['success'=>false,'message'=>'Nome progetto o nome profilo mancante.']);
    }
    if (empty($profileSkills)) {
        json_exit(['success'=>false,'message'=>'Almeno una skill è richiesta per il profilo.']);
    }
    $userEmail = SessionManager::getUserEmail();
    $projectCheck = $db->fetchOne("SELECT Nome, Tipo FROM PROGETTO WHERE Nome = ? AND Email_Creatore = ? AND Tipo = 'Software'", [$projectName, $userEmail]);
    if (!$projectCheck) {
        json_exit(['success'=>false,'message'=>'Progetto non trovato, non autorizzato o non è un progetto Software.']);
    }
    try {
        $db->beginTransaction();
        $stmt = $db->callStoredProcedure('InserisciProfiloRichiesto', [$profileName, $projectName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $profileId = $result['ID_Profilo'] ?? null;
        $stmt->closeCursor();
        if (!$profileId) throw new Exception("Impossibile ottenere l'ID del profilo appena inserito.");
        foreach ($profileSkills as $skill) {
            $competenza = trim($skill['competenza'] ?? '');
            $livello = intval($skill['livello'] ?? 0);
            if (empty($competenza) || $livello < 0 || $livello > 5) {
                throw new Exception("Dati skill non validi: competenza '$competenza', livello '$livello'.");
            }
            $stmtSkill = $db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);
            $stmtSkill->closeCursor();
            $stmtSkillRich = $db->callStoredProcedure('InserisciSkillRichiesta', [$profileId, $competenza, $livello]);
            $stmtSkillRich->closeCursor();
        }
        $db->commit();
        json_exit(['success'=>true,'message'=>'Profilo e skill aggiunti con successo!']);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Errore in manage_profile.php (add): ' . $e->getMessage());
        json_exit(['success'=>false,'message'=>'Errore del database: ' . $e->getMessage()]);
    }
}

if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // ELIMINAZIONE PROFILO
    $profileId = intval($_POST['profile_id'] ?? 0);
    if (!$profileId) json_exit(['success'=>false,'message'=>'ID profilo mancante.']);
    $userEmail = SessionManager::getUserEmail();
    // Verifica che il profilo appartenga a un progetto dell'utente
    $check = $db->fetchOne("SELECT p.ID, pr.Nome, pr.Email_Creatore FROM PROFILO p JOIN PROGETTO pr ON p.Nome_Progetto = pr.Nome WHERE p.ID = ? AND pr.Email_Creatore = ?", [$profileId, $userEmail]);
    if (!$check) json_exit(['success'=>false,'message'=>'Profilo non trovato o non autorizzato.']);
    try {
        $db->beginTransaction();
        // Elimina skill richieste collegate
        $db->execute("DELETE FROM SKILL_RICHIESTA WHERE ID_Profilo = ?", [$profileId]);
        // Elimina il profilo
        $db->execute("DELETE FROM PROFILO WHERE ID = ?", [$profileId]);
        $db->commit();
        json_exit(['success'=>true,'message'=>'Profilo eliminato con successo.']);
    } catch (Exception $e) {
        $db->rollback();
        error_log('Errore in manage_profile.php (delete): ' . $e->getMessage());
        json_exit(['success'=>false,'message'=>'Errore del database: ' . $e->getMessage()]);
    }
}

json_exit(['success'=>false,'message'=>'Metodo di richiesta non valido o parametri insufficienti.']);
?>