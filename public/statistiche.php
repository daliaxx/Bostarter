<?php
require_once '../config/database.php';
require_once '../includes/navbar.php';

SessionManager::start();
$isLoggedIn = SessionManager::isLoggedIn();
$isCreator = SessionManager::isCreator();
$isAdmin = SessionManager::isAdmin();

$view = $_GET['view'] ?? 'top-creatori';
$db = Database::getInstance();

function renderTable($title, $headers, $rows, $icon) {
    echo "<div class='card'>";
    echo "<div class='card-header bg-primary text-white'>";
    echo "<h4 class='mb-0'><i class='fas fa-{$icon} me-2'></i>" . htmlspecialchars($title) . "</h4>";
    echo "</div>";
    echo "<div class='card-body'>";

    if (empty($rows)) {
        echo "<div class='text-center py-4'>";
        echo "<i class='fas fa-chart-bar fa-3x text-muted mb-3'></i>";
        echo "<p class='text-muted'>Nessun dato disponibile per questa statistica.</p>";
        echo "</div>";
        echo "</div></div>";
        return;
    }

    echo "<div class='table-responsive'>";
    echo "<table class='table table-hover'>";
    echo "<thead class='table-light'><tr>";
    echo "<th>#</th>";
    foreach ($headers as $h) echo "<th>" . htmlspecialchars($h) . "</th>";
    echo "</tr></thead><tbody>";

    $position = 1;
    foreach ($rows as $row) {
        echo "<tr>";
        // Colonna posizione
        echo "<td>";
        if ($position <= 3) {
            $medal = $position == 1 ? 'gold' : ($position == 2 ? 'silver' : '#cd7f32');
            echo "<span class='badge me-2' style='background-color: {$medal}; color: white;'>{$position}</span>";
        } else {
            echo "<span class='badge bg-secondary me-2'>{$position}</span>";
        }
        echo "</td>";
        // Colonne dati, usando i nomi delle colonne
        foreach ($headers as $colName) {
            $value = $row[$colName] ?? '';
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
        $position++;
    }
    echo "</tbody></table>";
    echo "</div>";
    echo "</div></div>";
}

$results = [];
$title = '';
$headers = [];
$icon = '';

switch ($view) {
    case 'top-creatori':
        $title = 'Top Creatori per Affidabilità';
        $headers = ['Nickname', 'Affidabilita'];
        $results = $db->fetchAll("SELECT Nickname, Affidabilita FROM classifica_affidabilita");
        break;

    case 'progetti-crescita':
        $title = 'Progetti con Percentuale di Raccolta più Alta';
        $headers = ['Nome','DifferenzaResidua'];
        $results = $db->fetchAll("SELECT Nome, DifferenzaResidua FROM ProgettiQuasiCompletati");
        break;

    case 'top-finanziatori':
        $title = 'Top Finanziatori per Importo Donato';
        $headers = ['Nickname','Totale'];
        $results = $db->fetchAll("SELECT Nickname, Totale FROM ClassificaFinanziatori");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche - BOSTARTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
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
        .stats-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        .table th {
            border-top: none;
            font-weight: 600;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .back-btn {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="stats-header">
    <div class="container text-center">
        <h1 class="display-5 fw-bold">
            <i class="fas fa-chart-bar me-2"></i>Statistiche BOSTARTER
        </h1>
        <p class="lead">Analizza le performance della piattaforma</p>
    </div>
</div>

<div class="container">
    <!-- nav tabelle -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills justify-content-center">
                <li class="nav-item">
                    <a href="?view=top-creatori" class="nav-link <?= $view === 'top-creatori' ? 'active' : '' ?>">
                        <i class="fas fa-trophy me-2"></i>Top Creatori
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?view=progetti-crescita" class="nav-link <?= $view === 'progetti-crescita' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line me-2"></i>Progetti in Crescita
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?view=top-finanziatori" class="nav-link <?= $view === 'top-finanziatori' ? 'active' : '' ?>">
                        <i class="fas fa-users me-2"></i>Top Finanziatori
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tabella Statistiche -->
    <div class="row">
        <div class="col-12">
            <?php renderTable($title, $headers, $results, $icon); ?>
        </div>
    </div>

    <!-- Pulsante Ritorno -->
    <div class="row mt-4 mb-5">
        <div class="col-12 text-center">
            <a href="projects/projects.php" class="btn btn-outline-primary back-btn">
                <i class="fas fa-arrow-left me-2"></i>Torna ai Progetti
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>