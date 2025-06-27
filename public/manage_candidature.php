<?php
require_once '../config/bootstrap.php';

header('Content-Type: application/json');

session_start();

$p_Email = $_SESSION['user_email'] ?? null;
$p_IDProfilo = $_POST['id_profilo'] ?? null;

if (!$p_Email || !$p_IDProfilo) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti per la candidatura.']);
    exit;
}

$db = Database::getInstance();

try {
    // 🔒 1. Controllo: l'utente è il creatore del progetto?
    $creatore = $db->fetchOne("
        SELECT p.Email_Creatore
        FROM PROFILO pr
        JOIN PROGETTO p ON pr.ID_Progetto = p.Nome
        WHERE pr.ID = ?
    ", [$p_IDProfilo]);

    if ($creatore && $creatore['Email_Creatore'] === $p_Email) {
        echo json_encode(['success' => false, 'message' => 'Non puoi candidarti al tuo stesso progetto.']);
        exit;
    }

    // ✅ 2. Chiama la stored procedure
    $stmt = $db->callStoredProcedure("InserisciCandidatura", [$p_Email, $p_IDProfilo]);
    $stmt->closeCursor();

    echo json_encode(['success' => true, 'message' => 'Candidatura inviata con successo.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Errore procedura: ' . $e->getMessage()]);
}
