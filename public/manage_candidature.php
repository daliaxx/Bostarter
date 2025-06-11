<?php
require_once '../../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

try {
    if (!SessionManager::isLoggedIn()) {
        header("Location: project_detail.php?error=accesso_non_autorizzato");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: project_detail.php?error=metodo_non_valido");
        exit;
    }

    $db = Database::getInstance();
    $userEmail = SessionManager::getUserEmail();

    $idProfilo = intval($_POST['profilo'] ?? 0);
    $nomeProgetto = trim($_POST['nome_progetto'] ?? '');

    if (empty($idProfilo) || empty($nomeProgetto)) {
        header("Location: project_detail.php?error=dati_mancanti");
        exit;
    }

    $db->callStoredProcedure('InserisciCandidatura', [
        $userEmail,
        $idProfilo
    ]);

    header("Location: project_detail.php?name=" . urlencode($nomeProgetto) . "&success=candidatura_inviata");
    exit;

} catch (Exception $e) {
    header("Location: project_detail.php?name=" . urlencode($nomeProgetto) . "&error=" . urlencode($e->getMessage()));
    exit;
}
?>
