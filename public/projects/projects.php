<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * BOSTARTER - Lista Progetti
 * File: public/projects.php
 */

require_once '../../config/database.php';
require_once '../../includes/navbar.php';

// Avvia sessione
SessionManager::start();

// Verifica se l'utente è loggato
$isLoggedIn = SessionManager::isLoggedIn();
$userEmail = SessionManager::getUserEmail();
$isCreator = SessionManager::isCreator();
$isAdmin = SessionManager::isAdmin();

// AGGIORNAMENTO AUTOMATICO STATO PROGETTI SCADUTI
$db = Database::getInstance();
$db->ensureEventScheduler(); // Assicura che l'evento MySQL sia attivo

// Esegui l'aggiornamento automatico
$db->execute("
    UPDATE PROGETTO 
    SET Stato = 'chiuso' 
    WHERE Stato = 'aperto' AND Data_Limite <= CURDATE()
");

// Variabili filtro iniziali
$searchQuery = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';

$whereClause = "WHERE 1=1";
$params = [];

if (!empty($searchQuery)) {
    $whereClause .= " AND (p.Nome LIKE ? OR p.Descrizione LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($statusFilter !== 'all') {
    $whereClause .= " AND p.Stato = ?";
    $params[] = $statusFilter;
}

if ($categoryFilter !== 'all') {
    $whereClause .= " AND p.Tipo = ?";
    $params[] = $categoryFilter;
}

$sql = "
    SELECT 
        p.Nome,
        p.Descrizione,
        p.Data_Inserimento,
        p.Stato,
        p.Budget,
        p.Data_Limite,
        p.Tipo AS Categoria,
        u.Nickname AS Creatore,
        c.Affidabilita,
        COALESCE(SUM(f.Importo), 0) AS Totale_Finanziato,
        COUNT(DISTINCT f.ID) AS Num_Finanziatori,
        foto.percorso AS Foto,
        DATEDIFF(p.Data_Limite, CURDATE()) AS Giorni_Rimanenti
    FROM PROGETTO p
    JOIN CREATORE c ON p.Email_Creatore = c.Email
    JOIN UTENTE u ON c.Email = u.Email
    LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
    LEFT JOIN FOTO foto ON p.Nome = foto.Nome_Progetto
    $whereClause
    GROUP BY p.Nome, p.Descrizione, p.Data_Inserimento, p.Stato, p.Budget, p.Data_Limite, p.Tipo, u.Nickname, c.Affidabilita, foto.percorso
    ORDER BY p.Data_Inserimento DESC
";

$progetti = $db->fetchAll($sql, $params);

// Gestione accesso utente
$isLoggedIn = SessionManager::isLoggedIn();
$isCreator = SessionManager::isCreator();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progetti - BOSTARTER</title>
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

<div class="container mt-4">
    <!-- Header e pulsanti azione -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 text-primary">
                <i class="fas fa-project-diagram me-2"></i>Esplora Progetti
            </h1>
            <p class="text-muted">Scopri e finanzia progetti innovativi</p>
        </div>
        <div class="col-md-4 text-md-end">
            <?php if ($isLoggedIn && $isCreator): ?>
                <a href="../dashboard/creator_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Crea Progetto
                </a>
            <?php elseif ($isLoggedIn && !$isCreator): ?>
                <!-- Visibile solo se NON è già creatore -->
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#becomeCreatorModal">
                    Diventa creatore
                </button>
            <?php else: ?>
                <a href="../../index.html" class="btn btn-outline-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Accedi per Creare
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtri -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Stato</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tutti</option>
                        <option value="aperto" <?= $statusFilter === 'aperto' ? 'selected' : '' ?>>Aperti</option>
                        <option value="chiuso" <?= $statusFilter === 'chiuso' ? 'selected' : '' ?>>Chiusi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Categoria</label>
                    <select name="category" id="category" class="form-select">
                        <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>Tutte</option>
                        <option value="hardware" <?= $categoryFilter === 'hardware' ? 'selected' : '' ?>>Hardware</option>
                        <option value="software" <?= $categoryFilter === 'software' ? 'selected' : '' ?>>Software</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Cerca</label>
                    <input type="text" name="search" id="search" class="form-control"
                           placeholder="Nome o descrizione..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-outline-primary d-block w-100">
                        <i class="fas fa-search"></i> Filtra
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistiche in evidenza -->
    <?php if ($isLoggedIn): ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-trophy fa-2x text-primary mb-2"></i>
                        <h5 class="card-title">Top Creatori</h5>
                        <p class="card-text">Classifica per affidabilità</p>
                        <a href="../statistiche.php?view=top-creatori" class="btn btn-outline-primary btn-sm">Visualizza</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                        <h5 class="card-title">Progetti in Crescita</h5>
                        <p class="card-text">Più vicini al completamento</p>
                        <a href="../statistiche.php?view=progetti-crescita" class="btn btn-outline-success btn-sm">Visualizza</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-info mb-2"></i>
                        <h5 class="card-title">Top Finanziatori</h5>
                        <p class="card-text">Maggiori investitori</p>
                        <a href="../statistiche.php?view=top-finanziatori" class="btn btn-outline-info btn-sm">Visualizza</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Messaggi di errore -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Lista progetti -->
    <div class="row">
        <?php if (empty($progetti)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Nessun progetto trovato</h4>
                    <p class="text-muted">
                        <?php if (!empty($searchQuery) || $statusFilter !== 'all' || $categoryFilter !== 'all'): ?>
                            Prova a modificare i filtri di ricerca
                        <?php else: ?>
                            Non ci sono ancora progetti nella piattaforma
                        <?php endif; ?>
                    </p>
                    <?php if ($isLoggedIn && $isCreator): ?>
                        <a href="../dashboard/creator_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Crea il Primo Progetto
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($progetti as $progetto): ?>
                <?php
                $percentualeCompletamento = $progetto['Budget'] > 0
                    ? min(100, ($progetto['Totale_Finanziato'] / $progetto['Budget']) * 100)
                    : 0;
                $giorniRimanenti = $progetto['Giorni_Rimanenti'];
                $statoClasse = $progetto['Stato'] === 'aperto' ? 'success' : 'secondary';
                ?>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card project-card h-100">
                        <?php
                        $fotoDb = $progetto['Foto'] ?? '';
                        if ($fotoDb !== '') {
                            // Se già contiene 'img/', non aggiungo nulla
                            $src = (strpos($fotoDb, 'img/') === 0)
                                ? "/Bostarter/{$fotoDb}"
                                : "/Bostarter/img/{$fotoDb}";
                        } else {
                            $src = null;
                        }
                        ?>

                        <?php if ($src): ?>
                            <img src="<?= htmlspecialchars($src) ?>"
                                 class="card-img-top"
                                 style="height:200px; object-fit:cover;"
                                 alt="<?= htmlspecialchars($progetto['Nome']) ?>">
                        <?php else: ?>
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light"
                                 style="height:200px;">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>


                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge <?= $progetto['Categoria'] === 'Hardware' ? 'badge-hardware' : 'badge-software' ?> text-white">
                                    <i class="fas fa-<?= $progetto['Categoria'] === 'Hardware' ? 'microchip' : 'code' ?> me-1"></i>
                                    <?= htmlspecialchars($progetto['Categoria']) ?>
                                </span>
                                <span class="badge bg-<?= $statoClasse ?>"><?= ucfirst($progetto['Stato']) ?></span>
                            </div>

                            <h5 class="card-title"><?= htmlspecialchars($progetto['Nome']) ?></h5>
                            <p class="card-text text-muted small flex-grow-1">
                                <?= htmlspecialchars(substr($progetto['Descrizione'], 0, 100)) ?>
                                <?= strlen($progetto['Descrizione']) > 100 ? '...' : '' ?>
                            </p>

                            <!-- Progress bar -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span><?= Utils::formatCurrency($progetto['Totale_Finanziato']) ?> raccolti</span>
                                    <span><?= number_format($percentualeCompletamento, 1) ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar progress-bar-custom"
                                         style="width: <?= $percentualeCompletamento ?>%"></div>
                                </div>
                                <div class="small text-muted mt-1">
                                    Obiettivo: <?= Utils::formatCurrency($progetto['Budget']) ?>
                                </div>
                            </div>

                            <!-- Info creatore e deadline -->
                            <div class="row small text-muted mb-3">
                                <div class="col-12 mb-1">
                                    <i class="fas fa-user me-1"></i>
                                    <span class="creator-badge badge text-dark">
                                        <?= htmlspecialchars($progetto['Creatore']) ?>
                                    </span>
                                    <small class="ms-1">
                                        (<?= number_format($progetto['Affidabilita'] * 100, 1) ?>% affidabilità)
                                    </small>
                                </div>
                                <div class="col-12 mb-1">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php if ($giorniRimanenti > 0): ?>
                                        <span class="text-warning"><?= $giorniRimanenti ?> giorni rimanenti</span>
                                    <?php else: ?>
                                        <span class="text-danger">Scaduto</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <i class="fas fa-users me-1"></i>
                                    <?= $progetto['Num_Finanziatori'] ?> finanziatori
                                </div>
                            </div>

                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <a href="project_detail.php?name=<?= urlencode($progetto['Nome']) ?>"
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>Visualizza Dettagli
                                    </a>
                                    <?php if ($progetto['Stato'] === 'aperto' && $giorniRimanenti > 0): ?>
                                        <?php if ($isLoggedIn): ?>
                                            <a href="project_detail.php?name=<?= urlencode($progetto['Nome']) ?>&action=fund"
                                               class="btn btn-primary">
                                                <i class="fas fa-heart me-1"></i>Finanzia Progetto
                                            </a>
                                        <?php else: ?>
                                            <a href="../../index.html" class="btn btn-primary">
                                                <i class="fas fa-sign-in-alt me-1"></i>Accedi per Finanziare
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal: Diventa Creatore -->
    <div class="modal fade" id="becomeCreatorModal" tabindex="-1" aria-labelledby="becomeCreatorLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="becomeCreatorLabel">Diventa un Creatore</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
            <p>Vuoi davvero diventare un creatore di progetti? Potrai crearli e gestirli dalla tua area personale.</p>
            <div id="becomeCreatorAlert" class="alert d-none"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="promuoviACreatore()">Conferma</button>
        </div>
        </div>
    </div>
    </div>

    <script>
    function promuoviACreatore() {
    fetch('/Bostarter/public/dashboard/become_creator.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        const alertBox = document.getElementById('becomeCreatorAlert');
        alertBox.classList.remove('d-none', 'alert-success', 'alert-danger');

        if (data.success) {
        alertBox.classList.add('alert-success');
        alertBox.textContent = data.message;

        setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('becomeCreatorModal'));
            modal.hide();
            location.reload(); // Ricarica per aggiornare la visibilità del bottone
        }, 1500);
        } else {
        alertBox.classList.add('alert-danger');
        alertBox.textContent = data.message;
        }
    })
    .catch(error => {
        const alertBox = document.getElementById('becomeCreatorAlert');
        alertBox.classList.remove('d-none', 'alert-success');
        alertBox.classList.add('alert-danger');
        alertBox.textContent = 'Errore di connessione. Riprova.';
        console.error(error);
    });
    }
    </script>


    <!-- Footer info -->
    <div class="row mt-5">
        <div class="col-12 text-center text-muted">
            <hr>
            <p>
                <strong>BOSTARTER</strong> - Piattaforma di crowdfunding universitaria<br>
                <small>Progetto del corso di Basi di Dati A.A. 2024/2025</small>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>