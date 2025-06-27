<?php
/**
 * BOSTARTER - Gestione Finanziamento Progetto - REWARD OBBLIGATORIE
 * File: public/fund_project.php
 */

require_once '../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

SessionManager::start();

// Verifica login
if (!SessionManager::isLoggedIn()) {
    header("Location: ../index.html");
    exit;
}

// Recupera il nome del progetto
$nomeProgetto = $_POST['nome_progetto'] ?? $_GET['name'] ?? null;

if (empty($nomeProgetto)) {
    die("<h3>Errore: nome del progetto non specificato.</h3>");
}

$emailUtente = SessionManager::getUserEmail();
$db = Database::getInstance();
$errore = null;

// Verifica che il progetto esista
$progetto = $db->fetchOne("SELECT Nome, Stato FROM PROGETTO WHERE Nome = ?", [$nomeProgetto]);
if (!$progetto) {
    die("<h3>Errore: Progetto non trovato.</h3>");
}

// ✅ VERIFICA CHE IL PROGETTO ABBIA REWARD (OBBLIGATORIE)
$rewards = $db->fetchAll("SELECT Codice, Descrizione FROM REWARD WHERE Nome_Progetto = ? ORDER BY Codice", [$nomeProgetto]);

if (empty($rewards)) {
    die("
        <div class='container mt-5'>
            <div class='alert alert-warning'>
                <h4><i class='fas fa-exclamation-triangle me-2'></i>Progetto Non Finanziabile</h4>
                <p>Questo progetto non ha ancora definito le reward per i sostenitori.</p>
                <p>Il creatore deve aggiungere almeno una reward prima che il progetto possa ricevere finanziamenti.</p>
                <a href='projects/project_detail.php?name=" . urlencode($nomeProgetto) . "' class='btn btn-primary'>
                    <i class='fas fa-arrow-left me-1'></i>Torna al Progetto
                </a>
            </div>
        </div>
    ");
}

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importo = floatval($_POST['importo'] ?? 0);
    $codiceReward = trim($_POST['codice_reward'] ?? '');

    // Validazioni
    if ($importo <= 0) {
        $errore = "Inserisci un importo valido (maggiore di zero).";
    }

    // ✅ REWARD OBBLIGATORIA - Non può essere vuota
    if (empty($codiceReward)) {
        $errore = "Devi selezionare una reward per completare il finanziamento.";
    } else {
        // Verifica che la reward selezionata esista per questo progetto
        $rewardEsiste = $db->fetchOne("SELECT Codice FROM REWARD WHERE Codice = ? AND Nome_Progetto = ?", [$codiceReward, $nomeProgetto]);
        if (!$rewardEsiste) {
            $errore = "La reward selezionata non è valida per questo progetto.";
        }
    }

    if (empty($errore)) {
        try {
            // ✅ Chiama la stored procedure con reward obbligatoria
            $stmt = $db->callStoredProcedure('FinanziaProgetto', [
                $emailUtente,
                $nomeProgetto,
                $importo,
                $codiceReward  // Sempre presente, mai NULL
            ]);

            // Log per debug
            error_log("✅ Finanziamento inserito - Utente: $emailUtente, Progetto: $nomeProgetto, Importo: $importo, Reward: $codiceReward");

            header("Location: ../public/projects/project_detail.php?name=" . urlencode($nomeProgetto) . "&success=finanziato");
            exit;

        } catch (Exception $e) {
            error_log("❌ Errore finanziamento: " . $e->getMessage());
            $errore = "Errore durante il finanziamento: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Finanzia il progetto - BOSTARTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .funding-container {
            max-width: 600px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .funding-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .reward-option {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .reward-option:hover {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
            transform: translateY(-2px);
        }
        .reward-option.selected {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        }
        .quick-amount {
            transition: all 0.3s ease;
        }
        .quick-amount:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="projects.php">
            <i class="fas fa-lightbulb me-2"></i>BOSTARTER
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="projects/project_detail.php?name=<?= urlencode($nomeProgetto) ?>">
                <i class="fas fa-arrow-left me-1"></i>Torna al Progetto
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="funding-container">
        <div class="funding-header">
            <h3 class="mb-0">
                <i class="fas fa-hand-holding-usd me-2"></i>
                Finanzia: <?= htmlspecialchars($nomeProgetto) ?>
            </h3>
            <p class="lead mb-0 mt-2">Scegli la tua reward e sostieni l'innovazione</p>
        </div>

        <div class="p-4">
            <?php if (!empty($errore)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($errore) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="fund_project.php" id="fundingForm">
                <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($nomeProgetto) ?>">

                <!-- ✅ STEP 1: Selezione Reward Obbligatoria -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-gift me-2"></i>
                        <strong>Scegli la tua reward</strong>
                        <span class="badge bg-danger">Obbligatorio</span>
                    </label>

                    <div id="rewardsContainer">
                        <?php foreach ($rewards as $index => $reward): ?>
                            <div class="reward-option" onclick="selectReward('<?= htmlspecialchars($reward['Codice']) ?>')">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="codice_reward"
                                           id="reward_<?= $index ?>"
                                           value="<?= htmlspecialchars($reward['Codice']) ?>"
                                        <?= (isset($_POST['codice_reward']) && $_POST['codice_reward'] === $reward['Codice']) ? 'checked' : '' ?>
                                           required>
                                    <label class="form-check-label w-100" for="reward_<?= $index ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($reward['Codice']) ?></strong>
                                                <p class="mb-0 text-muted"><?= htmlspecialchars($reward['Descrizione']) ?></p>
                                            </div>
                                            <i class="fas fa-gift fa-2x text-success"></i>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Seleziona una reward per ricevere un ringraziamento speciale per il tuo sostegno.
                    </small>
                </div>

                <!-- ✅ STEP 2: Importo -->
                <div class="mb-4">
                    <label for="importo" class="form-label">
                        <i class="fas fa-euro-sign me-2"></i>
                        <strong>Importo da finanziare (€)</strong>
                    </label>
                    <input type="number" name="importo" id="importo"
                           step="0.01" min="1" class="form-control form-control-lg"
                           required value="<?= htmlspecialchars($_POST['importo'] ?? '') ?>"
                           placeholder="Es: 25.00">

                    <!-- Pulsanti importo rapido -->
                    <div class="mt-3 d-flex justify-content-around">
                        <button type="button" class="btn btn-outline-success quick-amount" onclick="setAmount(10)">€10</button>
                        <button type="button" class="btn btn-outline-success quick-amount" onclick="setAmount(25)">€25</button>
                        <button type="button" class="btn btn-outline-success quick-amount" onclick="setAmount(50)">€50</button>
                        <button type="button" class="btn btn-outline-success quick-amount" onclick="setAmount(100)">€100</button>
                        <button type="button" class="btn btn-outline-success quick-amount" onclick="setAmount(250)">€250</button>
                    </div>
                </div>

                <!-- ✅ STEP 3: Riepilogo -->
                <div class="card bg-light mb-4" id="summaryCard" style="display: none;">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-clipboard-list me-2"></i>Riepilogo Finanziamento
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Progetto:</strong> <?= htmlspecialchars($nomeProgetto) ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Reward:</strong> <span id="selectedRewardText">Nessuna selezionata</span>
                            </div>
                            <div class="col-12 mt-2">
                                <strong>Importo:</strong> <span id="selectedAmountText">€0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                        <i class="fas fa-paper-plane me-2"></i>Conferma Finanziamento
                    </button>
                    <a href="projects/project_detail.php?name=<?= urlencode($nomeProgetto) ?>"
                       class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Annulla
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer informativo -->
        <div class="bg-light p-3 text-center">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>
                Il tuo finanziamento include automaticamente la reward selezionata.
                <br>
                <strong>Grazie per sostenere l'innovazione!</strong>
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // ✅ Funzione per selezionare reward
    function selectReward(rewardCode) {
        // Rimuovi selezione precedente
        document.querySelectorAll('.reward-option').forEach(option => {
            option.classList.remove('selected');
        });

        // Seleziona la nuova reward
        const selectedOption = event.currentTarget;
        selectedOption.classList.add('selected');

        // Aggiorna il radio button
        const radioButton = selectedOption.querySelector('input[type="radio"]');
        radioButton.checked = true;

        // Aggiorna il testo del riepilogo
        const rewardDescription = selectedOption.querySelector('.text-muted').textContent;
        document.getElementById('selectedRewardText').textContent = `${rewardCode} - ${rewardDescription}`;

        // Verifica se possiamo abilitare il submit
        checkFormCompletion();
    }

    // ✅ Funzione per impostare importo rapido
    function setAmount(amount) {
        document.getElementById('importo').value = amount;
        document.getElementById('selectedAmountText').textContent = `€${amount}.00`;

        // Rimuovi focus da altri pulsanti
        document.querySelectorAll('.quick-amount').forEach(btn => {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-success');
        });

        // Evidenzia il pulsante selezionato
        event.target.classList.remove('btn-outline-success');
        event.target.classList.add('btn-success');

        checkFormCompletion();
    }

    // ✅ Verifica se il form è completo
    function checkFormCompletion() {
        const selectedReward = document.querySelector('input[name="codice_reward"]:checked');
        const importo = parseFloat(document.getElementById('importo').value);

        const isComplete = selectedReward && importo > 0;

        // Abilita/disabilita submit button
        document.getElementById('submitBtn').disabled = !isComplete;

        // Mostra/nascondi riepilogo
        document.getElementById('summaryCard').style.display = isComplete ? 'block' : 'none';
    }

    // ✅ Listener per aggiornamento importo manuale
    document.getElementById('importo').addEventListener('input', function() {
        const amount = parseFloat(this.value) || 0;
        document.getElementById('selectedAmountText').textContent = `€${amount.toFixed(2)}`;

        // Rimuovi evidenziazione dai pulsanti rapidi
        document.querySelectorAll('.quick-amount').forEach(btn => {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-success');
        });

        checkFormCompletion();
    });

    // ✅ Listener per selezione reward via radio button
    document.querySelectorAll('input[name="codice_reward"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const rewardCode = this.value;
                const rewardDescription = this.closest('.reward-option').querySelector('.text-muted').textContent;
                document.getElementById('selectedRewardText').textContent = `${rewardCode} - ${rewardDescription}`;

                // Aggiorna visual selection
                document.querySelectorAll('.reward-option').forEach(option => {
                    option.classList.remove('selected');
                });
                this.closest('.reward-option').classList.add('selected');

                checkFormCompletion();
            }
        });
    });

    // ✅ Validazione form prima dell'invio
    document.getElementById('fundingForm').addEventListener('submit', function(e) {
        const selectedReward = document.querySelector('input[name="codice_reward"]:checked');
        const importo = parseFloat(document.getElementById('importo').value);

        if (!selectedReward) {
            e.preventDefault();
            alert('⚠️ Devi selezionare una reward per completare il finanziamento!');
            return false;
        }

        if (importo <= 0) {
            e.preventDefault();
            alert('⚠️ Inserisci un importo valido maggiore di zero!');
            return false;
        }

        // Mostra loading sul pulsante
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione...';

        return true;
    });

    // ✅ Controlla stato iniziale al caricamento
    document.addEventListener('DOMContentLoaded', function() {
        checkFormCompletion();

        // Se c'è una reward pre-selezionata (in caso di errore), aggiorna la UI
        const preSelectedReward = document.querySelector('input[name="codice_reward"]:checked');
        if (preSelectedReward) {
            preSelectedReward.closest('.reward-option').classList.add('selected');
            const rewardCode = preSelectedReward.value;
            const rewardDescription = preSelectedReward.closest('.reward-option').querySelector('.text-muted').textContent;
            document.getElementById('selectedRewardText').textContent = `${rewardCode} - ${rewardDescription}`;
        }
    });
</script>
</body>
</html>