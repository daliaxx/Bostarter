<?php
session_start();
require_once '../../config/database.php';

// Controlla se è admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../projects.php');
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
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../projects.php">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <div class="navbar-nav ms-auto">
            <span class="navbar-text me-3">
                <i class="fas fa-user-shield"></i> Admin: <?= $_SESSION['user_nickname'] ?>
            </span>
            <a class="nav-link" href="../projects.php">Progetti</a>
            <a class="nav-link" href="../../auth/logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard Amministratore</h1>
            <p class="text-muted">Benvenuto, <?= $_SESSION['user_nome'] ?> <?= $_SESSION['user_cognome'] ?>!</p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Gestione Utenti</h5>
                </div>
                <div class="card-body">
                    <p>Gestisci utenti e permessi</p>
                    <button class="btn btn-primary">Gestisci Utenti</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cog"></i> Competenze</h5>
                </div>
                <div class="card-body">
                    <p>Aggiungi nuove competenze</p>
                    <button class="btn btn-primary">Gestisci Skill</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Statistiche</h5>
                </div>
                <div class="card-body">
                    <p>Visualizza statistiche</p>
                    <a href="../projects.php" class="btn btn-primary">Vai ai Progetti</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>