<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../../config/database.php';

session_start();

// Inizializza le variabili per la navbar
$isLoggedIn = isset($_SESSION['user_email']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$isCreator = isset($_SESSION['is_creator']) && $_SESSION['is_creator'] == 1;

// Mini-classe per simulare SessionManager::get, se non esiste già
if (!class_exists('SessionManager')) {
    class SessionManager {
        public static function get($key, $default = null) {
            return $_SESSION[$key] ?? $default;
        }
    }
}

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
    $tipo = $_POST['tipo'] ?? ''; // Recupera il tipo di progetto dal form
    $email = $_SESSION['user_email'];

    // Recupera il nome del profilo richiesto se presente
    $profilo_richiesto_nome = trim($_POST['profilo_richiesto_nome'] ?? '');

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
            } else {
                // Validazione per il nome del profilo richiesto solo se applicabile
                if ($tipo === 'Software' && isset($_POST['profili']) && $_POST['profili'] === 'si' && empty($profilo_richiesto_nome)) {
                    $error_message = "Il nome del profilo richiesto è obbligatorio per i progetti Software con profili specifici.";
                }
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
                $email,
                $tipo
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

            // ✅ LOGICA PER PROFILI E SKILL (SOLO SE PROGETTO SOFTWARE E PROFILI RICHIESTI)
            if ($tipo === 'Software' && isset($_POST['profili']) && $_POST['profili'] === 'si' && !empty($profilo_richiesto_nome)) {
                $competenze = $_POST['competenze'] ?? [];
                $livelli = $_POST['livelli'] ?? [];

                // Utilizza il nome del profilo richiesto dal form
                $result_profilo = $db->fetchOne("CALL InserisciProfiloRichiesto(?, ?)", [$profilo_richiesto_nome, $nome]);
                $id_profilo = $result_profilo['ID_Profilo'] ?? null;

                if ($id_profilo) {
                    for ($i = 0; $i < count($competenze); $i++) {
                        $competenza = trim($competenze[$i]);
                        $livello = intval($livelli[$i]);

                        if (!empty($competenza) && $livello >= 0 && $livello <= 5) {
                            $db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);
                            $db->callStoredProcedure('InserisciSkillRichiesta', [
                                $id_profilo,
                                $competenza,
                                $livello
                            ]);
                        }
                    }
                } else {
                    throw new Exception("Errore durante la creazione del profilo richiesto.");
                }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6f42c1; /* Viola */
            --secondary-color: #007bff; /* Blu */
            --gradient-start: #8a2be2; /* BlueViolet */
            --gradient-end: #4a008a; /* DarkPurple */
            --bg-light: #e0e6ed;
            --bg-dark: #c3cfe2;
            --card-bg: #ffffff;
            --text-color: #333;
            --border-color: #e9ecef;
            --input-border: #ced4da;
            --shadow-light: rgba(0, 0, 0, 0.08);
            --shadow-medium: rgba(0, 0, 0, 0.15);

            /* Nuove variabili per la navbar */
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --small-radius: 12px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-dark) 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-color);
        }
        /* Stili della navbar forniti dall'utente */
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
            color: white !important; /* Aggiunto per coerenza */
        }
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: var(--small-radius);
            margin: 0 0.25rem;
            color: white !important; /* Aggiunto per coerenza */
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        /* Fine stili navbar forniti */

        .form-container {
            max-width: 900px;
            margin: 40px auto;
            background: var(--card-bg);
            border-radius: 25px;
            box-shadow: 0 15px 45px var(--shadow-medium);
            overflow: hidden;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .form-header {
            background: linear-gradient(45deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2.5rem;
            text-align: center;
            border-bottom: 5px solid rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        .form-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .form-body {
            padding: 3rem;
            flex-grow: 1;
        }
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-control, .form-select {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid var(--input-border);
            box-shadow: inset 0 1px 3px var(--shadow-light);
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
            outline: none;
        }
        .alert {
            border-radius: 0.75rem;
            font-weight: 500;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        /* Reward Section */
        .card.border-primary {
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 8px 25px var(--shadow-light);
        }
        .card-header {
            background: linear-gradient(45deg, var(--secondary-color), #0056b3);
            border-bottom: none;
            padding: 1.5rem 2rem;
        }
        .card-header h5 {
            color: white;
            font-weight: 600;
        }
        .reward-counter {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .reward-item {
            background: #f8f9fa;
            border: 1px dashed var(--input-border);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        .reward-item:hover {
            border-color: var(--secondary-color);
            background: #e9f5ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px var(--shadow-light);
        }
        .btn-add-reward {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-add-reward:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-remove-reward {
            background: #dc3545; /* Bootstrap danger */
            border: none;
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-remove-reward:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        /* Buttons */
        .btn-primary, .btn-outline-secondary {
            border-radius: 0.75rem;
            padding: 0.85rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(138, 43, 226, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
            filter: brightness(1.1);
        }
        .btn-outline-secondary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 10px rgba(111, 66, 193, 0.2);
        }

        /* Profile/Skill Section */
        #software-options, #profili-input {
            border: 1px dashed var(--primary-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            background-color: rgba(111, 66, 193, 0.05); /* Light primary color background */
        }
        #software-options label {
            color: var(--text-color); /* Override form-label primary color */
            font-weight: 500;
        }
        .profilo-entry {
            background: #ffffff;
            border: 1px solid var(--input-border);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px var(--shadow-light);
        }
        .profilo-entry input {
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
        }
        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
            border-radius: 0.75rem;
        }
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
    </style>

    <script>
        function toggleSoftwareOptionsSelect(select) {
            const tipo = select.value;
            const softwareOptions = document.getElementById("software-options");
            const profiliInput = document.getElementById("profili-input");
            const profiliSiRadio = document.getElementById("profiliSi");
            const profiliNoRadio = document.getElementById("profiliNo");

            if (tipo === "Software") { // Case-sensitive "Software"
                softwareOptions.style.display = "block";
                // Only default to "No" and hide profiliInput if neither radio is checked (fresh selection)
                if (!profiliSiRadio.checked && !profiliNoRadio.checked) {
                    profiliNoRadio.checked = true; // Default to "No"
                    profiliInput.style.display = "none";
                }
                // If one is already checked (e.g., from $_POST), let toggleProfiloInput handle it
            } else { // Implicitly "Hardware" or initial empty selection
                softwareOptions.style.display = "none";
                profiliInput.style.display = "none"; // Ensure profili input is also hidden
                // Reset radio buttons for a clean slate if type changes away from "Software"
                if (profiliSiRadio) profiliSiRadio.checked = false;
                if (profiliNoRadio) profiliNoRadio.checked = false;

                // Clear skill inputs and profile name when switching away from software
                const listaProfili = document.getElementById("lista-profili");
                if(listaProfili) listaProfili.innerHTML = '';
                const profiloNomeInput = document.getElementById('profilo_richiesto_nome');
                if (profiloNomeInput) profiloNomeInput.value = '';
            }
        }

        function toggleProfiloInput(valore) {
            const profiliInput = document.getElementById("profili-input");
            profiliInput.style.display = (valore === "si") ? "block" : "none";

            // If "Si" is selected and no skill inputs exist, add one
            if (valore === "si" && document.querySelectorAll('#lista-profili .profilo-entry').length === 0) {
                aggiungiProfilo();
            }
            // If "No" is selected, clear skill inputs and profile name
            if (valore === "no") {
                const listaProfili = document.getElementById("lista-profili");
                if(listaProfili) listaProfili.innerHTML = '';
                const profiloNomeInput = document.getElementById('profilo_richiesto_nome');
                if (profiloNomeInput) profiloNomeInput.value = '';
            }
        }

        function aggiungiProfilo() { // Funzione per aggiungere un nuovo campo di profilo/skill
            const nuovo = document.createElement("div");
            nuovo.className = "profilo-entry";
            nuovo.innerHTML = `
                <input type="text" name="competenze[]" class="form-control" placeholder="Competenza (es: AI)" required>
                <input type="number" name="livelli[]" class="form-control" placeholder="Livello (0-5)" min="0" max="5" required>
                <button type="button" class="btn btn-danger btn-sm btn-remove-skill" onclick="rimuoviProfilo(this)"><i class="fas fa-minus-circle"></i></button>
            `;
            document.getElementById("lista-profili").appendChild(nuovo);
        }

        function rimuoviProfilo(button) { // Funzione per rimuovere un campo di profilo/skill
            button.closest('.profilo-entry').remove();
            // Ensure at least one skill input remains if "Si" is selected and there are no skills left
            if (document.getElementById("profiliSi").checked && document.querySelectorAll('#lista-profili .profilo-entry').length === 0) {
                // Not adding one automatically here, as the user might want to remove all.
                // The validation will catch if no skills are entered when 'Si' is chosen.
            }
        }
    </script>


</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.html">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="../projects.php">
                        <i class="fas fa-project-diagram me-1"></i>Progetti
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= htmlspecialchars(SessionManager::get('user_nickname', 'Utente')) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-shield-alt me-2"></i>Dashboard Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php elseif ($isCreator): ?>
                                <li><a class="dropdown-item" href="creator_dashboard.php"><i class="fas fa-user-cog me-2"></i>Dashboard Creatore</a></li>
                                <li><a class="dropdown-item" href="new_project.php"><i class="fas fa-plus me-2"></i>Crea Progetto</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                            <li><a class="dropdown-item" href="statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/login.php"><i class="fas fa-sign-in-alt me-1"></i>Accedi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/register.php"><i class="fas fa-user-plus me-1"></i>Registrati</a>
                    </li>
                <?php endif; ?>
            </ul>
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
                        <select class="form-select" id="tipo" name="tipo" required onchange="toggleSoftwareOptionsSelect(this)">
                            <option value="">Seleziona tipo...</option>
                            <option value="Hardware" <?= (isset($_POST['tipo']) && $_POST['tipo'] == 'Hardware') ? 'selected' : '' ?>>Hardware</option>
                            <option value="Software" <?= (isset($_POST['tipo']) && $_POST['tipo'] == 'Software') ? 'selected' : '' ?>>Software</option>
                        </select>
                    </div>
                </div>

                <div id="software-options" class="mb-3" style="display:<?= (isset($_POST['tipo']) && $_POST['tipo'] == 'Software') ? 'block' : 'none' ?>;">
                    <label class="form-label">
                        <i class="fas fa-users-cog me-1"></i>Ricerchi profili con skill specifiche per il tuo progetto software?
                    </label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="profili" id="profiliSi" value="si" onchange="toggleProfiloInput('si')"
                               <?= (isset($_POST['profili']) && $_POST['profili'] == 'si') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="profiliSi">Sì</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="profili" id="profiliNo" value="no" onchange="toggleProfiloInput('no')"
                               <?= (isset($_POST['profili']) && $_POST['profili'] == 'no') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="profiliNo">No</label>
                    </div>

                    <div id="profili-input" class="mt-3" style="display:<?= (isset($_POST['profili']) && $_POST['profili'] == 'si') ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label for="profilo_richiesto_nome" class="form-label">
                                <i class="fas fa-id-badge me-1"></i>Nome del Profilo Richiesto
                            </label>
                            <input type="text" class="form-control" id="profilo_richiesto_nome" name="profilo_richiesto_nome"
                                   placeholder="Es: Sviluppatore Backend, Esperto AI"
                                   value="<?= htmlspecialchars($_POST['profilo_richiesto_nome'] ?? '') ?>">
                            <small class="form-text text-muted">Dai un nome al tipo di profilo che cerchi.</small>
                        </div>
                        <h6><i class="fas fa-list-ul me-1"></i>Skill richieste per questo profilo:</h6>
                        <div id="lista-profili">
                            <?php
                            // Ripopola i campi delle skill se c'è stato un errore di validazione
                            if (isset($_POST['competenze']) && isset($_POST['livelli'])) {
                                for ($i = 0; $i < count($_POST['competenze']); $i++) {
                                    $comp = htmlspecialchars($_POST['competenze'][$i]);
                                    $liv = htmlspecialchars($_POST['livelli'][$i]);
                                    echo "<div class='profilo-entry'>";
                                    echo "<input type='text' name='competenze[]' class='form-control' placeholder='Competenza (es: AI)' value='{$comp}' required>";
                                    echo "<input type='number' name='livelli[]' class='form-control' placeholder='Livello (0-5)' min='0' max='5' value='{$liv}' required>";
                                    echo "<button type='button' class='btn btn-danger btn-sm btn-remove-skill' onclick='rimuoviProfilo(this)'><i class='fas fa-minus-circle'></i></button>";
                                    echo "</div>";
                                }
                            }
                            ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="aggiungiProfilo()">
                            <i class="fas fa-plus me-1"></i>Aggiungi un'altra skill
                        </button>
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
                            <?php
                            // Ripopola i campi delle reward se c'è stato un errore di validazione
                            if (!empty($rewards)) {
                                $i = 0;
                                foreach ($rewards as $reward) {
                                    $i++;
                                    echo "<div class='reward-item' id='reward_{$i}'>";
                                    echo "<div class='row'>";
                                    echo "<div class='col-md-4'>";
                                    echo "<label class='form-label'>Codice Reward</label>";
                                    echo "<input type='text' name='reward_codes[]' class='form-control' placeholder='es: EARLY_BIRD' value='" . htmlspecialchars($reward['codice']) . "' required>";
                                    echo "<small class='text-muted'>Identificativo univoco</small>";
                                    echo "</div>";
                                    echo "<div class='col-md-7'>";
                                    echo "<label class='form-label'>Descrizione</label>";
                                    echo "<input type='text' name='reward_descriptions[]' class='form-control' placeholder='es: Accesso anticipato al prodotto' value='" . htmlspecialchars($reward['descrizione']) . "' required>";
                                    echo "<small class='text-muted'>Cosa riceverà il sostenitore</small>";
                                    echo "</div>";
                                    echo "<div class='col-md-1 d-flex align-items-end'>";
                                    echo "<button type='button' class='btn btn-remove-reward btn-sm' onclick='removeReward({$i})' title='Rimuovi reward'>";
                                    echo "<i class='fas fa-trash'></i>";
                                    echo "</button>";
                                    echo "</div>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                            }
                            ?>
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

                <div class="d-grid gap-2 mt-4">
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
    let rewardCount = <?= !empty($rewards) ? count($rewards) : 0 ?>; // Inizializza il contatore con le reward già presenti

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

        // Validazione per i profili e le skill se il tipo è "Software" e "Sì" è selezionato
        const tipoProgetto = document.getElementById('tipo').value;
        const profiliSi = document.getElementById('profiliSi');
        const profiloNomeInput = document.getElementById('profilo_richiesto_nome');

        if (tipoProgetto === 'Software' && profiliSi && profiliSi.checked) {
            if (!profiloNomeInput || !profiloNomeInput.value.trim()) {
                e.preventDefault();
                alert('⚠️ Se hai selezionato "Sì" per la ricerca di profili, devi specificare un nome per il profilo richiesto!');
                return false;
            }

            const competenzeInputs = document.querySelectorAll('#lista-profili input[name="competenze[]"]');
            const livelliInputs = document.querySelectorAll('#lista-profili input[name="livelli[]"]');

            if (competenzeInputs.length === 0) {
                e.preventDefault();
                alert('⚠️ Se hai selezionato "Sì" per la ricerca di profili, devi aggiungere almeno una skill richiesta!');
                return false;
            }

            for (let i = 0; i < competenzeInputs.length; i++) {
                if (!competenzeInputs[i].value.trim() || !livelliInputs[i].value.trim()) {
                    e.preventDefault();
                    alert('⚠️ Tutti i campi delle skill richieste devono essere compilati!');
                    return false;
                }
                const livello = parseInt(livelliInputs[i].value);
                if (isNaN(livello) || livello < 0 || livello > 5) {
                    e.preventDefault();
                    alert('⚠️ Il livello della skill deve essere un numero tra 0 e 5!');
                    return false;
                }
            }
        }

        return true;
    });

    // ✅ Aggiungi automaticamente una reward all'avvio solo se non ci sono già reward (es. dopo un errore di validazione)
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize reward count display and add default if none exist
        updateRewardCount();
        if (rewardCount === 0) {
            addReward();
        }

        // Initialize project type and profile options display based on pre-filled values from $_POST
        const tipoSelect = document.getElementById("tipo");
        const profiliSiRadio = document.getElementById("profiliSi");
        const profiliNoRadio = document.getElementById("profiliNo");
        const listaProfili = document.getElementById("lista-profili"); // Get the container for skill inputs

        if (tipoSelect) {
            toggleSoftwareOptionsSelect(tipoSelect); // This will handle initial #software-options visibility

            // Explicitly set #profili-input visibility based on the 'profili' radio button state
            if (profiliSiRadio && profiliSiRadio.checked) {
                toggleProfiloInput('si');
                // If 'Si' is checked and there are no pre-filled skills (e.g., first load for Software type)
                if (listaProfili.children.length === 0) {
                     aggiungiProfilo(); // Add one default skill input
                }
            } else if (profiliNoRadio && profiliNoRadio.checked) {
                toggleProfiloInput('no');
            }
            // If it's a fresh load and "Software" is selected, default "No" for profili
            else if (tipoSelect.value === "Software") {
                profiliNoRadio.checked = true;
                toggleProfiloInput('no');
            }
            // If it's a fresh load and "Hardware" is selected or no type selected, ensure profili input is hidden
            else {
                toggleProfiloInput('no'); // Ensures it's hidden if no type or Hardware
            }
        }
    });
</script>
</body>
</html>