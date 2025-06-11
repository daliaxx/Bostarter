<?php
/**
 * BOSTARTER - Gestione Finanziamento Progetto
 * File: public/fund_project.php
 *
 * Questo script gestisce l'inserimento di un finanziamento per un progetto,
 * inclusa l'associazione opzionale di una reward (senza importo minimo).
 */

require_once '../config/database.php'; // Assicurati che il percorso sia corretto

ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

// Verifica login
if (!SessionManager::isLoggedIn()) {
    header("Location: ../index.html");
    exit;
}

// Recupera il nome del progetto: prima da POST (per la sottomissione del form), poi da GET (per il caricamento iniziale della pagina).
$nomeProgetto = $_POST['nome_progetto'] ?? $_GET['name'] ?? null;

// Se, dopo entrambi i tentativi, il nome del progetto è ancora vuoto, mostra l'errore
if (empty($nomeProgetto)) {
    die("<h3>Errore: nome del progetto non specificato.</h3>");
}

$emailUtente = SessionManager::getUserEmail();
$db = Database::getInstance();
$errore = null; // Inizializza la variabile errore

// Questo blocco è necessario solo se vuoi ri-mostrare il form in caso di errore,
// con le reward pre-caricate. Dato che il form è nel modale, potresti non averne bisogno
// di visualizzare fund_project.php direttamente.
// Se il tuo form di finanziamento *viene* reindirizzato qui su errore POST,
// allora queste linee servono a ri-popolare le reward.
$rewards = $db->fetchAll("SELECT Codice, Descrizione FROM REWARD WHERE Nome_Progetto = ?", [$nomeProgetto]);
// Non abbiamo più bisogno di $rewardsMap perché non c'è Importo_Minimo

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importo = floatval($_POST['importo'] ?? 0);
    $codiceReward = $_POST['codice_reward'] ?? null; // Nome dell'input dal form: codice_reward

    if ($importo <= 0) {
        $errore = "Inserisci un importo valido (maggiore di zero).";
    }

    // Qui non c'è più la validazione dell'Importo_Minimo della reward
    // perché hai specificato che non è un requisito.
    // Verifichiamo solo se la reward selezionata esiste davvero per quel progetto (sicurezza).
    if (!empty($codiceReward)) {
        $rewardEsiste = $db->fetchOne("SELECT Codice FROM REWARD WHERE Codice = ? AND Nome_Progetto = ?", [$codiceReward, $nomeProgetto]);
        if (!$rewardEsiste) {
            $errore = "Ricompensa non valida per questo progetto.";
            $codiceReward = null; // Se non valida, resetta per non salvare un codice errato
        }
    }


    if (empty($errore)) {
        try {
            $stmt = $db->callStoredProcedure('FinanziaProgetto', [
                $emailUtente, 
                $nomeProgetto, 
                $importo, 
                $codiceReward
            ]);

            header("Location: project_detail.php?name=" . urlencode($nomeProgetto) . "&success=finanziato");
            exit;

        } catch (Exception $e) {
            $errore = "Errore durante il finanziamento: " . $e->getMessage();
        }
    }
}

// Questo HTML viene mostrato solo se il file fund_project.php viene caricato direttamente
// o se c'è un errore POST che non fa il redirect.
// Nel tuo setup con il modale, l'utente non vedrà questa pagina a meno di un errore grave.
$progetto = $db->fetchOne("SELECT Nome FROM PROGETTO WHERE Nome = ?", [$nomeProgetto]);
if (!$progetto) {
    die("<h3>Errore: Progetto non trovato.</h3>");
}
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

    <form method="POST" action="fund_project.php">
        <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($nomeProgetto) ?>">
        <div class="mb-3">
            <label for="importo" class="form-label">Importo</label>
            <input type="number" name="importo" id="importo" step="0.01" min="1" class="form-control" required value="<?= htmlspecialchars($_POST['importo'] ?? '') ?>">
        </div>

        <?php if (!empty($rewards)): ?>
            <div class="mb-3">
                <label for="codice_reward" class="form-label">Scegli una ricompensa (opzionale)</label>
                <select name="codice_reward" id="codice_reward" class="form-select">
                    <option value="">Nessuna ricompensa</option>
                    <?php foreach ($rewards as $r): ?>
                        <option value="<?= htmlspecialchars($r['Codice']) ?>"
                            <?= (isset($_POST['codice_reward']) && $_POST['codice_reward'] === $r['Codice']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['Descrizione']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Invia Finanziamento</button>
    </form>
    <p class="mt-3"><a href="project_detail.php?name=<?= urlencode($nomeProgetto) ?>">Torna al progetto</a></p>
</div>
</body>
</html>