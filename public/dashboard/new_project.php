<?php
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_email']) || $_SESSION['is_creator'] != 1) {
    die("Accesso non autorizzato.");
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descrizione = $_POST['descrizione'];
    $budget = $_POST['budget'];
    $data_limite = $_POST['data_limite'];
    $tipo = $_POST['tipo'];
    $email = $_SESSION['user_email'];

    // Carica immagine
    $immaginePath = null;
    if (isset($_FILES['immagine']) && $_FILES['immagine']['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . "_" . basename($_FILES['immagine']['name']);
        $tmpName = $_FILES['immagine']['tmp_name'];
        $uploadDir = __DIR__ . '/../../img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($tmpName, $destPath)) {
            $immaginePath = 'img/' . $fileName;
        }
    }

    try {
        // 1. Crea progetto
        $db->execute("
            INSERT INTO PROGETTO (Nome, Descrizione, Data_Inserimento, Budget, Data_Limite, Stato, Email_Creatore, Tipo)
            VALUES (?, ?, CURDATE(), ?, ?, 'aperto', ?, ?)
        ", [$nome, $descrizione, $budget, $data_limite, $email, $tipo]);

        // 2. Inserisci immagine se presente
        if ($immaginePath !== null) {
            $db->execute("
                INSERT INTO FOTO (Nome_Progetto, Percorso)
                VALUES (?, ?)
            ", [$nome, $immaginePath]);
        }

        // 3. Redirect
        header("Location: creator_dashboard.php");
        exit;

    } catch (Exception $e) {
        echo "<p>❌ Errore: " . $e->getMessage() . "</p>";
    }

}
?>

<h2>Nuovo Progetto</h2>
<form method="POST" enctype="multipart/form-data">
    <label>Nome progetto:</label><br>
    <input type="text" name="nome" required><br><br>

    <label>Descrizione:</label><br>
    <textarea name="descrizione" required></textarea><br><br>

    <label>Budget richiesto (€):</label><br>
    <input type="number" name="budget" min="1" required><br><br>

    <label>Data limite:</label><br>
    <input type="date" name="data_limite" required><br><br>

    <label>Tipo:</label><br>
    <select name="tipo" required>
        <option value="hardware">Hardware</option>
        <option value="software">Software</option>
    </select><br><br>

     <label>Immagine progetto:</label><br>
        <input type="file" name="immagine" accept="imgages/*"><br><br>

    <button type="submit">Crea Progetto</button>
</form>
