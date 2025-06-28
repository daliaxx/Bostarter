<?php
/**
 * BOSTARTER - Dashboard Utente
 * File: public/dashboard/user_dashboard.php
 */

require_once '../../config/database.php';
$error = null;
$candidatureInCorso = [];

// Verifica login
SessionManager::requireLogin('../../index.html');

    $userEmail = SessionManager::get('user_email');
    $userName = SessionManager::get('user_nome') . ' ' . SessionManager::get('user_cognome');
    $userNickname = SessionManager::get('user_nickname');
    $isCreator = SessionManager::isCreator();
    $isAdmin = SessionManager::isAdmin();

try {
    $db = Database::getInstance();

    // Statistiche utente
    $stats = [
        'progetti_finanziati' => 0,
        'totale_investito' => 0,
        'candidature_inviate' => 0,
        'candidature_accettate' => 0,
        'commenti_inseriti' => 0
    ];

    // Progetti finanziati
    $result = $db->fetchOne("
        SELECT COUNT(DISTINCT Nome_Progetto) as count, COALESCE(SUM(Importo), 0) as totale 
        FROM FINANZIAMENTO 
        WHERE Email_Utente = ?
    ", [$userEmail]);

    $stats['progetti_finanziati'] = $result['count'] ?? 0;
    $stats['totale_investito'] = $result['totale'] ?? 0;

    // Candidature
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM CANDIDATURA WHERE Email_Utente = ?", [$userEmail]);
    $stats['candidature_inviate'] = $result['count'] ?? 0;

    $result = $db->fetchOne("SELECT COUNT(*) as count FROM CANDIDATURA WHERE Email_Utente = ? AND Esito = 1", [$userEmail]);
    $stats['candidature_accettate'] = $result['count'] ?? 0;

    // Commenti
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM COMMENTO WHERE Email_Utente = ?", [$userEmail]);
    $stats['commenti_inseriti'] = $result['count'] ?? 0;

    // Progetti finanziati recenti
    $progettiFinanziati = $db->fetchAll("
    SELECT p.Nome, p.Descrizione, 
        SUM(f.Importo) AS Mio_Investimento, 
        MAX(f.Data) AS Ultima_Donazione,
        GROUP_CONCAT(
            DISTINCT CONCAT(r.Codice, '|', r.Descrizione) 
            ORDER BY f.Data DESC 
            SEPARATOR ';;;'
        ) AS Rewards_Ricevute,
        COUNT(DISTINCT f.ID) AS Num_Finanziamenti
    FROM FINANZIAMENTO f
    JOIN PROGETTO p ON f.Nome_Progetto = p.Nome
    LEFT JOIN REWARD r ON f.Codice_Reward = r.Codice
    WHERE f.Email_Utente = ?
    GROUP BY p.Nome, p.Descrizione
    ORDER BY Ultima_Donazione DESC
    LIMIT 5
", [$userEmail]);
    // Recupera skill dell'utente
    $mySkills = [];
    try {
        $sql = "SELECT sc.Competenza, sc.Livello 
            FROM SKILL_CURRICULUM sc 
            WHERE sc.Email_Utente = ? 
            ORDER BY sc.Competenza, sc.Livello DESC";

        $skillsResult = $db->fetchAll($sql, [$userEmail]);
        $mySkills = is_array($skillsResult) ? $skillsResult : [];

    } catch (Exception $e) {
        error_log("Errore recupero skills: " . $e->getMessage());
        $mySkills = [];
    }

    // Query per candidature in corso
    $candidatureInCorso = $db->fetchAll("
        SELECT c.ID, c.Data_Candidatura, c.Esito, pr.Nome as Nome_Profilo,
            p.Nome as Nome_Progetto
        FROM CANDIDATURA c
        JOIN PROFILO pr ON c.ID_Profilo = pr.ID
        JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
        WHERE c.Email_Utente = ?
        ORDER BY c.Data_Candidatura DESC
        LIMIT 5
    ", [$userEmail]);

} catch (Exception $e) {
    error_log("Errore dashboard utente: " . $e->getMessage());
    $error = "Errore nel caricamento dei dati: " . $e->getMessage(); // mostra il messaggio reale
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BOSTARTER</title>
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
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .skill-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            margin: 3px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .candidatura-pending {
            border-left: 4px solid #ffc107;
        }
        .candidatura-accepted {
            border-left: 4px solid #28a745;
        }
        .candidatura-rejected {
            border-left: 4px solid #dc3545;
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
                        <?php elseif ($isCreator): ?>
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
            <h1><i class="fas fa-user-circle"></i> Benvenuto, <?= htmlspecialchars($userName) ?>!</h1>
            <p class="text-muted">Nickname: <strong><?= htmlspecialchars($userNickname) ?></strong></p>
        </div>
    </div>

    <!-- Errori -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Statistiche -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-heart fa-2x mb-2"></i>
                    <h4><?= $stats['progetti_finanziati'] ?></h4>
                    <small>Progetti Finanziati</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-euro-sign fa-2x mb-2"></i>
                    <h4><?= number_format($stats['totale_investito'], 0) ?>€</h4>
                    <small>Totale Investito</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-paper-plane fa-2x mb-2"></i>
                    <h4><?= $stats['candidature_inviate'] ?></h4>
                    <small>Candidature</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h4><?= $stats['candidature_accettate'] ?></h4>
                    <small>Accettate</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-comments fa-2x mb-2"></i>
                    <h4><?= $stats['commenti_inseriti'] ?></h4>
                    <small>Commenti</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="fas fa-cogs fa-2x mb-2"></i>
                    <span class="badge bg-primary"><?= isset($mySkills) ? count($mySkills) : 0 ?></span>
                    <small>Skills</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonna sinistra -->
        <div class="col-lg-8">
            <!-- Progetti finanziati con reward -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-heart me-2"></i>I Miei Investimenti</h5>
                    <a href="../projects/projects.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Finanzia Altri
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($progettiFinanziati)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Non hai ancora finanziato nessun progetto</h6>
                            <a href="../projects/projects.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Esplora Progetti
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-project-diagram me-1"></i>Progetto</th>
                                    <th><i class="fas fa-euro-sign me-1"></i>Investimento</th>
                                    <th><i class="fas fa-gift me-1"></i>Le Mie Reward</th>
                                    <th><i class="fas fa-calendar me-1"></i>Ultima Donazione</th>
                                    <th><i class="fas fa-eye me-1"></i>Azioni</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($progettiFinanziati as $progetto): ?>
                                    <?php
                                    // ✅ Elabora le reward ricevute
                                    $rewardsRicevute = [];
                                    if (!empty($progetto['Rewards_Ricevute'])) {
                                        $rewardsList = explode(';;;', $progetto['Rewards_Ricevute']);
                                        foreach ($rewardsList as $rewardData) {
                                            if (!empty($rewardData) && strpos($rewardData, '|') !== false) {
                                                list($codice, $descrizione) = explode('|', $rewardData, 2);
                                                $rewardsRicevute[] = [
                                                    'codice' => $codice,
                                                    'descrizione' => $descrizione
                                                ];
                                            }
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($progetto['Nome']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars(substr($progetto['Descrizione'], 0, 60)) ?>...</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success fs-6">€<?= number_format($progetto['Mio_Investimento'], 2) ?></span>
                                            <?php if ($progetto['Num_Finanziamenti'] > 1): ?>
                                                <br><small class="text-muted"><?= $progetto['Num_Finanziamenti'] ?> donazioni</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($rewardsRicevute)): ?>
                                                <div class="reward-list">
                                                    <?php foreach (array_unique($rewardsRicevute, SORT_REGULAR) as $reward): ?>
                                                        <div class="reward-item mb-1">
                                                    <span class="badge bg-primary me-1">
                                                        <i class="fas fa-gift me-1"></i><?= htmlspecialchars($reward['codice']) ?>
                                                    </span>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($reward['descrizione']) ?></small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">
                                            <i class="fas fa-minus-circle me-1"></i>Nessuna reward
                                        </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y', strtotime($progetto['Ultima_Donazione'])) ?></small>
                                        </td>
                                        <td>
                                            <a href="../projects/project_detail.php?name=<?= urlencode($progetto['Nome']) ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Visualizza progetto">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ✅ Info aggiuntiva -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Le tue reward:</strong> I creatori ti contatteranno per consegnare le ricompense dei progetti che hai sostenuto.
                                Le reward vengono assegnate in base ai finanziamenti che hai effettuato.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ✅ CSS aggiuntivo per migliorare l'aspetto -->
            <style>
                .reward-item {
                    padding: 0.25rem 0;
                }

                .reward-list {
                    max-height: 80px;
                    overflow-y: auto;
                }

                .table td {
                    vertical-align: middle;
                }

                .badge.fs-6 {
                    font-size: 0.9rem !important;
                }
            </style>
            <!-- Candidature -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-briefcase me-2"></i>Le Mie Candidature</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($candidatureInCorso)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Non hai ancora inviato candidature</h6>
                            <a href="../projects/projects.php?category=software" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Cerca Progetti Software
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($candidatureInCorso as $candidatura): ?>
                            <div class="card mb-2 candidatura-<?= $candidatura['Esito'] === null ? 'pending' : ($candidatura['Esito'] ? 'accepted' : 'rejected') ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($candidatura['Nome_Profilo']) ?></h6>
                                            <small class="text-muted">
                                                Progetto: <?= htmlspecialchars($candidatura['Nome_Progetto']) ?><br>
                                                Candidatura del: <?= date('d/m/Y', strtotime($candidatura['Data_Candidatura'])) ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($candidatura['Esito'] === null): ?>
                                                <span class="badge bg-warning">In Attesa</span>
                                            <?php elseif ($candidatura['Esito']): ?>
                                                <span class="badge bg-success">Accettata</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Rifiutata</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonna destra -->
        <div class="col-lg-4">
            <!-- Le mie skills -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-cogs me-2"></i>Le Mie Skills</h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($mySkills)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-tools fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Aggiungi le tue competenze per candidarti ai progetti!</p>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                <i class="fas fa-plus me-2"></i>Aggiungi Skill
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="skills-container">
                            <?php foreach ($mySkills as $skill): ?>
                                <span class="skill-badge">
                                    <?= htmlspecialchars($skill['Competenza']) ?>
                                    <span class="badge bg-light text-dark ms-1"><?= $skill['Livello'] ?>/5</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <button class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                            <i class="fas fa-plus me-2"></i>Aggiungi Altra Skill
                        </button>
                    <?php endif; ?>
                </div>
            </div>



<!-- Modal Aggiungi Skill -->
<div class="modal fade" id="addSkillModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Aggiungi Skill
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addSkillForm">
                    <div class="mb-3">
                        <label for="competenza" class="form-label">Competenza</label>
                        <select class="form-select" id="competenza" name="competenza" required>
                            <option value="">Caricamento competenze...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="livello" class="form-label">Livello (1-5)</label>
                        <select class="form-select" id="livello" name="livello" required>
                            <option value="">Seleziona livello...</option>
                            <option value="1">1 - Principiante</option>
                            <option value="2">2 - Base</option>
                            <option value="3">3 - Intermedio</option>
                            <option value="4">4 - Avanzato</option>
                            <option value="5">5 - Esperto</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="addSkill()">
                    <i class="fas fa-plus me-2"></i>Aggiungi
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function addSkill() {
        const competenza = document.getElementById('competenza').value;
        const livello = document.getElementById('livello').value;

        if (!competenza || !livello) {
            alert('Seleziona competenza e livello');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_skill');
        formData.append('competenza', competenza);
        formData.append('livello', livello);

        fetch('../../api/manage_skill.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('Errore durante l\'aggiunta della skill');
                console.error('Error:', error);
            });
    }

    // Carica competenze dinamicamente
    function loadAvailableSkills() {
        fetch('../../api/manage_skill.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_available_skills'
        })
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('competenza');
                if (data.success) {
                    select.innerHTML = '<option value="">Seleziona una competenza...</option>';
                    data.skills.forEach(skill => {
                        select.innerHTML += `<option value="${skill.Competenza}">${skill.Competenza}</option>`;
                    });
                } else {
                    select.innerHTML = '<option value="">Errore caricamento competenze</option>';
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                document.getElementById('competenza').innerHTML = '<option value="">Errore connessione</option>';
            });
    }

    // Carica competenze all'apertura del modal
    document.getElementById('addSkillModal').addEventListener('show.bs.modal', function() {
        loadAvailableSkills();
    });
</script>
</body>
</html>