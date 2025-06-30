<?php
/**
 * BOSTARTER - Modifica Progetto
 * File: public/dashboard/edit_project.php
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../config/database.php';
require_once '../../includes/navbar.php';

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

// START: NEW CODE FOR COMPONENTS (Hardware projects)
$components = [];
if ($project['Tipo'] === 'Hardware') {
    $components = $db->fetchAll("
        SELECT ID, Nome, Descrizione, Prezzo, Quantita
        FROM COMPONENTE
        WHERE Nome_Progetto = ?
        ORDER BY Nome
    ", [$projectName]);
}
// END: NEW CODE FOR COMPONENTS

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
        /* START: NEW CSS FOR COMPONENT SECTION */
        .component-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .component-item:hover {
            border-color: #28a745;
            background: #e8f5e8;
        }
        .component-price {
            font-weight: bold;
            color: #28a745;
        }
        .component-quantity {
            font-weight: bold;
            color: #007bff;
        }
        /* END: NEW CSS FOR COMPONENT SECTION */
    </style>
</head>
<body>

<div class="container mt-4">
    <div id="alertContainer"></div>
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
            <?php if ($project['Tipo'] === 'Hardware'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0"><i class="fas fa-microchip me-2"></i>Componenti Hardware</h4>
                    </div>
                    <div class="card-body">
                        <div class="list-group mb-3">
                            <?php if (empty($components)): ?>
                                <p>Nessun componente aggiunto a questo progetto hardware.</p>
                            <?php else: ?>
                                <?php foreach ($components as $component): ?>
                                    <div class="list-group-item list-group-item-action component-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5><i class="fas fa-cube me-1"></i><?= htmlspecialchars($component['Nome']) ?></h5>
                                            <button class="btn btn-danger btn-sm" onclick="deleteComponent(<?= $component['ID'] ?>)">
                                                <i class="fas fa-trash-alt"></i> Elimina Componente
                                            </button>
                                        </div>
                                        <p class="mb-2"><?= htmlspecialchars($component['Descrizione']) ?></p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <span class="component-price">
                                                    <i class="fas fa-euro-sign me-1"></i>Prezzo: €<?= number_format($component['Prezzo'], 2) ?>
                                                </span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="component-quantity">
                                                    <i class="fas fa-boxes me-1"></i>Quantità: <?= htmlspecialchars($component['Quantita']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addComponentModal">
                            <i class="fas fa-plus me-2"></i>Aggiungi Nuovo Componente
                        </button>
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

<div class="modal fade" id="addComponentModal" tabindex="-1" aria-labelledby="addComponentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="addComponentModalLabel">Aggiungi Nuovo Componente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addComponentForm">
                <div class="modal-body">
                    <input type="hidden" name="project_name" value="<?= htmlspecialchars($projectName) ?>">
                    <div class="mb-3">
                        <label for="componentName" class="form-label">Nome Componente</label>
                        <input type="text" class="form-control" id="componentName" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="componentDescription" class="form-label">Descrizione Componente</label>
                        <textarea class="form-control" id="componentDescription" name="descrizione" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="componentPrice" class="form-label">Prezzo (€)</label>
                        <input type="number" step="0.01" class="form-control" id="componentPrice" name="prezzo" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="componentQuantity" class="form-label">Quantità</label>
                        <input type="number" class="form-control" id="componentQuantity" name="quantita" required min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-warning" onclick="addComponent()">Aggiungi Componente</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Variabili globali
    window.competenzeDisponibili = [];
    let profileSkillCounter = 0;

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

    // Funzioni reward
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
        formData.append('project_name', '<?= htmlspecialchars($projectName) ?>');
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

    // Funzioni profili per progetti Software con controllo anti-duplicati

    // Funzione per validazione real-time
    function validateSkillSelection() {
        const skillSelects = document.querySelectorAll('#skillsContainer select[name^="profile_skills"]');
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
        const warningDiv = document.getElementById('duplicateWarning');
        if (duplicates.length > 0) {
            if (!warningDiv) {
                const warning = document.createElement('div');
                warning.id = 'duplicateWarning';
                warning.className = 'alert alert-warning mt-2';
                warning.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attenzione:</strong> Competenze duplicate rilevate: ${duplicates.join(', ')}
                `;
                document.getElementById('skillsContainer').parentNode.appendChild(warning);
            } else {
                warningDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attenzione:</strong> Competenze duplicate rilevate: ${duplicates.join(', ')}
                `;
            }
        } else {
            if (warningDiv) {
                warningDiv.remove();
            }
        }
    }

    function addSkillToProfileModal() {
        const skillsContainer = document.getElementById('skillsContainer');
        const skillId = `skill-${profileSkillCounter++}`;

        const skillDiv = document.createElement('div');
        skillDiv.className = 'row g-2 mb-2 align-items-center';
        skillDiv.id = skillId;
        skillDiv.innerHTML = `
            <div class="col-md-5">
                <select class="form-control competenza-select"
                        name="profile_skills[${skillId}][competenza]"
                        onchange="validateSkillSelection()"
                        required>
                    <option value="">Caricamento...</option>
                </select>
            </div>
            <div class="col-md-5">
                <input type="number"
                       class="form-control"
                       name="profile_skills[${skillId}][livello]"
                       placeholder="Livello (0-5)"
                       min="0" max="5"
                       required>
            </div>
            <div class="col-md-2">
                <button type="button"
                        class="btn btn-outline-danger w-100"
                        onclick="removeSkillFromProfileModal('${skillId}')">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        `;
        skillsContainer.appendChild(skillDiv);

        // Popola il nuovo select appena creato
        const newSelect = skillDiv.querySelector('.competenza-select');
        if (window.competenzeDisponibili && Array.isArray(window.competenzeDisponibili) && window.competenzeDisponibili.length > 0) {
            newSelect.innerHTML = '<option value="">Seleziona competenza...</option>';
            window.competenzeDisponibili.forEach(skill => {
                newSelect.innerHTML += `<option value="${skill.Competenza}">${skill.Competenza}</option>`;
            });
        }
    }

    function removeSkillFromProfileModal(id) {
        const skillToRemove = document.getElementById(id);
        if (skillToRemove) {
            skillToRemove.remove();
            // Rimuovi warning dopo rimozione
            validateSkillSelection();
        }
    }

    // Funzione addProfile con controllo anti-duplicati
    function addProfile() {
        const form = document.getElementById('addProfileForm');
        const formData = new FormData(form);

        // Basic validation for profile name
        const profileName = formData.get('profile_name').trim();
        if (!profileName) {
            alert('Il nome del profilo è obbligatorio.');
            return;
        }

        // Controllo duplicati nome profilo
        const existingProfileNames = Array.from(document.querySelectorAll('.profile-item h5'))
            .map(h5 => h5.textContent.trim().toLowerCase());

        if (existingProfileNames.includes(profileName.toLowerCase())) {
            alert(
                `ERRORE: Esiste già un profilo con il nome "${profileName}".\n\n` +
                `Scegli un nome diverso per il nuovo profilo.`
            );
            return;
        }

        // Basic validation for skills
        const skillInputs = document.querySelectorAll('#skillsContainer select[name^="profile_skills"]');
        const levelInputs = document.querySelectorAll('#skillsContainer input[type="number"][name^="profile_skills"]');

        if (skillInputs.length === 0) {
            alert('Devi aggiungere almeno una skill per il profilo.');
            return;
        }

        // Controllo anti-duplicati
        const competenzeSelezionate = [];
        const competenzeDuplicate = [];

        for (let i = 0; i < skillInputs.length; i++) {
            const competenza = skillInputs[i].value.trim();
            const livello = parseInt(levelInputs[i].value);

            // Validazione campi base
            if (!competenza || !livello) {
                alert('Tutti i campi skill e livello devono essere compilati.');
                return;
            }
            if (livello < 0 || livello > 5) {
                alert('Il livello della skill deve essere tra 0 e 5.');
                return;
            }

            // Controllo duplicati: stessa competenza = errore
            if (competenzeSelezionate.includes(competenza)) {
                if (!competenzeDuplicate.includes(competenza)) {
                    competenzeDuplicate.push(competenza);
                }
            } else {
                competenzeSelezionate.push(competenza);
            }
        }

        // Se ci sono duplicati, blocca e mostra errore
        if (competenzeDuplicate.length > 0) {
            alert(
                `ERRORE: Le seguenti competenze sono duplicate:\n\n` +
                `${competenzeDuplicate.join(', ')}\n\n` +
                `Ogni competenza può essere richiesta solo UNA volta per profilo.\n` +
                `Rimuovi i duplicati prima di salvare.`
            );
            return;
        }

        // Se tutto ok, invia al server
        fetch('../../api/manage_profile.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Profilo aggiunto con successo!');
                    location.reload();
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
        formData.append('project_name', '<?= htmlspecialchars($projectName) ?>');
        formData.append('action', 'delete');

        fetch('../../api/manage_profile.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Profilo eliminato con successo!');
                    location.reload();
                } else {
                    alert(data.message || 'Errore durante l\'eliminazione del profilo.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di rete o del server durante l\'eliminazione del profilo.');
            });
    }

    // Funzioni componenti per progetti Hardware
    function addComponent() {
        const form = document.getElementById('addComponentForm');
        const formData = new FormData(form);

        // Basic validation
        const nome = formData.get('nome').trim();
        const descrizione = formData.get('descrizione').trim();
        const prezzo = parseFloat(formData.get('prezzo'));
        const quantita = parseInt(formData.get('quantita'));

        if (!nome || !descrizione) {
            alert('Nome e descrizione sono obbligatori.');
            return;
        }

        if (prezzo <= 0) {
            alert('Il prezzo deve essere maggiore di zero.');
            return;
        }

        if (quantita <= 0) {
            alert('La quantità deve essere maggiore di zero.');
            return;
        }

        // Prepare form data with correct parameter names
        const apiFormData = new FormData();
        apiFormData.append('action', 'add_component');
        apiFormData.append('nome_progetto', formData.get('project_name'));
        apiFormData.append('nome_componente', nome);
        apiFormData.append('descrizione', descrizione);
        apiFormData.append('prezzo', prezzo);
        apiFormData.append('quantita', quantita);

        fetch('../../api/manage_components.php', {
            method: 'POST',
            body: apiFormData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Componente aggiunto con successo!');
                    location.reload();
                } else {
                    alert(data.message || 'Errore durante l\'aggiunta del componente.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di rete o del server durante l\'aggiunta del componente.');
            });
    }

    function deleteComponent(componentId) {
        if (!confirm('Vuoi eliminare questo componente?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_component');
        formData.append('component_id', componentId);

        fetch('../../api/manage_components.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Componente eliminato con successo!');
                    location.reload();
                } else {
                    alert(data.message || 'Errore durante l\'eliminazione del componente.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di rete o del server durante l\'eliminazione del componente.');
            });
    }

    // Inizializzazione e event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Carica competenze quando si apre il modal per aggiungere profili
        const addProfileModalElement = document.getElementById('addProfileModal');
        if (addProfileModalElement) {
            addProfileModalElement.addEventListener('show.bs.modal', function () {
                if (!window.competenzeDisponibili || window.competenzeDisponibili.length === 0) {
                    loadCompetenze();
                }
                const skillsContainer = document.getElementById('skillsContainer');
                if (skillsContainer && skillsContainer.children.length === 0) {
                    addSkillToProfileModal();
                }
            });

            // Clear previous inputs if modal is closed and reopened
            addProfileModalElement.addEventListener('hidden.bs.modal', function () {
                document.getElementById('addProfileForm').reset();
                document.getElementById('skillsContainer').innerHTML = '';
                profileSkillCounter = 0;

                // Rimuovi warning duplicati quando chiudi modal
                const warningDiv = document.getElementById('duplicateWarning');
                if (warningDiv) {
                    warningDiv.remove();
                }
            });
        }
    });
</script>
</body>
</html>