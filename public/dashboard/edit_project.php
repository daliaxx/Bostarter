<?php
/**
 * BOSTARTER - Modifica Progetto
 * File: public/dashboard/edit_project.php
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../config/database.php';

SessionManager::requireLogin('../../index.html');
SessionManager::requireCreator();

$userEmail = SessionManager::getUserEmail();
$userNickname = SessionManager::get('user_nickname') ?? 'Utente';
$userName = SessionManager::get('user_nome') ?? '';
$userCognome = SessionManager::get('user_cognome') ?? '';
$isAdmin = SessionManager::isAdmin();
$isCreator = SessionManager::isCreator();
$projectName = $_GET['name'] ?? '';

if (empty($projectName)) {
    header('Location: creator_dashboard.php?error=progetto_non_specificato');
    exit;
}

$db = Database::getInstance();
$error = null;
$success = null;

// Verifica che il progetto appartenga all'utente loggato
$project = $db->fetchOne("
    SELECT * FROM PROGETTO 
    WHERE Nome = ? AND Email_Creatore = ?
", [$projectName, $userEmail]);

if (!$project) {
    header('Location: creator_dashboard.php?error=progetto_non_trovato');
    exit;
}

// Gestione form di modifica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descrizione = trim($_POST['descrizione'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);
    $dataLimite = $_POST['data_limite'] ?? '';

    if (empty($descrizione) || $budget <= 0 || empty($dataLimite)) {
        $error = "Tutti i campi sono obbligatori e devono essere validi.";
    } elseif (strtotime($dataLimite) <= time()) {
        $error = "La data limite deve essere futura.";
    } else {
        try {
            $db->execute("
                UPDATE PROGETTO 
                SET Descrizione = ?, Budget = ?, Data_Limite = ?
                WHERE Nome = ? AND Email_Creatore = ?
            ", [$descrizione, $budget, $dataLimite, $projectName, $userEmail]);

            $success = "Progetto aggiornato con successo!";

            // Aggiorna i dati del progetto
            $project = $db->fetchOne("
                SELECT * FROM PROGETTO 
                WHERE Nome = ? AND Email_Creatore = ?
            ", [$projectName, $userEmail]);

        } catch (Exception $e) {
            $error = "Errore durante l'aggiornamento: " . $e->getMessage();
        }
    }
}

// Recupera le reward del progetto
$rewards = $db->fetchAll("
    SELECT * FROM REWARD WHERE Nome_Progetto = ?
", [$projectName]);

// START: MODIFIED CODE FOR PROFILES (previously added)
$profiles = [];
if ($project['Tipo'] === 'Software') {
    $profiles = $db->fetchAll("
        SELECT P.ID, P.Nome
        FROM PROFILO P
        WHERE P.Nome_Progetto = ?
    ", [$projectName]);

    foreach ($profiles as &$profile) {
        $profile['skills'] = $db->fetchAll("
            SELECT SR.Competenza, SR.Livello
            FROM SKILL_RICHIESTA SR
            WHERE SR.ID_Profilo = ?
        ", [$profile['ID']]);
    }
    unset($profile); // Break the reference
}
// END: MODIFIED CODE FOR PROFILES

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Progetto - BOSTARTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --small-radius: 12px;
        }
        body {
            background: #f8f9fa;
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
        /* START: NEW CSS FOR PROFILE SECTION (previously added) */
        .profile-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .profile-item:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .skill-badge {
            background-color: #0d6efd;
            color: white;
            padding: 0.35em 0.65em;
            border-radius: 0.25rem;
            font-size: 0.8em;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
        /* END: NEW CSS FOR PROFILE SECTION */
    </style>
</head>
<body>
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
                    <a class="nav-link" href="creator_dashboard.php">
                        <i class="fas fa-arrow-left me-1"></i>Dashboard
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($userNickname ?? 'Utente') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (isset($isAdmin) && $isAdmin): ?>
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
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Modifica Progetto: <?= htmlspecialchars($project['Nome']) ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Progetto</label>
                            <input type="text" class="form-control" id="nome" value="<?= htmlspecialchars($project['Nome']) ?>" disabled>
                            <small class="form-text text-muted">Il nome del progetto non può essere modificato.</small>
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo di Progetto</label>
                            <input type="text" class="form-control" id="tipo" value="<?= htmlspecialchars($project['Tipo']) ?>" disabled>
                            <small class="form-text text-muted">Il tipo di progetto non può essere modificato dopo la creazione.</small>
                        </div>
                        <div class="mb-3">
                            <label for="descrizione" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="descrizione" name="descrizione" rows="5" required><?= htmlspecialchars($project['Descrizione']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="budget" class="form-label">Budget Richiesto (€)</label>
                            <input type="number" step="0.01" class="form-control" id="budget" name="budget" value="<?= htmlspecialchars($project['Budget']) ?>" required min="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="data_limite" class="form-label">Data Limite Raccolta Fondi</label>
                            <input type="date" class="form-control" id="data_limite" name="data_limite" value="<?= htmlspecialchars($project['Data_Limite']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salva Modifiche</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-gift me-2"></i>Gestione Reward</h4>
                </div>
                <div class="card-body">
                    <div class="list-group mb-3">
                        <?php if (empty($rewards)): ?>
                            <p>Nessuna reward aggiunta a questo progetto.</p>
                        <?php else: ?>
                            <?php foreach ($rewards as $reward): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($reward['Codice']) ?>:</strong> <?= htmlspecialchars($reward['Descrizione']) ?>
                                    </div>
                                    <button class="btn btn-danger btn-sm" onclick="deleteReward('<?= htmlspecialchars($reward['Codice']) ?>')">
                                        <i class="fas fa-trash-alt"></i> Elimina
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRewardModal"><i class="fas fa-plus me-2"></i>Aggiungi Nuova Reward</button>
                </div>
            </div>
            <?php if ($project['Tipo'] === 'Software'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-users-cog me-2"></i>Profili Richiesti</h4>
                    </div>
                    <div class="card-body">
                        <div class="list-group mb-3">
                            <?php if (empty($profiles)): ?>
                                <p>Nessun profilo richiesto per questo progetto.</p>
                            <?php else: ?>
                                <?php foreach ($profiles as $profile): ?>
                                    <div class="list-group-item list-group-item-action profile-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5><i class="fas fa-user-tag me-1"></i><?= htmlspecialchars($profile['Nome']) ?></h5>
                                            <button class="btn btn-danger btn-sm" onclick="deleteProfile(<?= $profile['ID'] ?>)">
                                                <i class="fas fa-trash-alt"></i> Elimina Profilo
                                            </button>
                                        </div>
                                        <?php if (!empty($profile['skills'])): ?>
                                            <h6>Skills Richieste:</h6>
                                            <div>
                                                <?php foreach ($profile['skills'] as $skill): ?>
                                                    <span class="skill-badge">
                                                        <?= htmlspecialchars($skill['Competenza']) ?> (Livello: <?= htmlspecialchars($skill['Livello']) ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p>Nessuna skill richiesta per questo profilo.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProfileModal"><i class="fas fa-user-plus me-2"></i>Aggiungi Nuovo Profilo</button>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Dettagli Progetto</h5>
                </div>
                <div class="card-body">
                    <p><strong>Stato:</strong> <span class="badge bg-<?= $project['Stato'] === 'aperto' ? 'success' : 'danger' ?>"><?= htmlspecialchars(ucfirst($project['Stato'])) ?></span></p>
                    <p><strong>Data Inserimento:</strong> <?= date('d/m/Y', strtotime($project['Data_Inserimento'])) ?></p>
                    <p><strong>Creatore:</strong> <?= htmlspecialchars($project['Email_Creatore']) ?></p>
                    </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addRewardModal" tabindex="-1" aria-labelledby="addRewardModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="addRewardModalLabel">Aggiungi Nuova Reward</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addRewardForm">
                <div class="modal-body">
                    <input type="hidden" name="project_name" value="<?= htmlspecialchars($projectName) ?>">
                    <div class="mb-3">
                        <label for="rewardCode" class="form-label">Codice Reward</label>
                        <input type="text" class="form-control" id="rewardCode" name="codice" required>
                    </div>
                    <div class="mb-3">
                        <label for="rewardDescription" class="form-label">Descrizione Reward</label>
                        <textarea class="form-control" id="rewardDescription" name="descrizione" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="addReward()">Aggiungi Reward</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addProfileModal" tabindex="-1" aria-labelledby="addProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addProfileModalLabel">Aggiungi Nuovo Profilo Richiesto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addProfileForm">
                <div class="modal-body">
                    <input type="hidden" name="project_name" value="<?= htmlspecialchars($projectName) ?>">
                    <div class="mb-3">
                        <label for="profileName" class="form-label">Nome Profilo</label>
                        <input type="text" class="form-control" id="profileName" name="profile_name" required>
                    </div>

                    <hr>
                    <h6>Skills Richieste per questo profilo:</h6>
                    <div id="skillsContainer" class="mb-3">
                        </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSkillToProfileModal()">
                        <i class="fas fa-plus me-1"></i>Aggiungi Skill
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" onclick="addProfile()">Salva Profilo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function addReward() {
        const form = document.getElementById('addRewardForm');
        const formData = new FormData(form);

        formData.append('action', 'add_reward');

        formData.append('nome_progetto', '<?= htmlspecialchars($projectName) ?>');

        fetch('../../api/manage_rewards.php', {
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
                alert('Errore durante l\'aggiunta della reward');
            });
    }

    function deleteReward(codice) {
        if (!confirm('Vuoi eliminare questa reward?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_reward');
        formData.append('codice', codice);
        formData.append('project_name', '<?= htmlspecialchars($projectName) ?>'); // Pass project name for context

        formData.append('nome_progetto', '<?= htmlspecialchars($projectName) ?>');

        fetch('../../api/manage_rewards.php', {
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
                alert('Errore durante l\'eliminazione della reward');
            });
    }

    // START: NEW JAVASCRIPT FUNCTIONS FOR PROFILES (previously added)
    let profileSkillCounter = 0; // To ensure unique names for skill inputs in the modal

    function addSkillToProfileModal() {
        const skillsContainer = document.getElementById('skillsContainer');
        const skillId = `skill-${profileSkillCounter++}`;

        const skillDiv = document.createElement('div');
        skillDiv.className = 'row g-2 mb-2 align-items-center';
        skillDiv.id = skillId;
        skillDiv.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control" name="profile_skills[${skillId}][competenza]" placeholder="Competenza" required>
            </div>
            <div class="col-md-5">
                <input type="number" class="form-control" name="profile_skills[${skillId}][livello]" placeholder="Livello (0-5)" min="0" max="5" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100" onclick="removeSkillFromProfileModal('${skillId}')">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        `;
        skillsContainer.appendChild(skillDiv);
    }

    function removeSkillFromProfileModal(id) {
        const skillToRemove = document.getElementById(id);
        if (skillToRemove) {
            skillToRemove.remove();
        }
    }

    function addProfile() {
        const form = document.getElementById('addProfileForm');
        const formData = new FormData(form);

        // Basic validation for profile name
        const profileName = formData.get('profile_name').trim();
        if (!profileName) {
            alert('Il nome del profilo è obbligatorio.');
            return;
        }

        // Basic validation for skills
        const skillInputs = document.querySelectorAll('#skillsContainer input[type="text"][name^="profile_skills"]');
        const levelInputs = document.querySelectorAll('#skillsContainer input[type="number"][name^="profile_skills"]');

        if (skillInputs.length === 0) {
            alert('Devi aggiungere almeno una skill per il profilo.');
            return;
        }

        for (let i = 0; i < skillInputs.length; i++) {
            if (!skillInputs[i].value.trim() || !levelInputs[i].value.trim()) {
                alert('Tutti i campi skill e livello devono essere compilati.');
                return;
            }
            if (parseInt(levelInputs[i].value) < 0 || parseInt(levelInputs[i].value) > 5) {
                alert('Il livello della skill deve essere tra 0 e 5.');
                return;
            }
        }

        fetch('../../api/add_profile.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Profilo aggiunto con successo!');
                    location.reload(); // Reload to show the new profile
                } else {
                    alert(data.message || 'Errore durante l\'aggiunta del profilo.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di rete o del server durante l\'aggiunta del profilo.');
            });
    }

    function deleteProfile(profileId) {
        if (!confirm('Vuoi eliminare questo profilo richiesto? Verranno eliminate anche tutte le sue skills associate.')) return;

        const formData = new FormData();
        formData.append('profile_id', profileId);
        formData.append('project_name', '<?= htmlspecialchars($projectName) ?>'); // Pass project name for context

        fetch('../../api/delete_profile.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Profilo eliminato con successo!');
                    location.reload(); // Reload to reflect changes
                } else {
                    alert(data.message || 'Errore durante l\'eliminazione del profilo.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di rete o del server durante l\'eliminazione del profilo.');
            });
    }
    // END: NEW JAVASCRIPT FUNCTIONS FOR PROFILES

    // Ensure at least one skill input is present when opening the modal for the first time
    document.addEventListener('DOMContentLoaded', function() {
        const addProfileModalElement = document.getElementById('addProfileModal');
        addProfileModalElement.addEventListener('shown.bs.modal', function () {
            const skillsContainer = document.getElementById('skillsContainer');
            if (skillsContainer.children.length === 0) {
                addSkillToProfileModal();
            }
        });
        // Clear previous inputs if modal is closed and reopened
        addProfileModalElement.addEventListener('hidden.bs.modal', function () {
            document.getElementById('addProfileForm').reset();
            document.getElementById('skillsContainer').innerHTML = ''; // Clear dynamically added skills
            profileSkillCounter = 0; // Reset counter
        });
    });
</script>
</body>
</html>