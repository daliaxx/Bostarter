<?php
require_once '../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$view = $_GET['view'] ?? 'top-creatori';
$db = Database::getInstance();

function renderTable($title, $headers, $rows) {
    echo "<h2 class='mb-4'>" . htmlspecialchars($title) . "</h2>";
    if (empty($rows)) {
        echo "<p class='text-muted'>Nessun dato disponibile.</p>";
        return;
    }
    echo "<div class='table-responsive'><table class='table table-bordered'>";
    echo "<thead><tr>";
    foreach ($headers as $h) echo "<th>" . htmlspecialchars($h) . "</th>";
    echo "</tr></thead><tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $cell) echo "<td>" . htmlspecialchars($cell) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}

$results = [];
$title = '';
$headers = [];

switch ($view) {
    case 'top-creatori':
        $title = 'Top Creatori per Affidabilità';
        $headers = ['Nickname', 'Email', 'Nr. Progetti', 'Affidabilità'];
        $results = $db->fetchAll("SELECT u.Nickname, c.Email, c.Nr_Progetti, c.Affidabilita
                                  FROM CREATORE c
                                  JOIN UTENTE u ON c.Email = u.Email
                                  ORDER BY c.Affidabilita DESC, c.Nr_Progetti DESC
                                  LIMIT 10");
        break;

    case 'progetti-crescita':
        $title = 'Progetti con Percentuale di Raccolta più Alta';
        $headers = ['Nome', 'Tipo', 'Totale Raccolto', 'Budget', '% Completamento'];
        $results = $db->fetchAll("SELECT p.Nome, p.Tipo, 
                                        COALESCE(SUM(f.Importo), 0) AS Raccolto, 
                                        p.Budget, 
                                        ROUND(COALESCE(SUM(f.Importo), 0)/p.Budget * 100, 1) AS Percentuale
                                  FROM PROGETTO p
                                  LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
                                  GROUP BY p.Nome, p.Tipo, p.Budget
                                  ORDER BY Percentuale DESC
                                  LIMIT 10");
        break;

    case 'top-finanziatori':
        $title = 'Top Finanziatori per Importo Donato';
        $headers = ['Nickname', 'Email', 'Totale Donato', 'Numero Donazioni'];
        $results = $db->fetchAll("SELECT u.Nickname, f.Email_Utente, SUM(f.Importo) AS Totale, COUNT(*) AS NumDonazioni
                                  FROM FINANZIAMENTO f
                                  JOIN UTENTE u ON f.Email_Utente = u.Email
                                  GROUP BY f.Email_Utente, u.Nickname
                                  ORDER BY Totale DESC
                                  LIMIT 10");
        break;

    default:
        header("Location: statistique.php?view=top-creatori");
        exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Statistiche</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="projects.php">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-1"></i>Progetti
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (SessionManager::isLoggedIn()): ?>
                    <?php $isAdmin = SessionManager::isAdmin(); $isCreator = SessionManager::isCreator(); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars(SessionManager::get('user_nickname', 'Utente')) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="dashboard/admin_dashboard.php"><i class="fas fa-shield-alt me-2"></i>Dashboard Admin</a></li>
                                <li><a class="dropdown-item" href="dashboard/user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                                <li><a class="dropdown-item" href="statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            <?php elseif ($isCreator): ?>
                                <li><a class="dropdown-item" href="dashboard/creator_dashboard.php"><i class="fas fa-user-cog me-2"></i>Dashboard Creatore</a></li>
                                <li><a class="dropdown-item" href="dashboard/new_project.php"><i class="fas fa-plus me-2"></i>Crea Progetto</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="dashboard/user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                                <li><a class="dropdown-item" href="statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="dashboard/user_dashboard.php"><i class="fas fa-user me-2"></i>Il mio profilo</a></li>
                                <li><a class="dropdown-item" href="statistiche.php"><i class="fas fa-chart-bar me-2"></i>Statistiche</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            <?php endif; ?>
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
<div class="container py-5">
    <h1 class="mb-4">Statistiche BOSTARTER</h1>

    <div class="btn-group mb-4" role="group">
        <a href="?view=top-creatori" class="btn btn-outline-primary <?= $view === 'top-creatori' ? 'active' : '' ?>">Top Creatori</a>
        <a href="?view=progetti-crescita" class="btn btn-outline-warning <?= $view === 'progetti-crescita' ? 'active' : '' ?>">Progetti in Crescita</a>
        <a href="?view=top-finanziatori" class="btn btn-outline-success <?= $view === 'top-finanziatori' ? 'active' : '' ?>">Top Finanziatori</a>
    </div>

    <?php renderTable($title, $headers, $results); ?>

    <a href="projects.php" class="btn btn-secondary mt-4"><i class="fas fa-arrow-left"></i> Torna ai Progetti</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
