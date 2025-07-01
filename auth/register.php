<?php

ob_start();
header('Content-Type: application/json');

require_once '../config/bootstrap.php'; 
try {
    $required = ['nome', 'cognome', 'email', 'nickname', 'password', 'data_nascita', 'luogo_nascita'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo mancante: $field"]);
            ob_end_flush();
            exit;
        }
    }

    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $email = trim($_POST['email']);
    $nickname = trim($_POST['nickname']);
    $password = $_POST['password'];
    $data_nascita = $_POST['data_nascita'];
    $luogo_nascita = trim($_POST['luogo_nascita']);
    $is_creatore = isset($_POST['is_creator']) ? 1 : 0;
    $nr_progetti = $is_creatore ? 0 : null;
    $affidabilita = $is_creatore ? 0 : null;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM UTENTE WHERE email = ? OR nickname = ?");
    $stmt->execute([$email, $nickname]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email o nickname già in uso']);
        ob_end_flush();
        exit;
    }

    $stmt = $pdo->prepare("CALL RegistraUtente(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $email,
        $nickname,
        $password,
        $nome,
        $cognome,
        $data_nascita,
        $luogo_nascita,
        $is_creatore  // true o false (1 o 0)
    ]);


    echo json_encode(['success' => true, 'message' => 'Registrazione completata con successo!']);
    ob_end_flush();
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Errore DB: ' . $e->getMessage()]);
    ob_end_flush();
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore generico: ' . $e->getMessage()]);
    ob_end_flush();
    exit;
}
