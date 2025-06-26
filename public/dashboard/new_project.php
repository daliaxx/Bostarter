<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_email']) || $_SESSION['is_creator'] != 1) {
    die("Accesso non autorizzato.");
}

$db = Database::getInstance();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);
    $data_limite = $_POST['data_limite'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $email = $_SESSION['user_email'];

    // ✅ REWARD - Recupera le reward dal form
    $rewards = [];
    if (isset($_POST['reward_codes']) && isset($_POST['reward_descriptions'])) {
        $rewardCodes = $_POST['reward_codes'];
        $rewardDescriptions = $_POST['reward_descriptions'];

        for ($i = 0; $i < count($rewardCodes); $i++) {
            $code = trim($rewardCodes[$i]);
            $desc = trim($rewardDescriptions[$i]);

            if (!empty($code) && !empty($desc)) {
                $rewards[] = [
                    'codice' => $code,
                    'descrizione' => $desc
                ];
            }
        }
    }

    // Validazioni
    if (empty($nome) || empty($descrizione) || $budget <= 0 || empty($data_limite) || empty($tipo)) {
        $error_message = "Tutti i campi sono obbligatori e devono essere validi.";
    } elseif (strtotime($data_limite) <= strtotime(date('Y-m-d'))) {
        $error_message = "La data limite deve essere futura.";
    } elseif (empty($rewards)) {
        $error_message = "Devi aggiungere almeno una reward per il tuo progetto.";
    } else {
        // Verifica che i codici reward siano univoci
        $codiciUnivoci = array_unique(array_column($rewards, 'codice'));
        if (count($codiciUnivoci) !== count($rewards)) {
            $error_message = "I codici delle reward devono essere univoci.";
        } else {
            // Verifica che il nome progetto non esista già
            $progettoEsistente = $db->fetchOne("SELECT Nome FROM PROGETTO WHERE Nome = ?", [$nome]);
            if ($progettoEsistente) {
                $error_message = "Esiste già un progetto con questo nome.";
            }
        }
    }

    if (empty($error_message)) {
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
            $db->beginTransaction();

            $oggi = date('Y-m-d');
            $stato = 'aperto';

            // 1. Inserisci progetto
            $db->callStoredProcedure('InserisciProgetto', [
                $nome,
                $descrizione,
                $oggi,
                $budget,
                $data_limite,
                $stato,
                $email
            ]);

            // 2. Inserisci foto se caricata
            if ($immaginePath) {
                $db->callStoredProcedure('InserisciFoto', [
                    $immaginePath,
                    $nome
                ]);
            }

            // 3. ✅ INSERISCI LE REWARD OBBLIGATORIE
            foreach ($rewards as $reward) {
                $db->execute("
                    INSERT INTO REWARD (Codice, Descrizione, Foto, Nome_Progetto) 
                    VALUES (?, ?, 'default_reward.jpg', ?)
                ", [$reward['codice'], $reward['descrizione'], $nome]);
            }

            $db->commit();

            $success_message = "Progetto '{$nome}' creato con successo con " . count($rewards) . " reward!";

            // Redirect dopo 2 secondi
            header("refresh:2;url=creator_dashboard.php");

        } catch (Exception $e) {
            $db->rollback();
            $error_message = "Errore durante l'inserimento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Nuovo Progetto - BOSTARTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .form-body {
            padding: 2rem;
        }
        .reward-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .reward-item:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .btn-add-reward {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-add-reward:hover {
            transform: translateY(-2px);
            color: white;
        }
        .btn-remove-reward {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: white;
        }
        .reward-counter {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../projects.php">
            <i class="fas fa-lightbulb me-2"></i>BOSTARTER
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="../projects.php">
                <i class="fas fa-project-diagram me-1"></i>Progetti
            </a>
            <a class="nav-link" href="creator_dashboard.php">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-plus-circle me-2"></i>Crea un Nuovo Progetto</h2>
            <p class="lead mb-0">Lancia la tua idea innovativa con reward accattivanti</p>
        </div>

        <div class="form-body">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <br><small>Reindirizzamento alla dashboard...</small>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="projectForm">
                <!-- Informazioni base progetto -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nome" class="form-label">
                            <i class="fas fa-file-signature me-1"></i>Nome progetto
                        </label>
                        <input type="text" class="form-control" id="nome" name="nome" required
                               value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="tipo" class="form-label">
                            <i class="fas fa-lightbulb me-1"></i>Tipo di progetto
                        </label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Seleziona tipo...</option>
                            <option value="Hardware" <?= (($_POST['tipo'] ?? '') === 'Hardware') ? 'selected' : '' ?>>Hardware</option>
                            <option value="Software" <?= (($_POST['tipo'] ?? '') === 'Software') ? 'selected' : '' ?>>Software</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="descrizione" class="form-label">
                        <i class="fas fa-align-left me-1"></i>Descrizione
                    </label>
                    <textarea class="form-control" id="descrizione" name="descrizione" rows="4" required><?= htmlspecialchars($_POST['descrizione'] ?? '') ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="budget" class="form-label">
                            <i class="fas fa-euro-sign me-1"></i>Budget richiesto (€)
                        </label>
                        <input type="number" class="form-control" id="budget" name="budget" min="1" step="0.01" required
                               value="<?= htmlspecialchars($_POST['budget'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_limite" class="form-label">
                            <i class="fas fa-calendar-alt me-1"></i>Data limite
                        </label>
                        <input type="date" class="form-control" id="data_limite" name="data_limite" required
                               value="<?= htmlspecialchars($_POST['data_limite'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="immagine" class="form-label">
                        <i class="fas fa-image me-1"></i>Immagine progetto
                    </label>
                    <input type="file" class="form-control" id="immagine" name="immagine" accept="image/*">
                    <small class="form-text text-muted">Carica un'immagine rappresentativa del tuo progetto.</small>
                </div>

                <!-- ✅ SEZIONE REWARD OBBLIGATORIE -->
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-gift me-2"></i>Reward del Progetto
                            </h5>
                            <span class="reward-counter">
                                <span id="rewardCount">0</span> reward
                            </span>
                        </div>
                        <small>Aggiungi le ricompense che i sostenitori riceveranno (minimo 1 richiesta)</small>
                    </div>
                    <div class="card-body">
                        <div id="rewardsContainer">
                            <!-- Le reward verranno aggiunte qui dinamicamente -->
                        </div>

                        <button type="button" class="btn btn-add-reward" onclick="addReward()">
                            <i class="fas fa-plus me-2"></i>Aggiungi Reward
                        </button>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Esempi di reward:</strong> Accesso beta, T-shirt personalizzata, Menzione nei credits,
                                Invito a evento esclusivo, Prodotto finale con sconto, ecc.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket me-2"></i>Crea Progetto con Reward
                    </button>
                    <a href="creator_dashboard.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    let rewardCount = 0;

    // ✅ Funzione per aggiungere una reward
    function addReward() {
        rewardCount++;

        const rewardHtml = `
        <div class="reward-item" id="reward_${rewardCount}">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Codice Reward</label>
                    <input type="text"
                           name="reward_codes[]"
                           class="form-control"
                           placeholder="es: EARLY_BIRD"
                           required>
                    <small class="text-muted">Identificativo univoco</small>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Descrizione</label>
                    <input type="text"
                           name="reward_descriptions[]"
                           class="form-control"
                           placeholder="es: Accesso anticipato al prodotto"
                           required>
                    <small class="text-muted">Cosa riceverà il sostenitore</small>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button"
                            class="btn btn-remove-reward btn-sm"
                            onclick="removeReward(${rewardCount})"
                            title="Rimuovi reward">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

        document.getElementById('rewardsContainer').insertAdjacentHTML('beforeend', rewardHtml);
        updateRewardCount();
    }

    // ✅ Funzione per rimuovere una reward
    function removeReward(id) {
        const rewardElement = document.getElementById(`reward_${id}`);
        if (rewardElement) {
            rewardElement.remove();
            updateRewardCount();
        }
    }

    // ✅ Aggiorna il contatore delle reward
    function updateRewardCount() {
        const currentRewards = document.querySelectorAll('.reward-item').length;
        document.getElementById('rewardCount').textContent = currentRewards;
    }

    // ✅ Validazione form prima dell'invio
    document.getElementById('projectForm').addEventListener('submit', function(e) {
        const rewards = document.querySelectorAll('.reward-item').length;

        if (rewards === 0) {
            e.preventDefault();
            alert('⚠️ Devi aggiungere almeno una reward per il tuo progetto!');
            return false;
        }

        // Verifica che tutti i campi reward siano compilati
        const rewardCodes = document.querySelectorAll('input[name="reward_codes[]"]');
        const rewardDescriptions = document.querySelectorAll('input[name="reward_descriptions[]"]');

        for (let i = 0; i < rewardCodes.length; i++) {
            if (!rewardCodes[i].value.trim() || !rewardDescriptions[i].value.trim()) {
                e.preventDefault();
                alert('⚠️ Tutti i campi delle reward devono essere compilati!');
                return false;
            }
        }

        return true;
    });

    // ✅ Aggiungi automaticamente una reward all'avvio
    document.addEventListener('DOMContentLoaded', function() {
        addReward(); // Prima reward di default
    });
</script>
</body>
</html>