<?php
require_once '../config/database.php';

// Attiva errori in fase di sviluppo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controllo parametro
if (!isset($_GET['name'])) {
    die("<h3>Errore: progetto non specificato.</h3>");
}

$nomeProgetto = $_GET['name'];
$db = Database::getInstance();

// Ottieni dettagli progetto
$sql = "
    SELECT 
        p.Nome,
        p.Descrizione,
        p.Data_Inserimento,
        p.Stato,
        p.Budget,
        p.Data_Limite,
        p.Tipo,
        u.Nickname AS Creatore,
        c.Affidabilita,
        COALESCE(SUM(f.Importo), 0) AS Totale_Finanziato,
        COUNT(DISTINCT f.ID) AS Num_Finanziatori,
        foto.percorso AS Foto,
        DATEDIFF(p.Data_Limite, CURDATE()) AS Giorni_Rimanenti
    FROM PROGETTO p
    JOIN CREATORE c ON p.Email_Creatore = c.Email
    JOIN UTENTE u ON c.Email = u.Email
    LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
    LEFT JOIN FOTO foto ON p.Nome = foto.Nome_Progetto
    WHERE p.Nome = ?
    GROUP BY p.Nome, p.Descrizione, p.Data_Inserimento, p.Stato, p.Budget, p.Data_Limite, p.Tipo, u.Nickname, c.Affidabilita, foto.percorso
";

$progetto = $db->fetchOne($sql, [$nomeProgetto]);
if (!$progetto) {
    die("<h3>Progetto non trovato.</h3>");
}

// Calcoli
$percentuale = $progetto['Budget'] > 0 ? round($progetto['Totale_Finanziato'] / $progetto['Budget'] * 100, 1) : 0;
$statoClasse = $progetto['Stato'] === 'aperto' ? 'success' : 'secondary';

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($progetto['Nome']) ?> - Dettagli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1><?= htmlspecialchars($progetto['Nome']) ?></h1>
    <p class="text-muted">Creato da <strong><?= htmlspecialchars($progetto['Creatore']) ?></strong> (<?= $progetto['Affidabilita'] * 100 ?>% affidabilità)</p>

    <?php if ($progetto['Foto']): ?>
        <img src="/Bostarter/img/<?= htmlspecialchars($progetto['Foto']) ?>" class="img-fluid mb-3" style="max-height:300px; object-fit:cover;">
    <?php endif; ?>

    <p><strong>Tipo:</strong> <?= htmlspecialchars($progetto['Tipo']) ?></p>
    <p><strong>Descrizione:</strong> <?= nl2br(htmlspecialchars($progetto['Descrizione'])) ?></p>
    <p><strong>Budget:</strong> <?= Utils::formatCurrency($progetto['Budget']) ?></p>
    <p><strong>Raccolto:</strong> <?= Utils::formatCurrency($progetto['Totale_Finanziato']) ?> (<?= $percentuale ?>%)</p>
    <p><strong>Stato:</strong> <span class="badge bg-<?= $statoClasse ?>"><?= $progetto['Stato'] ?></span></p>
    <p><strong>Giorni rimanenti:</strong> <?= $progetto['Giorni_Rimanenti'] ?></p>
    <p><strong>Finanziatori:</strong> <?= $progetto['Num_Finanziatori'] ?></p>

    <a href="projects.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Torna ai Progetti</a>
</div>
</body>
</html>
