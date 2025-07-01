<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/navbar.php';

// Controlla se è admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../projects/projects.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - BOSTARTER</title>
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
        .skill-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            margin: 0.25rem;
            display: inline-block;
            font-size: 0.9rem;
        }
        .skill-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            background: #f8f9fa;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard Amministratore</h1>
            <p class="text-muted">Benvenuto, <?= $_SESSION['user_nome'] ?> <?= $_SESSION['user_cognome'] ?>!</p>
        </div>
    </div>

    <!-- Alert per messaggi -->
    <div id="alertContainer"></div>

    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-cog"></i> Gestione Competenze</h5>
                    <span class="badge bg-info" id="skillCount">Caricamento...</span>
                </div>
                <div class="card-body">
                    <!-- Form per aggiungere nuova competenza -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">
                                <i class="fas fa-plus-circle me-2"></i>Aggiungi Nuova Competenza
                            </h6>
                            <form id="addCompetenceForm">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-brain"></i>
                                    </span>
                                    <input type="text"
                                           class="form-control"
                                           id="competenceName"
                                           placeholder="Inserisci nome competenza (es. Blockchain, DevOps...)"
                                           maxlength="100"
                                           required>
                                    <button type="submit" class="btn btn-success" id="addBtn">
                                        <i class="fas fa-plus me-1"></i>Aggiungi
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Verrà creata automaticamente con tutti i livelli da 1 a 5
                                </small>
                            </form>
                        </div>
                    </div>

                    <hr>

                    <!-- Lista competenze esistenti -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-primary mb-0">
                                    <i class="fas fa-list me-2"></i>Competenze Disponibili nel Sistema
                                </h6>
                                <button class="btn btn-outline-primary btn-sm" onclick="loadSkills()">
                                    <i class="fas fa-sync-alt me-1"></i>Aggiorna
                                </button>
                            </div>

                            <div id="skillsList" class="skill-list">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Caricamento competenze...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Statistiche</h5>
                </div>
                <div class="card-body">
                    <p>Visualizza statistiche della piattaforma</p>
                    <a href="../statistiche.php" class="btn btn-primary">Vai alle Statistiche</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

    // Carica competenze all'avvio
    document.addEventListener('DOMContentLoaded', function() {
        loadSkills();
    });

    // Carica lista competenze
    function loadSkills() {
        const skillsList = document.getElementById('skillsList');
        const skillCount = document.getElementById('skillCount');

        skillsList.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Caricamento...</div>';

        fetch('../../api/manage_skill.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_all_skills'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySkills(data.skills);
                    skillCount.textContent = `${data.count} competenze`;
                } else {
                    skillsList.innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>${data.message}</div>`;
                    skillCount.textContent = 'Errore';
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                skillsList.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Errore di connessione</div>';
                skillCount.textContent = 'Errore';
            });
    }

    // Mostra competenze
    function displaySkills(skills) {
        const skillsList = document.getElementById('skillsList');

        if (skills.length === 0) {
            skillsList.innerHTML = '<div class="text-muted text-center"><i class="fas fa-inbox me-2"></i>Nessuna competenza trovata</div>';
            return;
        }

        let html = '';
        skills.forEach(skill => {
            html += `<span class="skill-item">
            <i class="fas fa-brain me-1 text-primary"></i>
            ${escapeHtml(skill.Competenza)}
            <small class="text-muted">(Lv. 1-5)</small>
        </span>`;
        });

        skillsList.innerHTML = html;
    }

    // per aggiungere competenza
    document.getElementById('addCompetenceForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const competenceName = document.getElementById('competenceName').value.trim();
        const addBtn = document.getElementById('addBtn');

        if (!competenceName) {
            showAlert('Inserisci il nome della competenza', 'warning');
            return;
        }

        // Disabilita pulsante durante invio
        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Aggiungendo...';

        fetch('../../api/manage_skill.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_new_competence&competenza=${encodeURIComponent(competenceName)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('competenceName').value = '';
                    loadSkills(); // Ricarica lista
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                showAlert('Errore di connessione durante l\'aggiunta', 'danger');
            })
            .finally(() => {
                // Riabilita pulsante
                addBtn.disabled = false;
                addBtn.innerHTML = '<i class="fas fa-plus me-1"></i>Aggiungi';
            });
    });

    // Mostra alert
    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertId = 'alert_' + Date.now();

        const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

        alertContainer.insertAdjacentHTML('beforeend', alertHtml);

        // Rimuovi automaticamente dopo 5 secondi
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }

    // Escape HTML per sicurezza
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
</script>
</body>
</html>