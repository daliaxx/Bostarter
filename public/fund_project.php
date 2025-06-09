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

// Verifica parametro progetto
if (!isset($_GET['name'])) {
    die("<h3>Errore: progetto non specificato.</h3>");
}

$nomeProgetto = $_GET['name'];
$emailUtente = SessionManager::getUserEmail();
$db = Database::getInstance();

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importo = floatval($_POST['importo'] ?? 0);
    $reward = $_POST['reward'] ?? null;

    if ($importo <= 0) {
        $errore = "Inserisci un importo valido.";
    } else {
        try {
            $db->execute("INSERT INTO FINANZIAMENTO (Data, Importo, Email_Utente, Codice_Reward, Nome_Progetto)
                          VALUES (NOW(), ?, ?, ?, ?)", [$importo, $emailUtente, $reward, $nomeProgetto]);
            header("Location: project_detail.php?name=" . urlencode($nomeProgetto));
            exit;
        } catch (Exception $e) {
            $errore = "Errore nel salvataggio del finanziamento.";
        }
    }
}

// Recupera i reward disponibili (opzionale)
$rewards = $db->fetchAll("SELECT Codice, Descrizione FROM REWARD WHERE Nome_Progetto = ?", [$nomeProgetto]);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Finanzia il progetto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Finanzia: <?= htmlspecialchars($nomeProgetto) ?></h2>

    <?php if (!empty($errore)): ?>
        <div class="alert alert-danger"><?= $errore ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="importo" class="form-label">Importo</label>
            <input type="number" name="importo" id="importo" step="0.01" min="1" class="form-control" required>
        </div>

        <?php if (!empty($rewards)): ?>
            <div class="mb-3">
                <label for="reward" class="form-label">Scegli un reward (opzionale)</label>
                <select name="reward" id="reward" class="form-select">
                    <option value="">Nessun reward</option>
                    <?php foreach ($rewards as $r): ?>
                        <option value="<?= $r['Codice'] ?>"><?= htmlspecialchars($r['Descrizione']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Conferma Finanziamento</button>
        <a href="project_detail.php?name=<?= urlencode($nomeProgetto) ?>" class="btn btn-secondary ms-2">Annulla</a>
    </form>
</div>
</body>
</html>
