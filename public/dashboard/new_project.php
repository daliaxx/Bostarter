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

    // Recupera reward o componenti in base al tipo
    $rewards = [];
    $componenti = [];

    if ($tipo === 'Software') {
        // Per progetti software, recupera le reward
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
    } elseif ($tipo === 'Hardware') {
        // Per progetti hardware, recupera i componenti
        if (isset($_POST['component_names']) && isset($_POST['component_descriptions'])
            && isset($_POST['component_prices']) && isset($_POST['component_quantities'])) {

            $componentNames = $_POST['component_names'];
            $componentDescriptions = $_POST['component_descriptions'];
            $componentPrices = $_POST['component_prices'];
            $componentQuantities = $_POST['component_quantities'];

            for ($i = 0; $i < count($componentNames); $i++) {
                $name = trim($componentNames[$i]);
                $desc = trim($componentDescriptions[$i]);
                $price = floatval($componentPrices[$i]);
                $quantity = intval($componentQuantities[$i]);

                if (!empty($name) && !empty($desc) && $price > 0 && $quantity > 0) {
                    $componenti[] = [
                        'nome' => $name,
                        'descrizione' => $desc,
                        'prezzo' => $price,
                        'quantita' => $quantity
                    ];
                }
            }
        }
    }

    // Validazioni
    if (empty($nome) || empty($descrizione) || $budget <= 0 || empty($data_limite) || empty($tipo)) {
        $error_message = "Tutti i campi sono obbligatori e devono essere validi.";
    } elseif (strtotime($data_limite) <= strtotime(date('Y-m-d'))) {
        $error_message = "La data limite deve essere futura.";
    } elseif ($tipo === 'Software' && empty($rewards)) {
        $error_message = "Devi aggiungere almeno una reward per il progetto Software.";
    } elseif ($tipo === 'Hardware' && empty($componenti)) {
        $error_message = "Devi aggiungere almeno un componente per il progetto Hardware.";
    } else {
        // Verifica univocità dei codici/nomi
        if ($tipo === 'Software') {
            $codiciUnivoci = array_unique(array_column($rewards, 'codice'));
            if (count($codiciUnivoci) !== count($rewards)) {
                $error_message = "I codici delle reward devono essere univoci.";
            }
        } elseif ($tipo === 'Hardware') {
            $nomiUnivoci = array_unique(array_column($componenti, 'nome'));
            if (count($nomiUnivoci) !== count($componenti)) {
                $error_message = "I nomi dei componenti devono essere univoci.";
            }
        }

        // Verifica che il nome progetto non esista già
        if (empty($error_message)) {
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
                $tipo,
                $email
            ]);

            // 2. Inserisci foto se caricata
            if ($immaginePath) {
                $db->callStoredProcedure('InserisciFoto', [
                    $immaginePath,
                    $nome
                ]);
            }

            // 3. Inserisci reward (solo per Software) o componenti (solo per Hardware)
            if ($tipo === 'Software') {
                foreach ($rewards as $reward) {
                    $db->execute("
                        INSERT INTO REWARD (Codice, Descrizione, Foto, Nome_Progetto) 
                        VALUES (?, ?, 'default_reward.jpg', ?)
                    ", [$reward['codice'], $reward['descrizione'], $nome]);
                }
                $countItems = count($rewards);
                $itemType = "reward";
            } else {
                foreach ($componenti as $componente) {
                    $db->execute("
                        INSERT INTO COMPONENTE (Nome, Descrizione, Prezzo, Quantita, Nome_Progetto) 
                        VALUES (?, ?, ?, ?, ?)
                    ", [
                        $componente['nome'],
                        $componente['descrizione'],
                        $componente['prezzo'],
                        $componente['quantita'],
                        $nome
                    ]);
                }
                $countItems = count($componenti);
                $itemType = "componenti";
            }

            $db->commit();

            $success_message = "Progetto '{$nome}' ({$tipo}) creato con successo con {$countItems} {$itemType}!";

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
        .reward-item, .component-item {
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
        .component-item:hover {
            border-color: #28a745;
            background: #e8f5e8;
        }
        .btn-add-reward {
            background: linear-gradient(45deg, #007bff, #6f42c1);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-add-component {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-add-reward:hover, .btn-add-component:hover {
            transform: translateY(-2px);
            color: white;
        }
        .btn-remove {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: white;
        }
        .counter {
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
            <p class="lead mb-0">Lancia la tua idea innovativa</p>
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

                <!-- SEZIONE REWARD (solo per Software) -->
                <div class="card border-primary mb-4" id="rewardsSection" style="display: none;">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-gift me-2"></i>Reward del Progetto
                            </h5>
                            <span class="counter">
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

                <!-- SEZIONE COMPONENTI (solo per Hardware) -->
                <div class="card border-success mb-4" id="componentsSection" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-microchip me-2"></i>Componenti Hardware Necessari
                            </h5>
                            <span class="counter">
                                <span id="componentCount">0</span> componenti
                            </span>
                        </div>
                        <small>Elenca i componenti fisici necessari per realizzare il progetto (minimo 1 richiesto)</small>
                    </div>
                    <div class="card-body">
                        <div id="componentsContainer">
                            <!-- I componenti verranno aggiunti qui dinamicamente -->
                        </div>

                        <button type="button" class="btn btn-add-component" onclick="addComponent()">
                            <i class="fas fa-plus me-2"></i>Aggiungi Componente
                        </button>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Esempi di componenti:</strong> Sensori, motori, circuiti, batterie, case, connettori,
                                display, microcontrollori, antenne, alimentatori, ecc.
                            </small>
                        </div>

                        <!-- Riepilogo costi -->
                        <div id="componentsSummary" class="mt-4 p-3 bg-light rounded" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Totale Componenti:</strong> <span id="totalComponents">0</span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Costo Stimato:</strong> <span id="totalCost">€0.00</span>
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-calculator me-1"></i>
                                Costo indicativo dei materiali necessari
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket me-2"></i>Crea Progetto
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
    let componentCount = 0;

    // Mostra/nascondi sezioni in base al tipo progetto
    document.getElementById('tipo').addEventListener('change', function() {
        const tipo = this.value;
        const rewardsSection = document.getElementById('rewardsSection');
        const componentsSection = document.getElementById('componentsSection');

        if (tipo === 'Software') {
            rewardsSection.style.display = 'block';
            componentsSection.style.display = 'none';
            // Aggiungi automaticamente una reward se non ce ne sono
            if (rewardCount === 0) {
                addReward();
            }
        } else if (tipo === 'Hardware') {
            rewardsSection.style.display = 'none';
            componentsSection.style.display = 'block';
            // Aggiungi automaticamente un componente se non ce ne sono
            if (componentCount === 0) {
                addComponent();
            }
        } else {
            rewardsSection.style.display = 'none';
            componentsSection.style.display = 'none';
        }
    });

    // Funzione per aggiungere una reward
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
                            class="btn btn-remove btn-sm"
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

    // Funzione per rimuovere una reward
    function removeReward(id) {
        const rewardElement = document.getElementById(`reward_${id}`);
        if (rewardElement) {
            rewardElement.remove();
            updateRewardCount();
        }
    }

    // Aggiorna il contatore delle reward
    function updateRewardCount() {
        const currentRewards = document.querySelectorAll('.reward-item').length;
        document.getElementById('rewardCount').textContent = currentRewards;
    }

    // Funzione per aggiungere componente
    function addComponent() {
        componentCount++;

        const componentHtml = `
        <div class="component-item" id="component_${componentCount}">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Nome Componente</label>
                    <input type="text"
                           name="component_names[]"
                           class="form-control"
                           placeholder="es: Sensore Temperatura"
                           required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Descrizione</label>
                    <input type="text"
                           name="component_descriptions[]"
                           class="form-control"
                           placeholder="es: Sensore digitale DS18B20"
                           required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Prezzo (€)</label>
                    <input type="number"
                           name="component_prices[]"
                           class="form-control component-price"
                           placeholder="25.00"
                           step="0.01"
                           min="0.01"
                           required
                           onchange="updateComponentsSummary()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantità</label>
                    <input type="number"
                           name="component_quantities[]"
                           class="form-control component-quantity"
                           placeholder="2"
                           min="1"
                           required
                           onchange="updateComponentsSummary()">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button"
                            class="btn btn-remove btn-sm"
                            onclick="removeComponent(${componentCount})"
                            title="Rimuovi componente">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

        document.getElementById('componentsContainer').insertAdjacentHTML('beforeend', componentHtml);
        updateComponentsCount();
        updateComponentsSummary();
    }

    // Funzione per rimuovere componente
    function removeComponent(id) {
        const componentElement = document.getElementById(`component_${id}`);
        if (componentElement) {
            componentElement.remove();
            updateComponentsCount();
            updateComponentsSummary();
        }
    }

    // Aggiorna contatore componenti
    function updateComponentsCount() {
        const currentComponents = document.querySelectorAll('.component-item').length;
        document.getElementById('componentCount').textContent = currentComponents;
    }

    // Aggiorna riepilogo costi
    function updateComponentsSummary() {
        const components = document.querySelectorAll('.component-item');
        const summaryDiv = document.getElementById('componentsSummary');

        if (components.length === 0) {
            summaryDiv.style.display = 'none';
            return;
        }

        let totalCost = 0;
        let totalComponents = 0;

        components.forEach(component => {
            const price = parseFloat(component.querySelector('.component-price').value) || 0;
            const quantity = parseInt(component.querySelector('.component-quantity').value) || 0;
            totalCost += price * quantity;
            totalComponents += quantity;
        });

        document.getElementById('totalComponents').textContent = totalComponents;
        document.getElementById('totalCost').textContent = `€${totalCost.toFixed(2)}`;

        summaryDiv.style.display = 'block';
    }

    // Validazione form prima dell'invio
    document.getElementById('projectForm').addEventListener('submit', function(e) {
        const tipo = document.getElementById('tipo').value;

        if (tipo === 'Software') {
            const rewards = document.querySelectorAll('.reward-item').length;

            if (rewards === 0) {
                e.preventDefault();
                alert('Devi aggiungere almeno una reward per il progetto Software!');
                return false;
            }

            // Verifica che tutti i campi reward siano compilati
            const rewardCodes = document.querySelectorAll('input[name="reward_codes[]"]');
            const rewardDescriptions = document.querySelectorAll('input[name="reward_descriptions[]"]');

            for (let i = 0; i < rewardCodes.length; i++) {
                if (!rewardCodes[i].value.trim() || !rewardDescriptions[i].value.trim()) {
                    e.preventDefault();
                    alert('Tutti i campi delle reward devono essere compilati!');
                    return false;
                }
            }
        } else if (tipo === 'Hardware') {
            const components = document.querySelectorAll('.component-item').length;

            if (components === 0) {
                e.preventDefault();
                alert('Devi aggiungere almeno un componente per il progetto Hardware!');
                return false;
            }

            // Verifica che tutti i campi componenti siano compilati
            const componentNames = document.querySelectorAll('input[name="component_names[]"]');
            const componentDescriptions = document.querySelectorAll('input[name="component_descriptions[]"]');
            const componentPrices = document.querySelectorAll('input[name="component_prices[]"]');
            const componentQuantities = document.querySelectorAll('input[name="component_quantities[]"]');

            for (let i = 0; i < componentNames.length; i++) {
                if (!componentNames[i].value.trim() ||
                    !componentDescriptions[i].value.trim() ||
                    !componentPrices[i].value ||
                    !componentQuantities[i].value) {
                    e.preventDefault();
                    alert('Tutti i campi dei componenti devono essere compilati!');
                    return false;
                }
            }
        }

        return true;
    });
</script>
</body>
</html>