<?php
/**
 * BOSTARTER - Dashboard Creatore - VERSIONE CORRETTA
 * File: public/dashboard/creator_dashboard.php
 */

require_once '../../config/database.php';

// Verifica login e che sia creatore
SessionManager::requireLogin('../../index.html');
SessionManager::requireCreator();

$userEmail = SessionManager::getUserEmail();
$userName = SessionManager::get('user_nome') . ' ' . SessionManager::get('user_cognome');
$userNickname = SessionManager::get('user_nickname');
$isAdmin = SessionManager::isAdmin();

try {
    $db = Database::getInstance();

    // Statistiche creatore
    $stats = $db->fetchOne("
        SELECT c.Nr_Progetti, c.Affidabilita,
               COALESCE(SUM(f.Importo), 0) as Totale_Raccolto,
               COUNT(DISTINCT f.Email_Utente) as Sostenitori_Unici
        FROM CREATORE c
        LEFT JOIN PROGETTO p ON c.Email = p.Email_Creatore
        LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
        WHERE c.Email = ?
        GROUP BY c.Nr_Progetti, c.Affidabilita
    ", [$userEmail]);

    // I miei progetti
    $mieiProgetti = $db->fetchAll("
        SELECT p.*, 
               COALESCE(SUM(f.Importo), 0) as Totale_Raccolto,
               COUNT(DISTINCT f.Email_Utente) as Num_Sostenitori,
               COUNT(DISTINCT c.ID) as Num_Commenti,
               DATEDIFF(p.Data_Limite, CURDATE()) as Giorni_Rimanenti,
               p.Tipo as Categoria
        FROM PROGETTO p
        LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
        LEFT JOIN COMMENTO c ON p.Nome = c.Nome_Progetto
        WHERE p.Email_Creatore = ?
        GROUP BY p.Nome
        ORDER BY p.Data_Inserimento DESC
    ", [$userEmail]);

    // AGGIORNAMENTO AUTOMATICO STATO PROGETTI SCADUTI DEL CREATORE
    $db->execute("
        UPDATE PROGETTO 
        SET Stato = 'chiuso' 
        WHERE Email_Creatore = ? AND Stato = 'aperto' AND Data_Limite <= CURDATE()
    ", [$userEmail]);

    // Aggiorna i dati locali se necessario
    foreach ($mieiProgetti as &$progetto) {
        if ($progetto['Stato'] === 'aperto' && $progetto['Data_Limite'] <= date('Y-m-d')) {
            $progetto['Stato'] = 'chiuso';
        }
    }

    // Gestione eliminazione progetto
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina_progetto'])) {
        $nome = $_POST['elimina_progetto'];
        $email = $_SESSION['user_email'];

        try {
            // Elimina prima eventuali dipendenze
            $db->execute("DELETE FROM FOTO WHERE Nome_Progetto = ?", [$nome]);
            // Aggiungi anche altri DELETE se servono: commenti, reward, candidature...

            // Poi elimina il progetto solo se appartiene a chi è loggato
            $db->execute("DELETE FROM PROGETTO WHERE Nome = ? AND Email_Creatore = ?", [$nome, $email]);

            // Redirect per aggiornare la lista senza ripetere il POST
            header("Location: creator_dashboard.php");
            exit;
        } catch (Exception $e) {
            echo "<p>❌ Errore nell'eliminazione: " . $e->getMessage() . "</p>";
        }
    }

    // Candidature ricevute
    $candidatureRicevute = $db->fetchAll("
        SELECT c.ID, c.Data_Candidatura, c.Esito, u.Nickname, u.Nome, u.Cognome,
            pr.Nome as Nome_Profilo, p.Nome as Nome_Progetto
        FROM CANDIDATURA c
        JOIN UTENTE u ON c.Email_Utente = u.Email
        JOIN PROFILO pr ON c.ID_Profilo = pr.ID
        JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
        WHERE p.Email_Creatore = ?
        ORDER BY 
            CASE 
                WHEN c.Esito IS NULL THEN 0  -- Candidature in attesa vengono prima
                WHEN c.Esito = 1 THEN 1      -- Candidature accettate
                ELSE 2                       -- Candidature rifiutate per ultime
            END,
            c.Data_Candidatura DESC
        LIMIT 20
    ", [$userEmail]);

    // Debug candidature
    error_log("🔍 Debug candidature per $userEmail:");
    foreach ($candidatureRicevute as $cand) {
        error_log("- ID: {$cand['ID']}, Esito: " . var_export($cand['Esito'], true) . ", Nickname: {$cand['Nickname']}");
    }

    // Commenti ricevuti (query corretta)
    $commentiRicevuti = $db->fetchAll("
        SELECT c.ID, c.Testo, c.Email_Utente, c.Nome_Progetto, r.Testo AS Risposta
        FROM COMMENTO c
        LEFT JOIN RISPOSTA r ON r.ID_Commento = c.ID
        JOIN PROGETTO p ON c.Nome_Progetto = p.Nome
        WHERE p.Email_Creatore = ?
        ORDER BY c.Data DESC
        LIMIT 10
    ", [$userEmail]);

    // Gestione risposta ai commenti
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_commento'], $_POST['testo_risposta'])) {
        $id = intval($_POST['id_commento']);
        $risposta = trim($_POST['testo_risposta']);
        $emailCreatore = SessionManager::getUserEmail();

        try {
            $db->callStoredProcedure('InserisciRisposta', [$id, $emailCreatore, $risposta]);
            header("Location: creator_dashboard.php");
            exit;
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Errore: " . $e->getMessage() . "</div>";
        }
    }

} catch (Exception $e) {
    error_log("Errore dashboard creatore: " . $e->getMessage());
    $error = "Errore nel caricamento dei dati. Riprova più tardi.";
}

// Funzione helper per formattare valuta
function formatCurrency($amount) {
    return '€ ' . number_format($amount, 2, ',', '.');
}

// Funzione per calcolare percentuale completamento
function getCompletionPercentage($raccolto, $budget) {
    if ($budget <= 0) return 0;
    return min(100, ($raccolto / $budget) * 100);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Creatore - BOSTARTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
        .creator-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        .creator-stat-card:hover {
            transform: translateY(-5px);
        }
        .project-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .project-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .project-hardware {
            border-left-color: #28a745;
        }
        .project-software {
            border-left-color: #6f42c1;
        }
        .candidatura-pending {
            border-left: 4px solid #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }
        .candidatura-accepted {
            border-left: 4px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
        }
        .candidatura-rejected {
            border-left: 4px solid #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }
        .affidabilita-badge {
            font-size: 1.2rem;
            padding: 8px 16px;
        }
        .quick-action-card {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            transition: transform 0.3s ease;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            color: white;
            text-decoration: none;
        }
        .comment-card {
            border-left: 4px solid #17a2b8;
        }
        .progress-thin {
            height: 4px;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../projects/projects.php">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../projects/projects.php">
                        <i class="fas fa-project-diagram me-1"></i>Progetti
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($userNickname) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($isAdmin): ?>
                            <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-shield-alt me-2"></i>Dashboard Admin</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="creator_dashboard.php"><i class="fas fa-user-cog me-2"></i>Dashboard Creatore</a></li>
                            <li><a class="dropdown-item" href="new_project.php"><i class="fas fa-plus me-2"></i>Crea Progetto</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                        <li><a class="dropdown-item" href="../statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 text-primary">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard Creatore
            </h1>
            <p class="text-muted">Benvenuto, <?= htmlspecialchars($userName) ?>! Gestisci i tuoi progetti e monitora le performance.</p>
        </div>
    </div>

    <!-- Messaggi di errore -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Statistiche principali -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card creator-stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-project-diagram fa-2x mb-2"></i>
                    <h3 class="card-title"><?= $stats['Nr_Progetti'] ?? 0 ?></h3>
                    <p class="card-text">Progetti Creati</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card creator-stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-euro-sign fa-2x mb-2"></i>
                    <h3 class="card-title"><?= formatCurrency($stats['Totale_Raccolto'] ?? 0) ?></h3>
                    <p class="card-text">Totale Raccolto</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card creator-stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h3 class="card-title"><?= $stats['Sostenitori_Unici'] ?? 0 ?></h3>
                    <p class="card-text">Sostenitori</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card creator-stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-star fa-2x mb-2"></i>
                    <span class="badge bg-light text-dark affidabilita-badge">
                        <?= number_format(($stats['Affidabilita'] ?? 0) * 100, 1) ?>%
                    </span>
                    <p class="card-text mt-2">Affidabilità</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Azioni rapide -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <a href="new_project.php" class="card quick-action-card text-decoration-none">
                <div class="card-body text-center">
                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                    <h5 class="card-title">Nuovo Progetto</h5>
                    <p class="card-text">Crea un nuovo progetto hardware o software</p>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="user_dashboard.php" class="card quick-action-card text-decoration-none">
                <div class="card-body text-center">
                    <i class="fas fa-cogs fa-2x mb-2"></i>
                    <h5 class="card-title">Gestisci Skill</h5>
                    <p class="card-text">Aggiorna le tue competenze</p>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="../projects/projects.php" class="card quick-action-card text-decoration-none">
                <div class="card-body text-center">
                    <i class="fas fa-search fa-2x mb-2"></i>
                    <h5 class="card-title">Esplora Progetti</h5>
                    <p class="card-text">Scopri e finanzia altri progetti</p>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <!-- I miei progetti -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-project-diagram me-2"></i>I Miei Progetti
                    </h5>
                    <a href="new_project.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Nuovo
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($mieiProgetti)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nessun progetto ancora</h5>
                            <p class="text-muted">Crea il tuo primo progetto per iniziare!</p>
                            <a href="new_project.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Crea Progetto
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($mieiProgetti as $progetto): ?>
                            <?php
                            $percentuale = getCompletionPercentage($progetto['Totale_Raccolto'], $progetto['Budget']);
                            $classeProgetto = $progetto['Tipo'] === 'Hardware' ? 'project-hardware' : 'project-software';
                            $statoClasse = $progetto['Stato'] === 'aperto' ? 'success' : 'secondary';
                            ?>

                            <div class="card project-card <?= $classeProgetto ?> mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="card-title">
                                                <span class="badge bg-<?= $progetto['Tipo'] === 'Hardware' ? 'success' : 'primary' ?> me-2">
                                                    <?= htmlspecialchars($progetto['Tipo']) ?>
                                                </span>
                                                <?= htmlspecialchars($progetto['Nome']) ?>
                                            </h6>
                                            <p class="card-text text-muted small">
                                                <?= htmlspecialchars(substr($progetto['Descrizione'], 0, 100)) ?>...
                                            </p>
                                        </div>
                                        <span class="badge bg-<?= $statoClasse ?> ms-2">
                                            <?= ucfirst($progetto['Stato']) ?>
                                        </span>
                                    </div>

                                    <!-- Progress bar -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span>Progresso</span>
                                                <span><?= number_format($percentuale, 1) ?>%</span>
                                            </div>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-<?= $progetto['Tipo'] === 'Hardware' ? 'success' : 'primary' ?>"
                                                     style="width: <?= $percentuale ?>%"></div>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <?= formatCurrency($progetto['Totale_Raccolto']) ?> di <?= formatCurrency($progetto['Budget']) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <small class="text-muted">Sostenitori</small>
                                            <div class="fw-bold text-primary"><?= $progetto['Num_Sostenitori'] ?></div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <small class="text-muted">Giorni rimasti</small>
                                            <div class="fw-bold <?= $progetto['Giorni_Rimanenti'] < 7 ? 'text-danger' : 'text-success' ?>">
                                                <?= $progetto['Giorni_Rimanenti'] > 0 ? $progetto['Giorni_Rimanenti'] : 'Scaduto' ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <a href="../projects/project_detail.php?name=<?= urlencode($progetto['Nome']) ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Visualizza
                                        </a>
                                        <a href="edit_project.php?name=<?= urlencode($progetto['Nome']) ?>"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Modifica
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminare il progetto?')">
                                            <input type="hidden" name="elimina_progetto" value="<?= htmlspecialchars($progetto['Nome']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                                        </form>
                                        <?php if ($progetto['Num_Commenti'] > 0): ?>
                                            <span class="badge bg-info ms-2">
                                                <i class="fas fa-comments me-1"></i><?= $progetto['Num_Commenti'] ?> commenti
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar con candidature e commenti -->
        <div class="col-lg-4">
            <!-- Candidature ricevute -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user-check me-2"></i>Candidature Ricevute
                        <?php
                        $candidatureInAttesa = array_filter($candidatureRicevute, function($c) {
                            return $c['Esito'] === null;
                        });
                        if (count($candidatureInAttesa) > 0): ?>
                            <span class="badge bg-warning"><?= count($candidatureInAttesa) ?></span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($candidatureRicevute)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox mb-2"></i>
                            <p class="small mb-0">Nessuna candidatura ricevuta</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($candidatureRicevute as $candidatura): ?>
                            <?php
                            // LOGICA CORRETTA per determinare lo stato
                            $esitoRaw = $candidatura['Esito'];

                            if ($esitoRaw === null || $esitoRaw === '' || $esitoRaw === 'NULL') {
                                // Candidatura in attesa
                                $statoClasse = 'candidatura-pending';
                                $statoBadge = '<span class="badge bg-warning">In Attesa</span>';
                                $mostraBottoni = true;
                            } elseif ($esitoRaw === 1 || $esitoRaw === '1' || $esitoRaw === true) {
                                // Candidatura accettata
                                $statoClasse = 'candidatura-accepted';
                                $statoBadge = '<span class="badge bg-success">Accettata</span>';
                                $mostraBottoni = false;
                            } else {
                                // Candidatura rifiutata
                                $statoClasse = 'candidatura-rejected';
                                $statoBadge = '<span class="badge bg-danger">Rifiutata</span>';
                                $mostraBottoni = false;
                            }
                            ?>

                            <div class="card <?= $statoClasse ?> mb-2" data-candidatura-id="<?= $candidatura['ID'] ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="card-title small mb-1">
                                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($candidatura['Nickname']) ?>
                                                <small class="text-muted">(<?= htmlspecialchars($candidatura['Nome'] . ' ' . $candidatura['Cognome']) ?>)</small>
                                            </h6>
                                            <p class="card-text small text-muted mb-2">
                                                <strong>Profilo:</strong> <?= htmlspecialchars($candidatura['Nome_Profilo']) ?><br>
                                                <strong>Progetto:</strong> <em><?= htmlspecialchars($candidatura['Nome_Progetto']) ?></em><br>
                                                <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($candidatura['Data_Candidatura'])) ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <?= $statoBadge ?>
                                        </div>
                                    </div>

                                    <?php if ($mostraBottoni): ?>
                                        <div class="d-flex gap-1 mt-2">
                                            <button class="btn btn-success btn-sm flex-fill"
                                                    onclick="gestisciCandidatura(<?= $candidatura['ID'] ?>, true)"
                                                    title="Accetta candidatura"
                                                    type="button">
                                                <i class="fas fa-check me-1"></i>Accetta
                                            </button>
                                            <button class="btn btn-danger btn-sm flex-fill"
                                                    onclick="gestisciCandidatura(<?= $candidatura['ID'] ?>, false)"
                                                    title="Rifiuta candidatura"
                                                    type="button">
                                                <i class="fas fa-times me-1"></i>Rifiuta
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Candidatura già processata
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Commenti ricevuti -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-comments me-2"></i>Commenti Ricevuti
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($commentiRicevuti)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-comment-slash fa-2x mb-2"></i>
                            <p class="small mb-0">Nessun commento ricevuto</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($commentiRicevuti as $commento): ?>
                            <div class="card comment-card mb-3">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between">
                                        <strong class="text-primary"><?= htmlspecialchars($commento['Email_Utente']) ?></strong>
                                        <small class="text-muted">Progetto: <?= htmlspecialchars($commento['Nome_Progetto']) ?></small>
                                    </div>
                                    <p class="mb-2"><?= htmlspecialchars($commento['Testo']) ?></p>

                                    <?php if ($commento['Risposta']): ?>
                                        <div class="bg-light p-2 rounded">
                                            <strong>La tua risposta:</strong> <?= htmlspecialchars($commento['Risposta']) ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" action="creator_dashboard.php" class="mt-2">
                                            <input type="hidden" name="id_commento" value="<?= $commento['ID'] ?>">
                                            <input type="hidden" name="nome_progetto" value="<?= $commento['Nome_Progetto'] ?>">
                                            <div class="input-group">
                                                <textarea name="testo_risposta" class="form-control" rows="2" placeholder="Scrivi una risposta..." required></textarea>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast per notifiche -->
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
    // ✅ FUNZIONE CORRETTA per gestire candidature
    function gestisciCandidatura(idCandidatura, accettata) {
        // Validazione input
        if (!idCandidatura || idCandidatura <= 0) {
            mostraToast('ID candidatura non valido', 'error');
            return;
        }

        // Conferma azione
        const azione = accettata ? 'accettare' : 'rifiutare';
        const conferma = confirm(`Sei sicuro di voler ${azione} questa candidatura?`);

        if (!conferma) {
            return;
        }

        // Disabilita i bottoni per evitare click multipli
        const bottoniCandidatura = document.querySelectorAll(`[onclick*="${idCandidatura}"]`);
        bottoniCandidatura.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Elaborazione...';
        });

        // Prepara i dati
        const formData = new FormData();
        formData.append('action', 'manage_candidatura');
        formData.append('id_candidatura', idCandidatura);
        formData.append('accettata', accettata ? '1' : '0');

        // Debug per verificare i dati inviati
        console.log('🔍 Gestione candidatura:', {
            id: idCandidatura,
            accettata: accettata ? '1' : '0'
        });

        // Chiamata API
        fetch('../../api/manage_candidatures.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                // Verifica se la risposta è JSON valida
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('✅ Risposta API:', data);

                if (data.success) {
                    mostraToast(data.message, 'success');

                    // Aggiorna l'UI senza ricaricare la pagina
                    aggiornaUICandidatura(idCandidatura, accettata, data.candidatura);

                    // Opzionale: ricarica dopo 2 secondi per aggiornare tutto
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mostraToast(data.message || 'Errore sconosciuto', 'error');
                    // Riabilita i bottoni in caso di errore
                    riabilitaBottoniCandidatura(idCandidatura);
                }
            })
            .catch(error => {
                console.error('❌ Errore:', error);
                mostraToast(`Errore di connessione: ${error.message}`, 'error');
                // Riabilita i bottoni in caso di errore
                riabilitaBottoniCandidatura(idCandidatura);
            });
    }

    // ✅ Funzione per aggiornare UI senza reload
    function aggiornaUICandidatura(idCandidatura, accettata, candidaturaData) {
        // Trova la card della candidatura
        const candidaturaCard = document.querySelector(`[data-candidatura-id="${idCandidatura}"]`);

        if (candidaturaCard) {
            // Aggiorna la classe CSS
            candidaturaCard.className = candidaturaCard.className
                    .replace(/candidatura-(pending|accepted|rejected)/, '') +
                ` candidatura-${accettata ? 'accepted' : 'rejected'}`;

            // Aggiorna il badge
            const badge = candidaturaCard.querySelector('.badge');
            if (badge) {
                badge.className = `badge bg-${accettata ? 'success' : 'danger'}`;
                badge.textContent = accettata ? 'Accettata' : 'Rifiutata';
            }

            // Rimuovi i bottoni di azione
            const bottoniContainer = candidaturaCard.querySelector('.d-flex.gap-1');
            if (bottoniContainer) {
                bottoniContainer.remove();
            }
        }
    }

    // ✅ Funzione per riabilitare bottoni in caso di errore
    function riabilitaBottoniCandidatura(idCandidatura) {
        const bottoniCandidatura = document.querySelectorAll(`[onclick*="${idCandidatura}"]`);
        bottoniCandidatura.forEach((btn, index) => {
            btn.disabled = false;
            if (btn.classList.contains('btn-success')) {
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Accetta';
            } else if (btn.classList.contains('btn-danger')) {
                btn.innerHTML = '<i class="fas fa-times me-1"></i>Rifiuta';
            }
        });
    }

    // ✅ Funzione migliorata per mostrare toast
    function mostraToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        const toast = document.getElementById('toastNotifica');
        const toastMessage = document.getElementById('toastMessage');

        if (!toast || !toastMessage) {
            // Fallback se il toast non esiste
            if (type === 'error') {
                alert(`❌ ${message}`);
            } else {
                alert(`✅ ${message}`);
            }
            return;
        }

        // Configura il toast
        toastMessage.textContent = message;

        // Colori in base al tipo
        const colorClasses = {
            'success': 'bg-success text-white',
            'error': 'bg-danger text-white',
            'warning': 'bg-warning text-dark',
            'info': 'bg-info text-white'
        };

        toast.className = `toast ${colorClasses[type] || colorClasses.info}`;

        // Mostra il toast
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: type === 'error' ? 5000 : 3000
        });
        bsToast.show();
    }

    // ✅ Inizializzazione quando il DOM è pronto
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Sistema gestione candidature caricato');

        // Debug opzionale
        if (window.location.search.includes('debug=1')) {
            const candidature = document.querySelectorAll('[data-candidatura-id]');
            console.log(`🔍 Candidature trovate: ${candidature.length}`);
        }
    });
</script>
</body>
</html>