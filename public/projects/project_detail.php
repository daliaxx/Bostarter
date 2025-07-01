<?php
require_once '../../config/database.php';
require_once '../../includes/navbar.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function formatCurrency($amount) {
    return '€ ' . number_format($amount, 2, ',', '.');
}

// Sessione e verifica utente
SessionManager::start();
$isLoggedIn = isset($_SESSION['user_email']);
$userEmail = $_SESSION['user_email'] ?? null;
$isAdmin = SessionManager::isAdmin();
$isCreator = SessionManager::isCreator();

// Controllo nome progetto
if (!isset($_GET['name']) || empty($_GET['name'])) {
    die("<h3>Errore: nome del progetto non specificato.</h3>");
}

$nomeProgetto = $_GET['name'];
$db = Database::getInstance();
$db->ensureEventScheduler();

// Query per i dettagli del progetto
$sql = "
    SELECT
        p.Nome, p.Descrizione, p.Data_Inserimento, p.Stato, p.Budget, p.Data_Limite, p.Tipo, p.Email_Creatore,
        u.Nickname AS CreatoreNickname, u.Nome AS CreatoreNome, u.Cognome AS CreatoreCognome,
        c.Affidabilita,
        COALESCE(SUM(f.Importo), 0) AS Totale_Finanziato,
        COUNT(DISTINCT f.Email_Utente) AS Num_Finanziatori,
        (SELECT percorso FROM FOTO WHERE Nome_Progetto = p.Nome LIMIT 1) AS Foto,
        DATEDIFF(p.Data_Limite, CURDATE()) AS Giorni_Rimanenti
    FROM PROGETTO p
    JOIN CREATORE c ON p.Email_Creatore = c.Email
    JOIN UTENTE u ON c.Email = u.Email
    LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
    WHERE p.Nome = ?
    GROUP BY p.Nome, p.Descrizione, p.Data_Inserimento, p.Stato, p.Budget, p.Data_Limite, p.Tipo,
             u.Nickname, u.Nome, u.Cognome, c.Affidabilita
";

$progetto = $db->fetchOne($sql, [$nomeProgetto]);

// Gestione messaggi di errore e successo
$errorMessage = '';
$successMessage = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_rewards':
            $errorMessage = 'Questo progetto non ha ancora definito le reward per i sostenitori.';
            break;
        case 'importo_non_valido':
            $errorMessage = 'Inserisci un importo valido maggiore di zero.';
            break;
        case 'reward_obbligatoria':
            $errorMessage = 'Devi selezionare una reward per completare il finanziamento.';
            break;
        case 'reward_non_valida':
            $errorMessage = 'La reward selezionata non è valida per questo progetto.';
            break;
        case 'errore_server':
            $errorMessage = 'Errore durante il finanziamento. Riprova più tardi.';
            break;
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'finanziato') {
    $successMessage = 'Finanziamento completato con successo! Grazie per il tuo supporto.';
}

// Aggiornamento automatico stato progetto scaduto
if ($progetto && $progetto['Stato'] === 'aperto' && $progetto['Data_Limite'] <= date('Y-m-d')) {
    $db->execute("UPDATE PROGETTO SET Stato = 'chiuso' WHERE Nome = ?", [$nomeProgetto]);
    $progetto['Stato'] = 'chiuso';
}

if (!$progetto) {
    die("<h3>Progetto non trovato.</h3>");
}

// Calcoli per visualizzazione
$percentuale = $progetto['Budget'] > 0 ? round($progetto['Totale_Finanziato'] / $progetto['Budget'] * 100, 1) : 0;
$statoClasse = ($progetto['Stato'] === 'aperto' && $progetto['Giorni_Rimanenti'] > 0 && $percentuale < 100) ? 'success' : 'secondary';

if ($progetto['Stato'] === 'chiuso' || $progetto['Giorni_Rimanenti'] <= 0 || $percentuale >= 100) {
    $statoClasse = 'info';
    if ($percentuale >= 100) {
        $statoClasse = 'primary';
    }
    if ($progetto['Giorni_Rimanenti'] <= 0 && $percentuale < 100) {
        $statoClasse = 'danger';
    }
}

// Gestione percorso immagine
$fotoDb = $progetto['Foto'] ?? '';
if ($fotoDb !== '') {
    $src = (strpos($fotoDb, 'img/') === 0) ? "/Bostarter/{$fotoDb}" : "/Bostarter/img/{$fotoDb}";
} else {
    $src = "/Bostarter/img/placeholder.jpg";
}

// Recupero rewards del progetto
$rewards = $db->fetchAll("SELECT Codice, Descrizione, Foto FROM REWARD WHERE Nome_Progetto = ? ORDER BY Codice ASC", [$nomeProgetto]);

// Recupero profili richiesti per progetti software
$profiliRicercati = $db->fetchAll("SELECT ID, Nome FROM PROFILO WHERE Nome_Progetto = ?", [$nomeProgetto]);

// Recupero componenti per progetti hardware
$componenti = [];
$totaleComponenti = 0;
if ($progetto['Tipo'] === 'Hardware') {
    $componenti = $db->fetchAll("
        SELECT ID, Nome, Descrizione, Prezzo, Quantita, (Prezzo * Quantita) as Totale
        FROM COMPONENTE
        WHERE Nome_Progetto = ?
        ORDER BY Nome ASC
    ", [$nomeProgetto]);

    foreach ($componenti as $componente) {
        $totaleComponenti += $componente['Totale'];
    }
}

// Verifica skill per candidature
if ($isLoggedIn && $progetto['Tipo'] === 'Software' && $progetto['Stato'] === 'aperto' && !empty($profiliRicercati)) {
    foreach ($profiliRicercati as &$profilo) {
        // Skill richieste per questo profilo
        $skillRichieste = $db->fetchAll("
            SELECT sr.Competenza, sr.Livello
            FROM SKILL_RICHIESTA sr
            WHERE sr.ID_Profilo = ?
        ", [$profilo['ID']]);

        // Skill dell'utente
        $skillUtente = $db->fetchAll("
            SELECT sc.Competenza, sc.Livello
            FROM SKILL_CURRICULUM sc
            WHERE sc.Email_Utente = ?
        ", [$userEmail]);

        // Mappa delle skill utente per controllo veloce
        $userSkillsMap = [];
        foreach ($skillUtente as $skill) {
            $userSkillsMap[$skill['Competenza']] = $skill['Livello'];
        }

        // Verifica corrispondenza skill
        $hasAllSkills = true;
        $missingSkills = [];
        $hasSkills = [];

        foreach ($skillRichieste as $required) {
            $competenza = $required['Competenza'];
            $livelloRichiesto = $required['Livello'];

            if (!isset($userSkillsMap[$competenza]) || $userSkillsMap[$competenza] < $livelloRichiesto) {
                $hasAllSkills = false;
                $missingSkills[] = $competenza . " (liv. " . $livelloRichiesto . ")";
            } else {
                $hasSkills[] = $competenza . " (liv. " . $userSkillsMap[$competenza] . "/" . $livelloRichiesto . ")";
            }
        }

        $profilo['canApply'] = $hasAllSkills;
        $profilo['missingSkills'] = $missingSkills;
        $profilo['hasSkills'] = $hasSkills;
        $profilo['requiredSkills'] = $skillRichieste;
    }
    unset($profilo); // ← AGGIUNGI QUESTA RIGA QUI
}

// Recupero commenti
$commenti = $db->fetchAll("
    SELECT c.ID, c.Email_Utente, c.Testo, r.Testo AS Risposta
    FROM COMMENTO c
    LEFT JOIN RISPOSTA r ON r.ID_Commento = c.ID
    WHERE c.Nome_Progetto = ?
", [$nomeProgetto]);

$isCreatore = ($isLoggedIn && isset($_SESSION['email'], $progetto['Email_Creatore']) && $_SESSION['email'] === $progetto['Email_Creatore']);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($progetto['Nome']) ?> - Dettagli Progetto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --card-hover-shadow: 0 20px 40px rgba(0,0,0,0.15);
            --border-radius: 20px;
            --small-radius: 12px;
            --text-primary: #2d3748;
            --text-secondary: #718096;
        }

        * {
            transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fffdfd 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--text-primary);
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

        .project-header {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .project-title {
            font-size: 3.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
            letter-spacing: -1px;
            position: relative;
            z-index: 2;
        }

        .creator-info {
            font-size: 1.2rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin: 1rem 0;
            position: relative;
            z-index: 2;
        }

        .project-details-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .project-details-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }

        .project-image {
            width: 100%;
            height: 450px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        .project-image:hover {
            transform: scale(1.02);
        }

        .stat-box {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-3px);
        }

        .stat-box .value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .stat-box .label {
            font-size: 0.95rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .progress {
            height: 25px;
            border-radius: 15px;
            background: rgba(0,0,0,0.1);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 15px;
            position: relative;
            overflow: hidden;
            font-weight: 600;
        }

        .progress-bar.bg-success {
            background: var(--success-gradient) !important;
        }

        .progress-bar.bg-primary {
            background: var(--primary-gradient) !important;
        }

        .info-row {
            background: rgba(255, 255, 255, 0.5);
            border-radius: var(--small-radius);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .info-row:hover {
            background: rgba(255, 255, 255, 0.8);
            border-left-color: #007bff;
            transform: translateX(5px);
        }

        .comment-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem; /* Adjusted padding for comments card */
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .comment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }

        .comment-item {
            background-color: rgba(248, 249, 250, 0.8); /* Light background for comments */
            border-left: 5px solid #007bff; /* Primary color border */
            border-radius: var(--small-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .comment-item small {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .comment-reply {
            background-color: rgba(232, 246, 237, 0.8); /* Lighter green for replies */
            border-left: 5px solid #28a745; /* Success color border */
            border-radius: var(--small-radius);
            padding: 0.75rem;
            margin-top: 0.75rem;
            margin-left: 1.5rem;
            font-size: 0.9rem;
        }

        .btn-modern {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            color: white;
        }

        .btn-secondary-modern {
            background: transparent;
            border: 2px solid var(--text-secondary);
            color: var(--text-secondary);
        }

        .btn-secondary-modern:hover {
            background: var(--text-secondary);
            color: white;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        .type-badge {
            background: var(--success-gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            position: relative;
            z-index: 2;
        }

        .profile-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            padding: 1.25rem; /* Adjusted padding */
        }

        .profile-card.can-apply {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }

        .profile-card.cannot-apply {
            border-color: #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }

        .skill-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem; /* Slightly larger padding */
            margin: 0.2rem; /* Adjusted margin */
            border-radius: 20px; /* More rounded */
            font-size: 0.8rem; /* Slightly larger font */
            font-weight: 600; /* Bolder font */
        }


        @media (max-width: 768px) {
            .project-title {
                font-size: 2.5rem;
                line-height: 1.2;
            }

            .project-details-card {
                padding: 1.5rem;
            }

            .stat-box .value {
                font-size: 1.8rem;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .skill-has {
            background: #d4edda;
            color: #155724;
        }

        .skill-missing {
            background: #fff3cd;
            color: #856404;
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>

<?php require_once '../../includes/navbar.php'; ?>

<div class="project-header">
    <div class="container text-center">
        <h1 class="project-title"><i class="fas fa-lightbulb me-3"></i><?= htmlspecialchars($progetto['Nome']) ?></h1>
        <div class="creator-info">
            Creato da <strong>
                <?= htmlspecialchars($progetto['CreatoreNickname']) ?>
                (<?= htmlspecialchars($progetto['CreatoreNome'] ?? '') . ' ' . htmlspecialchars($progetto['CreatoreCognome'] ?? '') ?>)
            </strong>
            <span class="badge bg-light text-dark ms-2">
                Affidabilità: <?= number_format($progetto['Affidabilita'] * 100, 1) ?>%
            </span>
        </div>
        <br>
        <span class="type-badge">
            <i class="fas fa-<?= $progetto['Tipo'] === 'Hardware' ? 'microchip' : 'code' ?> me-1"></i>
            <?= htmlspecialchars($progetto['Tipo']) ?>
        </span>
    </div>
</div>

<div class="container">
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="fade-in-up">
                <img src="<?= htmlspecialchars($src) ?>" class="project-image" alt="Immagine Progetto <?= htmlspecialchars($progetto['Nome']) ?>">
            </div>

            <div class="project-details-card fade-in-up">
                <h3 class="mb-3 text-primary"><i class="fas fa-info-circle me-2"></i>Dettagli del Progetto</h3>
                <p class="lead"><?= nl2br(htmlspecialchars($progetto['Descrizione'])) ?></p>

                <hr class="my-4">

                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="value"><?= formatCurrency($progetto['Budget']) ?></div>
                            <div class="label"><i class="fas fa-bullseye me-1"></i>Budget Target</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="value"><?= formatCurrency($progetto['Totale_Finanziato']) ?></div>
                            <div class="label"><i class="fas fa-euro-sign me-1"></i>Raccolto Finora</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="value"><?= $percentuale ?>%</div>
                            <div class="label"><i class="fas fa-percent me-1"></i>Completamento</div>
                        </div>
                    </div>
                </div>

                <div class="progress mt-3">
                    <div class="progress-bar bg-<?= $statoClasse ?>" role="progressbar"
                         style="width: <?= $percentuale ?>%;"
                         aria-valuenow="<?= $percentuale ?>" aria-valuemin="0" aria-valuemax="100">
                        <?= $percentuale ?>%
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="info-row">
                            <i class="fas fa-calendar-alt me-2 text-muted"></i>Data Inserimento: <strong><?= date('d/m/Y', strtotime($progetto['Data_Inserimento'])) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <i class="fas fa-hourglass-end me-2 text-muted"></i>Data Limite: <strong><?= date('d/m/Y', strtotime($progetto['Data_Limite'])) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <i class="fas fa-clock me-2 text-muted"></i>Giorni Rimanenti:
                            <strong class="<?= $progetto['Giorni_Rimanenti'] <= 7 && $progetto['Giorni_Rimanenti'] > 0 ? 'text-warning' : ($progetto['Giorni_Rimanenti'] <= 0 ? 'text-danger' : 'text-success') ?>">
                                <?= $progetto['Giorni_Rimanenti'] > 0 ? $progetto['Giorni_Rimanenti'] : 'Scaduto' ?>
                            </strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <i class="fas fa-users me-2 text-muted"></i>Sostenitori: <strong><?= $progetto['Num_Finanziatori'] ?></strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="info-row">
                            <i class="fas fa-circle me-2 text-muted"></i>Stato Progetto:
                            <span class="badge bg-<?= $statoClasse ?>"><?= ucfirst($progetto['Stato']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($progetto['Tipo'] === 'Hardware' && !empty($componenti)): ?>
                <div class="project-details-card fade-in-up">
                    <h3 class="mb-3 text-warning">
                        <i class="fas fa-microchip me-2"></i>Componenti Hardware Necessari
                    </h3>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-warning">
                            <tr>
                                <th><i class="fas fa-cube me-1"></i>Componente</th>
                                <th><i class="fas fa-info-circle me-1"></i>Descrizione</th>
                                <th><i class="fas fa-euro-sign me-1"></i>Prezzo Unitario</th>
                                <th><i class="fas fa-boxes me-1"></i>Quantità</th>
                                <th><i class="fas fa-calculator me-1"></i>Totale</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($componenti as $componente): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($componente['Nome']) ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($componente['Descrizione']) ?></small>
                                    </td>
                                    <td>
                                        <span class="text-success fw-bold"><?= formatCurrency($componente['Prezzo']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($componente['Quantita']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-primary fw-bold"><?= formatCurrency($componente['Totale']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end">
                                    <strong>Totale Componenti:</strong>
                                </td>
                                <td>
                                    <span class="text-primary fw-bold fs-5"><?= formatCurrency($totaleComponenti) ?></span>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php elseif ($progetto['Tipo'] === 'Hardware' && empty($componenti)): ?>
                <div class="project-details-card fade-in-up">
                    <h3 class="mb-3 text-warning">
                        <i class="fas fa-microchip me-2"></i>Componenti Hardware
                    </h3>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted">
                            Questo progetto hardware non ha ancora specificato i componenti necessari.
                            <br>
                            Il creatore aggiungerà presto la lista dettagliata dei componenti hardware.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="projects.php" class="btn btn-secondary-modern btn-lg"><i class="fas fa-arrow-left me-2"></i>Torna ai Progetti</a>
                <?php if ($isLoggedIn && $progetto['Stato'] === 'aperto' && $progetto['Giorni_Rimanenti'] > 0 && $percentuale < 100): ?>
                    <button class="btn btn-modern btn-lg ms-3" data-bs-toggle="modal" data-bs-target="#finanziaModal">
                        <i class="fas fa-hand-holding-usd me-2"></i>Finanzia questo Progetto
                    </button>
                <?php elseif ($isLoggedIn && $progetto['Stato'] === 'aperto' && $percentuale >= 100): ?>
                    <button class="btn btn-success btn-lg ms-3" disabled>
                        <i class="fas fa-check-circle me-2"></i>Obiettivo Raggiunto!
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($isLoggedIn): ?>
                <div class="comment-card my-4 fade-in-up">
                    <h3 class="mb-3 text-primary"><i class="fas fa-comments me-2"></i>Commenti</h3>
                    <?php if (count($commenti) > 0): ?>
                        <div class="mb-3">
                            <?php foreach ($commenti as $commento): ?>
                                <div class="comment-item">
                                    <small class="d-block mb-1">
                                        <strong>
                                            <?php if ($commento['Email_Utente'] === $progetto['Email_Creatore']): ?>
                                                <i class="fas fa-crown text-warning me-1"></i>Creatore:
                                            <?php else: ?>
                                                <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($commento['Email_Utente']) ?>:
                                            <?php endif; ?>
                                        </strong>
                                    </small>
                                    <p class="mb-1"><?= htmlspecialchars($commento['Testo']) ?></p>

                                    <?php if (!empty($commento['Risposta'])): ?>
                                        <div class="comment-reply">
                                            <small class="d-block mb-1">
                                                <strong><i class="fas fa-reply me-1"></i>Risposta del creatore:</strong>
                                            </small>
                                            <p class="mb-0"><?= htmlspecialchars($commento['Risposta']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-comment-dots fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Nessun commento presente per questo progetto.</p>
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <form action="/Bostarter/api/manage_comment.php" method="POST">
                        <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">
                        <div class="mb-3">
                            <label for="commentText" class="form-label">Lascia un commento:</label>
                            <textarea class="form-control" id="commentText" name="testo_commento" rows="3" placeholder="Scrivi qui il tuo commento..." required maxlength="500"></textarea>
                            <div class="form-text text-muted">Massimo 500 caratteri.</div>
                        </div>
                        <button type="submit" class="btn btn-modern"><i class="fas fa-comment me-2"></i>Invia Commento</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info my-4 text-center">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Accedi per lasciare un commento.
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && $progetto['Tipo'] === 'Software' && $progetto['Stato'] === 'aperto'): ?>
                <div class="project-details-card my-4 fade-in-up">
                    <h3 class="mb-3 text-primary">
                        <i class="fas fa-user-tie me-2"></i>Candidati per un ruolo
                    </h3>

                    <div class="card-body px-0">
                        <?php if (isset($_GET['success']) && $_GET['success'] === 'candidatura_inviata'): ?>
                            <div class="alert alert-success" id="alertBox">Candidatura inviata con successo!</div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error']) && strpos($_GET['error'], 'candidatura_') === 0): ?>
                            <div class="alert alert-danger" id="alertBox">Errore: <?= htmlspecialchars(str_replace('_', ' ', $_GET['error'])) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($profiliRicercati)): ?>
                            <p class="mb-3">Questo progetto software sta ricercando i seguenti profili:</p>

                            <?php foreach ($profiliRicercati as $profilo): ?>
                                <div class="profile-card <?= $profilo['canApply'] ? 'can-apply' : 'cannot-apply' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0">
                                            <i class="fas fa-<?= $profilo['canApply'] ? 'check-circle text-success' : 'exclamation-triangle text-warning' ?> me-2"></i>
                                            <?= htmlspecialchars($profilo['Nome']) ?>
                                        </h6>
                                        <?php if ($profilo['canApply']): ?>
                                            <span class="badge bg-success">Puoi candidarti</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Skill mancanti</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($profilo['requiredSkills'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted d-block mb-1">Skill richieste:</small>
                                            <?php foreach ($profilo['requiredSkills'] as $skill): ?>
                                                <?php
                                                $hasSkill = false;
                                                foreach ($profilo['hasSkills'] as $userSkill) {
                                                    if (strpos($userSkill, $skill['Competenza']) === 0) {
                                                        $hasSkill = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <span class="skill-tag <?= $hasSkill ? 'skill-has' : 'skill-missing' ?>">
                                                <?= htmlspecialchars($skill['Competenza']) ?> (Liv. <?= $skill['Livello'] ?>)
                                                <?= $hasSkill ? '✓' : '✗' ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$profilo['canApply'] && !empty($profilo['missingSkills'])): ?>
                                        <div class="mt-2">
                                            <small class="text-danger">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Ti mancano: <?= implode(', ', $profilo['missingSkills']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <form id="candidaturaForm" class="mt-3">
                                <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">

                                <div class="mb-3">
                                    <label for="profiloCandidatura" class="form-label">Seleziona il profilo per cui candidarti:</label>
                                    <select class="form-select" id="profiloCandidatura" name="profilo" required>
                                        <option value="">Seleziona un profilo...</option>
                                        <?php foreach ($profiliRicercati as $profilo): ?>
                                            <option value="<?= htmlspecialchars($profilo['ID']) ?>"
                                                <?= !$profilo['canApply'] ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($profilo['Nome']) ?>
                                                <?= !$profilo['canApply'] ? ' (skill insufficienti)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php
                                $hasEligibleProfile = false;
                                foreach ($profiliRicercati as $profilo) {
                                    if ($profilo['canApply']) {
                                        $hasEligibleProfile = true;
                                        break;
                                    }
                                }
                                $utenteEmail = $_SESSION['user_email'] ?? null;
                                $creatoreEmail = $progetto['Email_Creatore'];
                                ?>

                                <?php if ($utenteEmail === $creatoreEmail): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-user-shield me-2"></i>
                                        <strong>Sei il creatore di questo progetto.</strong><br>
                                        Non puoi candidarti al tuo stesso progetto.
                                    </div>
                                <?php elseif ($hasEligibleProfile): ?>
                                    <button type="button" class="btn btn-modern" onclick="inviaCandidatura()">
                                        <i class="fas fa-user-plus me-2"></i>Invia Candidatura
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Non puoi candidarti per nessun profilo.</strong><br>
                                        <small>Aggiungi le skill mancanti nel tuo
                                            <a href="/Bostarter/public/dashboard/user_dashboard.php" class="alert-link">profilo utente</a>
                                            per poter inviare candidature.
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </form>

                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Questo progetto software non ha profili specifici ricercati al momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($progetto['Tipo'] === 'Software' && $progetto['Stato'] !== 'aperto'): ?>
                <div class="alert alert-info my-4 text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Questo progetto software non accetta più candidature (Stato: <?= htmlspecialchars($progetto['Stato']) ?>).
                </div>
            <?php elseif ($progetto['Tipo'] === 'Software' && !$isLoggedIn): ?>
                <div class="alert alert-info my-4 text-center">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Accedi per candidarti a questo progetto software.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="finanziaModal" tabindex="-1" aria-labelledby="finanziaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="finanziaModalLabel">
                    <i class="fas fa-hand-holding-usd me-2"></i>
                    Finanzia <?= htmlspecialchars($progetto['Nome']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($rewards)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Seleziona una reward</strong> per ricevere un ringraziamento speciale dal creatore.
                    </div>

                    <form action="../../api/fund_project.php" method="POST" id="quickFundingForm">
                        <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">

                        <div class="mb-4">
                            <label class="form-label">
                                <strong><i class="fas fa-gift me-2"></i>Reward disponibili</strong>
                                <span class="badge bg-danger ms-2">Obbligatorio</span>
                            </label>

                            <?php foreach ($rewards as $index => $reward): ?>
                                <?php
                                $fotoReward = $reward['Foto'] ?? '';
                                if ($fotoReward !== '') {
                                    $src = (strpos($fotoReward, 'img/') === 0)
                                        ? "/Bostarter/{$fotoReward}"
                                        : "/Bostarter/img/{$fotoReward}";
                                } else {
                                    $src = null;
                                }
                                ?>
                                <div class="form-check border rounded p-3 mb-2" style="cursor: pointer;">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="codice_reward"
                                           id="modal_reward_<?= $index ?>"
                                           value="<?= htmlspecialchars($reward['Codice']) ?>"
                                           required>
                                    <label class="form-check-label w-100" for="modal_reward_<?= $index ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($reward['Codice']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($reward['Descrizione']) ?></small>
                                            </div>
                                            <?php if ($src): ?>
                                                <img src="<?= htmlspecialchars($src) ?>" alt="Foto Reward" style="max-width:60px;max-height:60px;border-radius:8px;">
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3">
                            <label for="modal_importo" class="form-label">
                                <strong><i class="fas fa-euro-sign me-2"></i>Importo da finanziare (€)</strong>
                            </label>
                            <input type="number" class="form-control form-control-lg"
                                   id="modal_importo" name="importo"
                                   min="1" step="0.01" required
                                   placeholder="Es: 25.00">

                            <div class="mt-2 d-flex justify-content-around">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setModalAmount(10)">€10</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setModalAmount(25)">€25</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setModalAmount(50)">€50</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setModalAmount(100)">€100</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setModalAmount(250)">€250</button>
                            </div>
                        </div>

                        <div class="card bg-light mb-3" id="modalSummary" style="display: none;">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-clipboard-list me-2"></i>Riepilogo
                                </h6>
                                <p class="mb-1"><strong>Reward:</strong> <span id="selectedRewardName">-</span></p>
                                <p class="mb-0"><strong>Importo:</strong> <span id="selectedAmountDisplay">€0.00</span></p>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="modalSubmitBtn" disabled>
                                <i class="fas fa-paper-plane me-2"></i>Conferma Finanziamento
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                        <h5>Progetto Non Ancora Finanziabile</h5>
                        <p class="mb-3">
                            Questo progetto non ha ancora definito le reward per i sostenitori.
                            <br>
                            Il creatore deve aggiungere almeno una reward prima che il progetto possa ricevere finanziamenti.
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Torna più tardi per vedere se sono state aggiunte delle reward!
                        </small>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($rewards)): ?>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Chiudi
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toastNotifica" class="toast" role="alert">
        <div class="toast-header">
            <strong class="me-auto">BOSTARTER</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Gestione modal finanziamento
    function setModalAmount(amount) {
        const importoInput = document.getElementById('modal_importo');
        if (importoInput) {
            importoInput.value = amount;
            document.getElementById('selectedAmountDisplay').textContent = `€${amount}.00`;
            checkModalFormCompletion();
        }
    }

    function checkModalFormCompletion() {
        const selectedReward = document.querySelector('input[name="codice_reward"]:checked');
        const importo = parseFloat(document.getElementById('modal_importo')?.value || 0);
        const isComplete = selectedReward && importo > 0;

        const submitBtn = document.getElementById('modalSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = !isComplete;
        }

        const summary = document.getElementById('modalSummary');
        if (summary) {
            summary.style.display = isComplete ? 'block' : 'none';
        }

        if (selectedReward) {
            const rewardCode = selectedReward.value;
            const rewardDesc = selectedReward.closest('.form-check').querySelector('.text-muted').textContent;
            document.getElementById('selectedRewardName').textContent = `${rewardCode} - ${rewardDesc}`;
        }
    }

    // Gestione candidature
    function mostraToast(message, type = 'info') {
        const toast = document.getElementById('toastNotifica');
        const toastMessage = document.getElementById('toastMessage');

        if (!toast || !toastMessage) {
            if (type === 'error') {
                alert(`errore ${message}`);
            } else {
                alert(`ok ${message}`);
            }
            return;
        }

        toastMessage.textContent = message;

        const colorClasses = {
            'success': 'bg-success text-white',
            'error': 'bg-danger text-white',
            'warning': 'bg-warning text-dark',
            'info': 'bg-info text-white'
        };

        toast.className = `toast ${colorClasses[type] || colorClasses.info}`;

        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: type === 'error' ? 5000 : 3000
        });
        bsToast.show();
    }

    function inviaCandidatura() {
        const form = document.getElementById('candidaturaForm');
        const profiloSelect = document.getElementById('profiloCandidatura');
        const submitBtn = form.querySelector('button[type="button"]');

        if (!profiloSelect.value) {
            mostraToast('Seleziona un profilo per candidarti', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Invio in corso...';

        const formData = new FormData();
        formData.append('action', 'submit_candidatura');
        formData.append('profilo', profiloSelect.value);
        formData.append('nome_progetto', form.querySelector('input[name="nome_progetto"]').value);

        fetch('/Bostarter/api/manage_candidature.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    mostraToast(data.message, 'success');
                    form.reset();
                    setTimeout(() => {
                        window.location.href = '/Bostarter/public/dashboard/user_dashboard.php';
                    }, 2000);
                } else {
                    mostraToast(data.message || 'Errore durante l\'invio della candidatura', 'error');
                    riabilitaBottoneCandidatura(submitBtn);
                }
            })
            .catch(error => {
                mostraToast(`Errore di connessione: ${error.message}`, 'error');
                riabilitaBottoneCandidatura(submitBtn);
            });
    }


    function riabilitaBottoneCandidatura(btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Invia Candidatura';
    }

    // Inizializzazione
    document.addEventListener('DOMContentLoaded', function() {
        // Gestione modal finanziamento
        document.querySelectorAll('input[name="codice_reward"]').forEach(radio => {
            radio.addEventListener('change', checkModalFormCompletion);
        });

        const modalImporto = document.getElementById('modal_importo');
        if (modalImporto) {
            modalImporto.addEventListener('input', function() {
                const amount = parseFloat(this.value) || 0;
                document.getElementById('selectedAmountDisplay').textContent = `€${amount.toFixed(2)}`;
                checkModalFormCompletion();
            });
        }

        const quickForm = document.getElementById('quickFundingForm');
        if (quickForm) {
            quickForm.addEventListener('submit', function(e) {
                const selectedReward = document.querySelector('input[name="codice_reward"]:checked');
                const importo = parseFloat(document.getElementById('modal_importo').value || 0);

                if (!selectedReward) {
                    e.preventDefault();
                    alert('Devi selezionare una reward per continuare!');
                    return false;
                }

                if (importo <= 0) {
                    e.preventDefault();
                    alert('Inserisci un importo valido maggiore di zero!');
                    return false;
                }

                const submitBtn = document.getElementById('modalSubmitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione...';

                return true;
            });
        }

        const modal = document.getElementById('finanziaModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function() {
                const form = document.getElementById('quickFundingForm');
                if (form) {
                    form.reset();
                    checkModalFormCompletion();
                }
            });
        }

        // allert
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success') || urlParams.has('error')) {
            const alertBox = document.getElementById('alertBox');
            if (alertBox) {
                setTimeout(() => {
                    alertBox.style.display = 'none';
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.delete('success');
                    newUrl.searchParams.delete('error');
                    window.history.replaceState({}, document.title, newUrl.toString());
                }, 3000);
            }
        }
    });

    // Apri automaticamente il modal se arriva dalla pagina progetti
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'fund') {
            const fundModal = document.getElementById('finanziaModal');
            if (fundModal) {
                const modal = new bootstrap.Modal(fundModal);
                modal.show();
            }
        }
    });

</script>

</body>
</html>