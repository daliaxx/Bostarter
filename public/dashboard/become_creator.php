<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

$email = $_SESSION['user_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();

        $exists = $db->fetchOne("SELECT 1 FROM CREATORE WHERE Email = ?", [$email]);
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Sei già un creatore!']);
            exit;
        }

        $stmt = $db->callStoredProcedure("PromuoviACreatore", [$email]);
        $stmt->closeCursor();
                
        $_SESSION['is_creator'] = true;

        echo json_encode(['success' => true, 'message' => 'Ora sei un creatore!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
    }
}
