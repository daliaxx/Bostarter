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
</head>
<body>
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
</body>
</html>
