<?php
require_once '../config/database.php';

// Attiva errori in fase di sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function formatCurrency($amount) {
    return '€ ' . number_format($amount, 2, ',', '.');
}

// Verifica se l'utente è loggato per la navbar dinamica
session_start();
$isLoggedIn = isset($_SESSION['user_email']);
$userEmail = $_SESSION['user_email'] ?? null;

$isAdmin = SessionManager::isAdmin();
$isCreator = SessionManager::isCreator();

// Controllo parametro
if (!isset($_GET['name']) || empty($_GET['name'])) {
    die("<h3>Errore: nome del progetto non specificato.</h3>");
}

$nomeProgetto = $_GET['name'];
$db = Database::getInstance();

// Ottieni dettagli progetto
$sql = "
    SELECT
        p.Nome,
        p.Descrizione,
        p.Data_Inserimento,
        p.Stato,
        p.Budget,
        p.Data_Limite,
        p.Tipo,
        u.Nickname AS CreatoreNickname,
        u.Nome AS CreatoreNome,
        u.Cognome AS CreatoreCognome,
        c.Affidabilita,
        COALESCE(SUM(f.Importo), 0) AS Totale_Finanziato,
        COUNT(DISTINCT f.Email_Utente) AS Num_Finanziatori,
        (SELECT percorso FROM FOTO WHERE Nome_Progetto = p.Nome LIMIT 1) AS Foto,
        DATEDIFF(p.Data_Limite, CURDATE()) AS Giorni_Rimanenti
    FROM PROGETTO p
    JOIN CREATORE c ON p.Email_Creatore = c.Email
    JOIN UTENTE u ON c.Email = u.Email
    LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
    LEFT JOIN FOTO foto ON p.Nome = foto.Nome_Progetto
    WHERE p.Nome = ?
    GROUP BY p.Nome, p.Descrizione, p.Data_Inserimento, p.Stato, p.Budget, p.Data_Limite, p.Tipo, CreatoreNickname, CreatoreNome, CreatoreCognome, c.Affidabilita, foto.percorso
";

$progetto = $db->fetchOne($sql, [$nomeProgetto]);
if (!$progetto) {
    die("<h3>Progetto non trovato.</h3>");
}

// Calcoli
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

// Percorso immagine
$fotoDb = $progetto['Foto'] ?? '';
if ($fotoDb !== '') {
    $src = (strpos($fotoDb, 'img/') === 0)
        ? "/Bostarter/{$fotoDb}"
        : "/Bostarter/img/{$fotoDb}";
} else {
    $src = null;
}

// Ottieni le rewards disponibili per questo progetto
$rewards = $db->fetchAll("SELECT Codice, Descrizione FROM REWARD WHERE Nome_Progetto = ? ORDER BY Codice ASC", [$nomeProgetto]);

$profiliRicercati = $db->fetchAll(
    "SELECT ID, Nome FROM PROFILO WHERE Nome_Progetto = ?",
    [$nomeProgetto]
);

// NUOVA SEZIONE: Verifica skill per candidature
if ($isLoggedIn && $progetto['Tipo'] === 'Software' && $progetto['Stato'] === 'aperto' && !empty($profiliRicercati)) {
    // Per ogni profilo, verifica se l'utente ha le skill necessarie
    foreach ($profiliRicercati as &$profilo) {
        // Ottieni skill richieste per questo profilo
        $skillRichieste = $db->fetchAll("
            SELECT sr.Competenza, sr.Livello 
            FROM SKILL_RICHIESTA sr 
            WHERE sr.ID_Profilo = ?
        ", [$profilo['ID']]);

        // Ottieni skill dell'utente
        $skillUtente = $db->fetchAll("
            SELECT sc.Competenza, sc.Livello 
            FROM SKILL_CURRICULUM sc 
            WHERE sc.Email_Utente = ?
        ", [$userEmail]);

        // Crea array associativo per controllo veloce
        $userSkillsMap = [];
        foreach ($skillUtente as $skill) {
            $userSkillsMap[$skill['Competenza']] = $skill['Livello'];
        }

        // Verifica se ha tutte le skill richieste
        $hasAllSkills = true;
        $missingSkills = [];
        $hasSkills = [];

        foreach ($skillRichieste as $required) {
            $competenza = $required['Competenza'];
            $livelloRichiesto = $required['Livello'];

            if (!isset($userSkillsMap[$competenza]) ||
                $userSkillsMap[$competenza] < $livelloRichiesto) {
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
}

$commenti = $db->fetchAll("
    SELECT c.ID, c.Email_Utente, c.Testo, r.Testo AS Risposta
    FROM COMMENTO c
    LEFT JOIN RISPOSTA r ON r.ID_Commento = c.ID
    WHERE c.Nome_Progetto = ?
    ",[$nomeProgetto]);

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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }

        /* Navbar migliorata */
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

        /* Header del progetto */
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

        /* Cards moderne */
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

        /* Immagine del progetto */
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

        /* Statistiche moderne */
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

        /* Progress bar migliorata */
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

        /* Info styling migliorato */
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

        /* Pulsanti moderni */
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

        /* Modal migliorato */
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

        /* Badge type migliorato */
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

        /* Stili per profili candidatura */
        .profile-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
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
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .skill-has {
            background: #d4edda;
            color: #155724;
        }

        .skill-missing {
            background: #fff3cd;
            color: #856404;
        }

        /* Responsive */
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

        /* Animazioni di entrata */
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

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../projects.php">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/Bostarter/public/projects.php">
                        <i class="fas fa-project-diagram me-1"></i>Progetti
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['user_nickname'] ?? 'Utente') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="/Bostarter/public/dashboard/admin_dashboard.php"><i class="fas fa-shield-alt me-2"></i>Dashboard Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php elseif ($isCreator): ?>
                                <li><a class="dropdown-item" href="/Bostarter/public/dashboard/creator_dashboard.php"><i class="fas fa-user-cog me-2"></i>Dashboard Creatore</a></li>
                                <li><a class="dropdown-item" href="/Bostarter/public/dashboard/new_project.php"><i class="fas fa-plus me-2"></i>Crea Progetto</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="/Bostarter/public/dashboard/user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                            <li><a class="dropdown-item" href="/Bostarter/public/statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
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

<div class="project-header">
    <div class="container text-center">
        <h1 class="project-title"><i class="fas fa-lightbulb me-3"></i><?= htmlspecialchars($progetto['Nome']) ?></h1>
        <div class="creator-info">
            Creato da
            <strong>
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
                <div class="card my-4">
                    <div class="card-header bg-light">
                        <h5>Lascia un commento</h5>
                        <?php if (count($commenti) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($commenti as $commento): ?>
                                    <div class="mb-3">
                                        <strong><?= htmlspecialchars($commento['Email_Utente']) ?>:</strong><br>
                                        <?= htmlspecialchars($commento['Testo']) ?>

                                        <?php if (!empty($commento['Risposta'])): ?>
                                            <div class="mt-1 ms-3 p-2 bg-light border-start border-3 border-success">
                                                <strong>Risposta del creatore:</strong><br>
                                                <?= htmlspecialchars($commento['Risposta']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                            </ul>
                        <?php else: ?>
                            <p>Nessun commento presente per questo progetto.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form action="/Bostarter/public/manage_comment.php" method="POST">
                            <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">
                            <div class="mb-3">
                                <label for="commentText" class="form-label visually-hidden">Il tuo commento</label>
                                <textarea class="form-control" id="commentText" name="testo_commento" rows="3" placeholder="Scrivi qui il tuo commento..." required maxlength="500"></textarea>
                                <div class="form-text text-muted">Massimo 500 caratteri.</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-comment me-2"></i>Invia Commento</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info my-4">
                    Accedi per lasciare un commento.
                </div>
            <?php endif; ?>
                <!-- Dopo visualizzazione commento -->
            <?php if ($isLoggedIn && $isCreatore): ?>
                <form action="/Bostarter/public/manage_comment.php" method="POST" class="mt-2">
                    <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">
                    <textarea name="testo_risposta" class="form-control" placeholder="Rispondi al commento..." required></textarea>
                    <button type="submit" class="btn btn-sm btn-outline-primary mt-1">Rispondi</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($commento['Risposta'])): ?>
                <div class="mt-1 ms-3 p-2 bg-light border-start border-3">
                    <strong>Risposta del creatore:</strong><br>
                    <?= htmlspecialchars($commento['Risposta']) ?>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && $progetto['Tipo'] === 'Software' && $progetto['Stato'] === 'aperto'): ?>
                <div class="card my-4">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-user-tie me-2"></i>Candidati per un ruolo in questo progetto</h5>
                    </div>

                    <div class="card-body">
                        <?php if (isset($_GET['success']) && $_GET['success'] === 'candidatura_inviata'): ?>
                            <div class="alert alert-success" id="alertBox">✅ Candidatura inviata con successo!</div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger" id="alertBox">❌ Errore: <?= htmlspecialchars($_GET['error']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($profiliRicercati)): ?>
                            <p class="mb-3">Questo progetto software sta ricercando i seguenti profili:</p>

                            <!-- Mostra dettagli profili con verifica skill -->
                            <?php foreach ($profiliRicercati as $profilo): ?>
                                <div class="profile-card <?= $profilo['canApply'] ? 'can-apply' : 'cannot-apply' ?> p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0">
                                            <i class="fas fa-<?= $profilo['canApply'] ? 'check-circle text-success' : 'exclamation-triangle text-warning' ?> me-2"></i>
                                            <?= htmlspecialchars($profilo['Nome']) ?>
                                        </h6>
                                        <?php if ($profilo['canApply']): ?>
                                            <span class="badge bg-success">✓ Puoi candidarti</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">⚠ Skill mancanti</span>
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

                            <!-- Form candidatura -->
                            <form action="/Bostarter/public/manage_candidature.php" method="POST" class="mt-3">
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
                                ?>

                                <?php if ($hasEligibleProfile): ?>
                                    <button type="submit" class="btn btn-success">
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
                <div class="alert alert-info my-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Questo progetto software non accetta più candidature (Stato: <?= htmlspecialchars($progetto['Stato']) ?>).
                </div>
            <?php elseif ($progetto['Tipo'] === 'Software' && !$isLoggedIn): ?>
                <div class="alert alert-info my-4">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Accedi per candidarti a questo progetto software.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="finanziaModal" tabindex="-1" aria-labelledby="finanziaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="finanziaModalLabel">Finanzia <?= htmlspecialchars($progetto['Nome']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="/Bostarter/public/fund_project.php" method="POST">
                    <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">
                    <div class="mb-3">
                        <label for="importo" class="form-label">Importo da finanziare (€)</label>
                        <input type="number" class="form-control" id="importo" name="importo" min="1" required>
                        <div class="mt-2 d-flex justify-content-around">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('importo').value = 10">€10</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('importo').value = 25">€25</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('importo').value = 50">€50</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('importo').value = 100">€100</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('importo').value = 250">€250</button>
                        </div>
                    </div>

                    <?php if (!empty($rewards)): ?>
                        <div class="mb-3">
                            <label for="codice_reward" class="form-label">Scegli una ricompensa (opzionale)</label>
                            <select class="form-select" id="codice_reward" name="codice_reward">
                                <option value="">Nessuna ricompensa</option>
                                <?php foreach ($rewards as $reward): ?>
                                    <option value="<?= htmlspecialchars($reward['Codice']) ?>">
                                        <?= htmlspecialchars($reward['Descrizione']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <!-- Nessuna reward disponibile -->
                        <input type="hidden" name="codice_reward" value="">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Questo progetto non ha ancora reward disponibili.
                        </div>
                    <?php endif; ?>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-modern btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Conferma Finanziamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 3000);
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>