<?php
/**
 * BOSTARTER - Navbar Unificata
 * File: includes/navbar.php
 */

// Determina il percorso base in base alla posizione del file che include la navbar
$currentFile = $_SERVER['PHP_SELF'];
$isInPublic = strpos($currentFile, '/public/') !== false;
$isInDashboard = strpos($currentFile, '/dashboard/') !== false;
$isInProjects = strpos($currentFile, '/projects/') !== false;
$isInAuth = strpos($currentFile, '/auth/') !== false;

// Calcola i percorsi relativi
if ($isInDashboard) {
    $basePath = '../';
    $projectsPath = '../projects/projects.php';
    $indexPath = '../../index.html';
    $logoutPath = '../../auth/logout.php';
} elseif ($isInProjects) {
    $basePath = '../';
    $projectsPath = 'projects.php';
    $indexPath = '../../index.html';
    $logoutPath = '../../auth/logout.php';
} elseif ($isInPublic) {
    $basePath = '';
    $projectsPath = 'projects/projects.php';
    $indexPath = '../index.html';
    $logoutPath = '../auth/logout.php';
} else {
    // Default per altri casi
    $basePath = '';
    $projectsPath = 'projects/projects.php';
    $indexPath = 'index.html';
    $logoutPath = 'auth/logout.php';
}

// Verifica stato utente
$isLoggedIn = SessionManager::isLoggedIn();
$isCreator = SessionManager::isCreator();
$isAdmin = SessionManager::isAdmin();
$userNickname = $isLoggedIn ? SessionManager::get('user_nickname', 'Utente') : 'Utente';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $projectsPath ?>">
            <i class="fas fa-crown me-2"></i>BOSTARTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $projectsPath ?>">
                        <i class="fas fa-project-diagram me-1"></i>Progetti
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($userNickname) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?= $basePath ?>dashboard/admin_dashboard.php"><i class="fas fa-shield-alt me-2"></i>Dashboard Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php elseif ($isCreator): ?>
                                <li><a class="dropdown-item" href="<?= $basePath ?>dashboard/creator_dashboard.php"><i class="fas fa-user-cog me-2"></i>Dashboard Creatore</a></li>
                                <li><a class="dropdown-item" href="<?= $basePath ?>dashboard/new_project.php"><i class="fas fa-plus me-2"></i>Crea Progetto</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= $basePath ?>dashboard/user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                            <li><a class="dropdown-item" href="<?= $basePath ?>statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $logoutPath ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $indexPath ?>"><i class="fas fa-sign-in-alt me-1"></i>Accedi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $indexPath ?>"><i class="fas fa-user-plus me-1"></i>Registrati</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>