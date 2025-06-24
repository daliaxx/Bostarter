<?php
/**
 * BOSTARTER - Modifica Progetto
 * File: public/dashboard/edit_project.php
 */

require_once '../../config/database.php';

SessionManager::requireLogin('../../index.html');
SessionManager::requireCreator();

$userEmail = SessionManager::getUserEmail();
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
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Progetto - BOSTARTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../projects.php">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="creator_dashboard.php">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-edit me-2"></i>Modifica Progetto: <?= htmlspecialchars($project['Nome']) ?></h4>
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
                            <label for="descrizione" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="descrizione" name="descrizione" rows="5" required><?= htmlspecialchars($project['Descrizione']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="budget" class="form-label">Budget (€)</label>
                                    <input type="number" class="form-control" id="budget" name="budget" min="1" step="0.01" value="<?= $project['Budget'] ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="data_limite" class="form-label">Data Limite</label>
                                    <input type="date" class="form-control" id="data_limite" name="data_limite" value="<?= $project['Data_Limite'] ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($project['Tipo']) ?>" disabled>
                            <small class="form-text text-muted">Il tipo di progetto non può essere modificato.</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="creator_dashboard.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-1"></i>Annulla
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Salva Modifiche
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Gestione Reward -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-gift me-2"></i>Reward</h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addRewardModal">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($rewards)): ?>
                        <p class="text-muted">Nessuna reward ancora aggiunta.</p>
                    <?php else: ?>
                        <?php foreach ($rewards as $reward): ?>
                            <div class="card mb-2">
                                <div class="card-body p-3">
                                    <h6 class="card-title"><?= htmlspecialchars($reward['Codice']) ?></h6>
                                    <p class="card-text small"><?= htmlspecialchars($reward['Descrizione']) ?></p>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteReward('<?= $reward['Codice'] ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Aggiungi Reward -->
<div class="modal fade" id="addRewardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Aggiungi Reward</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRewardForm">
                    <div class="mb-3">
                        <label for="rewardCodice" class="form-label">Codice Reward</label>
                        <input type="text" class="form-control" id="rewardCodice" name="codice" required>
                    </div>
                    <div class="mb-3">
                        <label for="rewardDescrizione" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="rewardDescrizione" name="descrizione" rows="3" required></textarea>
                    </div>
                    <input type="hidden" name="nome_progetto" value="<?= htmlspecialchars($projectName) ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="addReward()">
                    <i class="fas fa-plus me-1"></i>Aggiungi
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function addReward() {
        const form = document.getElementById('addRewardForm');
        const formData = new FormData(form);

        fetch('../../api/add_reward.php', {
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
        formData.append('codice', codice);

        fetch('../../api/delete_reward.php', {
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
</script>
</body>
</html>