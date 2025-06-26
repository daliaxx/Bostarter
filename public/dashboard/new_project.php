<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_email']) || $_SESSION['is_creator'] != 1) {
    die("Accesso non autorizzato.");
}

$db = Database::getInstance();

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descrizione = $_POST['descrizione'];
    $budget = floatval($_POST['budget']);
    $data_limite = $_POST['data_limite'];
    $tipo = $_POST['tipo'];
    $email = $_SESSION['user_email'];

    if (strtotime($data_limite) < strtotime(date('Y-m-d'))) {
        $error_message = "La data limite non può essere nel passato.";
    } else {
        // Carica immagine
        $immaginePath = null;
        if (isset($_FILES['immagine']) && $_FILES['immagine']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . "_" . basename($_FILES['immagine']['name']);
            $tmpName = $_FILES['immagine']['tmp_name'];
            $uploadDir = __DIR__ . '/../../img/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $destPath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $destPath)) {
                $immaginePath = 'img/' . $fileName;
            }
        }

        try {
            $oggi = date('Y-m-d');
            $stato = 'aperto';

            // Inserisci progetto
            $db->callStoredProcedure('InserisciProgetto', [
                $nome,
                $descrizione,
                $oggi,
                $budget,
                $data_limite,
                $stato,
                $email
            ]);

            // Inserisci foto se caricata
            if ($immaginePath) {
                $db->callStoredProcedure('InserisciFoto', [
                    $immaginePath,
                    $nome
                ]);
            }

            echo "Progetto inserito con successo.";

            // Redirect
            header("Location: creator_dashboard.php");
            exit;

        } catch (Exception $e) {
            $error_message = "Errore durante l'inserimento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Nuovo Progetto - BOSTARTER</title>
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
        body {
            background-color: #f8f9fa;
        }
        .form-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .form-header {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
            padding: 20px;
            margin: -30px -30px 30px -30px; /* Negativo per estendersi oltre il card */
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            text-align: center;
        }
        .form-header h2 {
            margin-bottom: 0;
        }
        .btn-primary-custom {
            background-color: #007bff;
            border-color: #007bff;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .btn-primary-custom:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../projects.php">
            <i class="fas fa-rocket me-2"></i>BOSTARTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../projects.php">
                        <i class="fas fa-project-diagram me-1"></i>Progetti
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['user_nickname'] ?? 'Utente') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($_SESSION['is_admin'] ?? false): ?>
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


<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-plus-circle me-2"></i>Crea un Nuovo Progetto</h2>
            <p class="lead mb-0">Riempi i campi sottostanti per lanciare la tua idea.</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger mb-3" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nome" class="form-label"><i class="fas fa-file-signature me-1"></i>Nome progetto:</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>

            <div class="mb-3">
                <label for="descrizione" class="form-label"><i class="fas fa-align-left me-1"></i>Descrizione:</label>
                <textarea class="form-control" id="descrizione" name="descrizione" rows="5" required></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="budget" class="form-label"><i class="fas fa-euro-sign me-1"></i>Budget richiesto (€):</label>
                    <input type="number" class="form-control" id="budget" name="budget" min="1" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="data_limite" class="form-label"><i class="fas fa-calendar-alt me-1"></i>Data limite:</label>
                    <input type="date" class="form-control" id="data_limite" name="data_limite" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="tipo" class="form-label"><i class="fas fa-lightbulb me-1"></i>Tipo di progetto:</label>
                <select class="form-select" id="tipo" name="tipo" required>
                    <option value="Hardware">Hardware</option>
                    <option value="Software">Software</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="immagine" class="form-label"><i class="fas fa-image me-1"></i>Immagine progetto:</label>
                <input type="file" class="form-control" id="immagine" name="immagine" accept="image/*">
                <small class="form-text text-muted">Carica un'immagine rappresentativa del tuo progetto.</small>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg btn-primary-custom">
                    <i class="fas fa-rocket me-2"></i>Crea Progetto
                </button>
                <a href="creator_dashboard.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>