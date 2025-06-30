<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../../config/database.php';
require_once '../../includes/navbar.php';

SessionManager::start();

if (!isset($_SESSION['user_email']) || $_SESSION['is_creator'] != 1) {
    die("Accesso non autorizzato.");
}

$db = Database::getInstance();

// Verifica se l'utente è loggato
$isLoggedIn = SessionManager::isLoggedIn();
$userEmail = SessionManager::getUserEmail();
$isCreator = SessionManager::isCreator();
$isAdmin = SessionManager::isAdmin();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$nome = trim($_POST['nome'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');
$budget = floatval($_POST['budget'] ?? 0);
$data_limite = $_POST['data_limite'] ?? '';
$tipo = trim($_POST['tipo'] ?? '');
$email = $_SESSION['user_email'];

// Recupera reward, componenti e profili
$rewards = [];
$componenti = [];
$profili = []; // NUOVO: Array per multipli profili

// Le reward sono sempre obbligatorie per entrambi i tipi
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

// NUOVO: Gestisci multipli profili per progetti software
if ($tipo === 'Software' && isset($_POST['profili']) && $_POST['profili'] === 'si') {

    // Verifica se abbiamo i dati dei profili
    if (isset($_POST['profile_names']) && isset($_POST['profile_skills'])) {
        $profileNames = $_POST['profile_names'];
        $profileSkills = $_POST['profile_skills'];

        // Debug per capire la struttura
        error_log("🔍 Profile Names: " . print_r($profileNames, true));
        error_log("🔍 Profile Skills: " . print_r($profileSkills, true));

        // Processa ogni profilo
        foreach ($profileNames as $index => $profileName) {
            $profileName = trim($profileName);

            if (!empty($profileName)) {
                // Trova le skill per questo profilo
                $profiloSkills = [];

                // Cerca nelle skill per trovare quelle di questo profilo
                foreach ($profileSkills as $profileId => $skillData) {
                    // Controlla se questo è il profilo corretto
                    if (isset($skillData['competenze']) && isset($skillData['livelli'])) {
                        $competenze = $skillData['competenze'];
                        $livelli = $skillData['livelli'];

                        // Processa le skill di questo profilo
                        for ($i = 0; $i < count($competenze); $i++) {
                            $competenza = trim($competenze[$i]);
                            $livello = intval($livelli[$i]);

                            if (!empty($competenza) && $livello >= 0 && $livello <= 5) {
                                $profiloSkills[$competenza] = $livello;
                            }
                        }
                        break; // Trovato il profilo, esci dal loop
                    }
                }

                // Aggiungi il profilo se ha almeno una skill
                if (!empty($profiloSkills)) {
                    $profili[] = [
                        'nome' => $profileName,
                        'competenze' => $profiloSkills
                    ];
                }
            }
        }
    }
}

// Gestisci componenti per progetti hardware (invariato)
if ($tipo === 'Hardware' && isset($_POST['component_names']) && isset($_POST['component_descriptions'])
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

// Validazioni (aggiornate)
$error_message = '';

// Validazioni base
if (empty($nome) || empty($descrizione) || $budget <= 0 || empty($data_limite) || empty($tipo)) {
    $error_message = "Tutti i campi sono obbligatori e devono essere validi.";
} elseif (strtotime($data_limite) <= strtotime(date('Y-m-d'))) {
    $error_message = "La data limite deve essere futura.";
} elseif (empty($rewards)) {
    $error_message = "Devi aggiungere almeno una reward per il progetto.";
} elseif ($tipo === 'Hardware' && empty($componenti)) {
    $error_message = "Devi aggiungere almeno un componente per il progetto Hardware.";
} elseif ($tipo === 'Software' && isset($_POST['profili']) && $_POST['profili'] === 'si' && empty($profili)) {
    $error_message = "Se hai selezionato 'Sì' per i profili, devi aggiungere almeno un profilo con le relative skill.";
} else {
    // Verifica univocità reward
    $codiciUnivoci = array_unique(array_column($rewards, 'codice'));
    if (count($codiciUnivoci) !== count($rewards)) {
        $error_message = "I codici delle reward devono essere univoci.";
    } elseif ($tipo === 'Hardware' && !empty($componenti)) {
        $nomiUnivoci = array_unique(array_column($componenti, 'nome'));
        if (count($nomiUnivoci) !== count($componenti)) {
            $error_message = "I nomi dei componenti devono essere univoci.";
        }
    } elseif ($tipo === 'Software' && !empty($profili)) {
        // NUOVO: Verifica univocità nomi profili
        $nomiProfiliUnivoci = array_unique(array_column($profili, 'nome'));
        if (count($nomiProfiliUnivoci) !== count($profili)) {
            $error_message = "I nomi dei profili devono essere univoci.";
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
    // Carica immagine se fornita
    $immaginePath = null;
    if (isset($_FILES['immagine']) && $_FILES['immagine']['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . "_" . basename($_FILES['immagine']['name']);
        $tmpName = $_FILES['immagine']['tmp_name'];
        $uploadDir = __DIR__ . '/../../img/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($tmpName, $destPath)) {
            $immaginePath = 'img/' . $fileName;
        }
    }

    try {
        $db->beginTransaction();

        $oggi = date('Y-m-d');
        $stato = 'aperto';

        // Validazione aggiuntiva per il tipo
        if (!in_array($tipo, ['Hardware', 'Software'])) {
            throw new Exception("Tipo progetto non valido: '$tipo'. Deve essere 'Hardware' o 'Software'");
        }

        // 1. Inserisci progetto
        $stmt = $db->callStoredProcedure('InserisciProgetto', [$nome, $descrizione, $oggi, $budget, $data_limite, $stato, $tipo, $email]);
        $stmt->closeCursor();

        // 2. Inserisci foto se caricata
        if ($immaginePath) {
            $stmt = $db->callStoredProcedure('InserisciFoto', [$immaginePath, $nome]);
            $stmt->closeCursor();
        }

        // 3. Inserisci reward per entrambi i tipi
        foreach ($rewards as $reward) {
            $db->execute("
                    INSERT INTO REWARD (Codice, Descrizione, Foto, Nome_Progetto) 
                    VALUES (?, ?, 'default_reward.jpg', ?)
                ", [$reward['codice'], $reward['descrizione'], $nome]);
        }

        // 4. Inserisci componenti per hardware o profili per software
        if ($tipo === 'Hardware') {
            foreach ($componenti as $componente) {
                $db->execute("
                        INSERT INTO COMPONENTE (Nome, Descrizione, Prezzo, Quantita, Nome_Progetto) 
                        VALUES (?, ?, ?, ?, ?)
                    ", [
                    $componente['nome'], $componente['descrizione'],
                    $componente['prezzo'], $componente['quantita'], $nome
                ]);
            }
            $countItems = count($componenti) + count($rewards);
            $itemType = "componenti e reward";
        } else {
            // Software: inserisci multipli profili
            if (!empty($profili)) {
                foreach ($profili as $profilo) {
                    // Inserisci il profilo
                    $stmt = $db->callStoredProcedure('InserisciProfiloRichiesto', [$profilo['nome'], $nome]);
                    $result = $stmt->fetch();
                    $stmt->closeCursor();
                    $id_profilo = $result['ID_Profilo'] ?? null;

                    if ($id_profilo) {
                        // Inserisci le skill di questo profilo
                        foreach ($profilo['competenze'] as $competenza => $livello) {
                            $competenza = trim($competenza);
                            $livello = intval($livello);

                            if (!empty($competenza) && $livello >= 0 && $livello <= 5) {
                                // Inserisci la skill generale se non esiste
                                $stmtSkill = $db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);
                                $stmtSkill->closeCursor();

                                // Inserisci la skill richiesta per questo profilo
                                $stmtSkillRich = $db->callStoredProcedure('InserisciSkillRichiesta', [
                                    $id_profilo, $competenza, $livello
                                ]);
                                $stmtSkillRich->closeCursor();
                            }
                        }
                    }
                }
                $countItems = count($rewards) + count($profili);
                $itemType = "reward e profili";
            } else {
                $countItems = count($rewards);
                $itemType = "reward";
            }
        }

        $db->commit();

        // Debug per capire cosa è stato creato
        error_log("✅ Progetto '{$nome}' ({$tipo}) creato con successo:");
        error_log("- Rewards: " . count($rewards));
        error_log("- Componenti: " . count($componenti));
        error_log("- Profili: " . count($profili));
        foreach ($profili as $p) {
            error_log("  * Profilo: {$p['nome']} con " . count($p['competenze']) . " skill");
        }

        $success_message = "Progetto '{$nome}' ({$tipo}) creato con successo con {$countItems} {$itemType}!";

        // Redirect dopo 2 secondi
        header("refresh:2;url=creator_dashboard.php");

    } catch (Exception $e) {
        $db->rollback();
        error_log("❌ Errore creazione progetto: " . $e->getMessage());
        $error_message = "Errore durante l'inserimento: " . $e->getMessage();
    }
}
}
?>

<!--
ESEMPIO DI DEBUG - Aggiungi questo codice temporaneamente dopo il POST per vedere la struttura dati:

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_skills'])): ?>
<div class="alert alert-info">
    <h5>🔍 DEBUG - Struttura dati ricevuta:</h5>
    <pre><?= htmlspecialchars(print_r($_POST, true)) ?></pre>
</div>
<?php endif; ?>
-->
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
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --small-radius: 12px;
        }
        .navbar {
            background: var(--primary-gradient) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1rem 0;
            z-index: 50;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: var(--small-radius);
            margin: 0 0.25rem;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        .project-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .progress-bar-custom {
            background: linear-gradient(45deg, #007bff, #0056b3);
        }
        .badge-hardware {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .badge-software {
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
        }
        .creator-badge {
            background: linear-gradient(45deg, #fd7e14, #ffc107);
        }
    </style>
</head>
<body>

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

                <!-- SEZIONE REWARD (sempre visibile) -->
                <div class="card border-primary mb-4" id="rewardsSection">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-gift me-2"></i>Reward del Progetto
                            </h5>
                            <span class="counter">
                                <span id="rewardCount">0</span> reward
                            </span>
                        </div>
                        <small>Aggiungi le ricompense che i sostenitori riceveranno (obbligatorio per tutti i progetti)</small>
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

                <!-- SEZIONE PROFILI SOFTWARE -->
                <div class="card border-info mb-4" id="softwareProfilesSection" style="display: none;">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-users-cog me-2"></i>Profili Richiesti per il Progetto Software
                            </h5>
                            <span class="counter">
                <span id="profileCount">0</span> profili
            </span>
                        </div>
                        <small>Definisci i profili professionali che cerchi per il tuo progetto</small>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-question-circle me-1"></i>Vuoi specificare profili con skill specifiche?
                            </label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="profili" id="profiliSi" value="si" onchange="toggleProfiloInput('si')">
                                <label class="form-check-label" for="profiliSi">Sì</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="profili" id="profiliNo" value="no" onchange="toggleProfiloInput('no')" checked>
                                <label class="form-check-label" for="profiliNo">No</label>
                            </div>
                        </div>

                        <div id="profili-input" class="mt-3" style="display: none;">
                            <!-- Container per lista profili -->
                            <div id="profilesContainer">
                                <!-- I profili verranno aggiunti qui dinamicamente -->
                            </div>

                            <button type="button" class="btn btn-success" onclick="addProfile()">
                                <i class="fas fa-user-plus me-2"></i>Aggiungi Nuovo Profilo
                            </button>

                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Esempi di profili:</strong> Sviluppatore Backend, Esperto AI, Data Scientist,
                                    DevOps Engineer, Frontend Developer, ecc.
                                </small>
                            </div>

                            <!-- Riepilogo profili -->
                            <div id="profilesSummary" class="mt-4 p-3 bg-light rounded" style="display: none;">
                                <div class="row">
                                    <div class="col-md-12">
                                        <strong>Profili Richiesti:</strong> <span id="totalProfiles">0</span>
                                        <div id="profilesList" class="mt-2"></div>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    Elenco dei profili che riceveranno candidature
                                </small>
                            </div>
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
    // Variabili globali
    let rewardCount = 0;
    let componentCount = 0;
    let profileCount = 0;
    window.competenzeDisponibili = [];

    // Funzioni per gestione competenze dal database
    function loadCompetenze() {
        fetch('../../api/manage_skill.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_available_skills'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.competenzeDisponibili = data.skills;
                    aggiornaSelectCompetenze();
                }
            })
            .catch(error => {
                console.error('Errore caricamento competenze:', error);
            });
    }

    function aggiornaSelectCompetenze() {
        document.querySelectorAll('.competenza-select').forEach(select => {
            select.innerHTML = '<option value="">Seleziona competenza...</option>';
            window.competenzeDisponibili.forEach(skill => {
                select.innerHTML += `<option value="${skill.Competenza}">${skill.Competenza}</option>`;
            });
        });
    }

    // Funzione per validazione real-time delle skill per un profilo specifico
    function validateSkillsForProfile(profileId) {
        const skillSelects = document.querySelectorAll(`#profile_${profileId} select[name="profile_skills[${profileId}][competenze][]"]`);
        const selectedCompetenze = [];
        const duplicates = [];

        skillSelects.forEach((select) => {
            const competenza = select.value.trim();

            // Reset stile
            select.style.borderColor = '';
            select.style.backgroundColor = '';

            if (competenza) {
                if (selectedCompetenze.includes(competenza)) {
                    // Duplicato trovato
                    select.style.borderColor = '#dc3545';
                    select.style.backgroundColor = '#ffe6e6';
                    if (!duplicates.includes(competenza)) {
                        duplicates.push(competenza);
                    }
                } else {
                    selectedCompetenze.push(competenza);
                }
            }
        });

        // Mostra warning se ci sono duplicati
        const warningDiv = document.getElementById(`duplicateWarning_${profileId}`);
        if (duplicates.length > 0) {
            if (!warningDiv) {
                const warning = document.createElement('div');
                warning.id = `duplicateWarning_${profileId}`;
                warning.className = 'alert alert-warning mt-2';
                warning.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attenzione:</strong> Competenze duplicate in questo profilo: ${duplicates.join(', ')}
                `;
                document.getElementById(`profile_${profileId}`).appendChild(warning);
            } else {
                warningDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attenzione:</strong> Competenze duplicate in questo profilo: ${duplicates.join(', ')}
                `;
            }
        } else {
            if (warningDiv) {
                warningDiv.remove();
            }
        }
    }

    // Gestione tipo progetto
    document.getElementById('tipo').addEventListener('change', function() {
        const tipo = this.value;
        const rewardsSection = document.getElementById('rewardsSection');
        const componentsSection = document.getElementById('componentsSection');
        const softwareProfilesSection = document.getElementById('softwareProfilesSection');

        if (tipo === 'Software') {
            rewardsSection.style.display = 'block';
            componentsSection.style.display = 'none';
            softwareProfilesSection.style.display = 'block';
            // Aggiungi automaticamente una reward se non ce ne sono
            if (rewardCount === 0) {
                addReward();
            }
            // Carica competenze per progetti software
            if (!window.competenzeDisponibili || window.competenzeDisponibili.length === 0) {
                loadCompetenze();
            }
        } else if (tipo === 'Hardware') {
            rewardsSection.style.display = 'block';
            componentsSection.style.display = 'block';
            softwareProfilesSection.style.display = 'none';
            // Aggiungi automaticamente una reward se non ce ne sono
            if (rewardCount === 0) {
                addReward();
            }
            // Aggiungi automaticamente un componente se non ce ne sono
            if (componentCount === 0) {
                addComponent();
            }
        } else {
            rewardsSection.style.display = 'none';
            componentsSection.style.display = 'none';
            softwareProfilesSection.style.display = 'none';
        }
    });

    // Inizializzazione
    document.addEventListener('DOMContentLoaded', function() {
        // Aggiungi automaticamente una reward all'avvio
        if (rewardCount === 0) {
            addReward();
        }
    });

    // Funzioni reward (invariate)
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

    function removeReward(id) {
        const rewardElement = document.getElementById(`reward_${id}`);
        if (rewardElement) {
            rewardElement.remove();
            updateRewardCount();
        }
    }

    function updateRewardCount() {
        const currentRewards = document.querySelectorAll('.reward-item').length;
        document.getElementById('rewardCount').textContent = currentRewards;
    }

    // Funzioni componenti (invariate)
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

    function removeComponent(id) {
        const componentElement = document.getElementById(`component_${id}`);
        if (componentElement) {
            componentElement.remove();
            updateComponentsCount();
            updateComponentsSummary();
        }
    }

    function updateComponentsCount() {
        const currentComponents = document.querySelectorAll('.component-item').length;
        document.getElementById('componentCount').textContent = currentComponents;
    }

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

    // Nuove funzioni per gestione multipli profili
    function toggleProfiloInput(valore) {
        const profiliInput = document.getElementById("profili-input");
        profiliInput.style.display = (valore === "si") ? "block" : "none";

        if (valore === "si") {
            // Carica competenze quando si abilita la sezione profili
            if (!window.competenzeDisponibili || window.competenzeDisponibili.length === 0) {
                loadCompetenze();
            }
            // Aggiungi automaticamente un profilo se non ce ne sono
            if (profileCount === 0) {
                addProfile();
            }
        }
        if (valore === "no") {
            // Pulisci tutto quando si disabilita
            document.getElementById("profilesContainer").innerHTML = '';
            profileCount = 0;
            updateProfileCount();
            updateProfilesSummary();
        }
    }

    function addProfile() {
        profileCount++;

        const profileHtml = `
        <div class="profile-item card border-info mb-3" id="profile_${profileCount}">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-user-tie me-2"></i>Profilo ${profileCount}
                    </h6>
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            onclick="removeProfile(${profileCount})"
                            title="Rimuovi profilo">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Nome del Profilo</label>
                    <input type="text"
                           name="profile_names[]"
                           class="form-control"
                           placeholder="es: Esperto AI, Sviluppatore Backend, DevOps Engineer..."
                           required>
                    <small class="text-muted">Dai un nome descrittivo al profilo che cerchi</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Skill Richieste per questo Profilo</label>
                    <div id="skillsContainer_${profileCount}">
                        <!-- Le skill verranno aggiunte qui -->
        </div>
        <button type="button"
        class="btn btn-outline-success btn-sm mt-2"
        onclick="addSkillToProfile(${profileCount})">
        <i class="fas fa-plus me-1"></i>Aggiungi Skill
        </button>
        </div>
        </div>
        </div>
        `;

        document.getElementById('profilesContainer').insertAdjacentHTML('beforeend', profileHtml);

        // Aggiungi automaticamente una skill al nuovo profilo
        addSkillToProfile(profileCount);

        updateProfileCount();
        updateProfilesSummary();
    }

    function removeProfile(profileId) {
        const profileElement = document.getElementById(`profile_${profileId}`);
        if (profileElement) {
            profileElement.remove();
            updateProfileCount();
            updateProfilesSummary();
        }
    }

    function addSkillToProfile(profileId) {
        const skillsContainer = document.getElementById(`skillsContainer_${profileId}`);
        const skillId = `skill_${profileId}_${Date.now()}`;

        const skillHtml = `
        <div class="row g-2 mb-2 align-items-center" id="${skillId}">
        <div class="col-md-6">
        <select name="profile_skills[${profileId}][competenze][]"
        class="form-control competenza-select"
        onchange="validateSkillsForProfile(${profileId})"
        required>
        <option value="">Caricamento...</option>
        </select>
        </div>
        <div class="col-md-4">
        <input type="number"
        name="profile_skills[${profileId}][livelli][]"
        class="form-control"
        placeholder="Livello (0-5)"
        min="0" max="5"
        required>
        </div>
        <div class="col-md-2">
        <button type="button"
        class="btn btn-outline-danger btn-sm w-100"
        onclick="removeSkillFromProfile('${skillId}', ${profileId})">
        <i class="fas fa-minus"></i>
        </button>
        </div>
        </div>
        `;

        skillsContainer.insertAdjacentHTML('beforeend', skillHtml);

        // Popola il nuovo select
        const newSelect = document.getElementById(skillId).querySelector('.competenza-select');
        if (window.competenzeDisponibili && Array.isArray(window.competenzeDisponibili) && window.competenzeDisponibili.length > 0) {
            newSelect.innerHTML = '<option value="">Seleziona competenza...</option>';
            window.competenzeDisponibili.forEach(skill => {
                newSelect.innerHTML += `<option value="${skill.Competenza}">${skill.Competenza}</option>`;
            });
        }
    }

    function removeSkillFromProfile(skillId, profileId) {
        const skillElement = document.getElementById(skillId);
        if (skillElement) {
            skillElement.remove();
            // Rivalida dopo rimozione
            validateSkillsForProfile(profileId);
        }
    }

    function updateProfileCount() {
        const currentProfiles = document.querySelectorAll('.profile-item').length;
        document.getElementById('profileCount').textContent = currentProfiles;
    }

    function updateProfilesSummary() {
        const profiles = document.querySelectorAll('.profile-item');
        const summaryDiv = document.getElementById('profilesSummary');

        if (profiles.length === 0) {
            summaryDiv.style.display = 'none';
            return;
        }

        let profilesList = [];
        profiles.forEach(profile => {
            const nameInput = profile.querySelector('input[name="profile_names[]"]');
            if (nameInput && nameInput.value.trim()) {
                profilesList.push(nameInput.value.trim());
            }
        });

        document.getElementById('totalProfiles').textContent = profiles.length;
        document.getElementById('profilesList').innerHTML = profilesList.length > 0
            ? profilesList.map(name => `<span class="badge bg-info me-1">${name}</span>`).join('')
            : '<em class="text-muted">Nomi profili non ancora inseriti</em>';

        summaryDiv.style.display = 'block';
    }

    // Aggiorna riepilogo quando l'utente digita nomi profili
    document.addEventListener('input', function(e) {
        if (e.target.matches('input[name="profile_names[]"]')) {
            updateProfilesSummary();
        }
    });

    // Validazione form potenziata per multipli profili
    document.getElementById('projectForm').addEventListener('submit', function(e) {
        const tipo = document.getElementById('tipo').value;

        // Verifica sempre le reward (obbligatorie per tutti i tipi)
        const rewards = document.querySelectorAll('.reward-item').length;
        if (rewards === 0) {
            e.preventDefault();
            alert('Devi aggiungere almeno una reward per il progetto!');
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

        if (tipo === 'Software') {
            // Verifica profili se "Sì" è selezionato
            const profiliSi = document.getElementById('profiliSi');
            if (profiliSi.checked) {
                const profiles = document.querySelectorAll('.profile-item');

                if (profiles.length === 0) {
                    e.preventDefault();
                    alert('Devi aggiungere almeno un profilo per il progetto Software!');
                    return false;
                }

                // Controlla ogni profilo
                for (let profile of profiles) {
                    const profileName = profile.querySelector('input[name="profile_names[]"]').value.trim();
                    const skillSelects = profile.querySelectorAll('select[name^="profile_skills"]');
                    const levelInputs = profile.querySelectorAll('input[name^="profile_skills"]');

                    if (!profileName) {
                        e.preventDefault();
                        alert('Tutti i profili devono avere un nome!');
                        return false;
                    }

                    if (skillSelects.length === 0) {
                        e.preventDefault();
                        alert(`Il profilo "${profileName}" deve avere almeno una skill!`);
                        return false;
                    }

                    // Controlla duplicati skill nel profilo
                    const competenzeInProfilo = [];
                    const duplicatiInProfilo = [];

                    for (let i = 0; i < skillSelects.length; i++) {
                        const competenza = skillSelects[i].value.trim();
                        const livello = levelInputs[i].value;

                        if (!competenza || !livello) {
                            e.preventDefault();
                            alert(`Tutte le skill del profilo "${profileName}" devono essere compilate!`);
                            return false;
                        }

                        if (competenzeInProfilo.includes(competenza)) {
                            if (!duplicatiInProfilo.includes(competenza)) {
                                duplicatiInProfilo.push(competenza);
                            }
                        } else {
                            competenzeInProfilo.push(competenza);
                        }
                    }

                    if (duplicatiInProfilo.length > 0) {
                        e.preventDefault();
                        alert(
                            `ERRORE nel profilo "${profileName}":\n\n` +
                            `Le seguenti competenze sono duplicate: ${duplicatiInProfilo.join(', ')}\n\n` +
                            `Ogni competenza può essere richiesta solo UNA volta per profilo.`
                        );
                        return false;
                    }
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