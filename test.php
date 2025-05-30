<?php
/**
 * Test di connessione al database
 */

require_once '../config/config.php';

echo "<h1>Test BOSTARTER - Database Setup</h1>";

try {
    // Test connessione database
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ <strong>Connessione database riuscita!</strong><br><br>";
    
    // Test tabelle
    echo "<h2>Test Tabelle</h2>";
    
    $tables = [
        'UTENTE',
        'CREATORE', 
        'AMMINISTRATORE',
        'PROGETTO',
        'SKILL',
        'FINANZIAMENTO'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "✅ Tabella $table: {$result['count']} records<br>";
        } catch (Exception $e) {
            echo "❌ Errore tabella $table: " . $e->getMessage() . "<br>";
        }
    }
    
    // Test stored procedures
    echo "<h2>Test Stored Procedures</h2>";
    
    try {
        $stmt = $pdo->prepare("CALL AutenticaUtente('dalia.barone@email.com', 'password123')");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "✅ Stored Procedure AutenticaUtente: " . $result['Messaggio'] . "<br>";
    } catch (Exception $e) {
        echo "❌ Errore Stored Procedure: " . $e->getMessage() . "<br>";
    }
    
    // Test views
    echo "<h2>Test Views</h2>";
    
    $views = [
        'classifica_affidabilita',
        'ProgettiQuasiCompletati', 
        'ClassificaFinanziatori'
    ];
    
    foreach ($views as $view) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $view");
            $result = $stmt->fetch();
            echo "✅ View $view: {$result['count']} records<br>";
        } catch (Exception $e) {
            echo "❌ Errore view $view: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>✅ Database configurato correttamente!</h2>";
    echo "<p>Puoi procedere al prossimo step.</p>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Errore connessione database:</strong><br>";
    echo $e->getMessage();
    echo "<br><br><strong>Verifica:</strong>";
    echo "<ul>";
    echo "<li>XAMPP/MAMP sia avviato</li>";
    echo "<li>MySQL sia in esecuzione</li>";
    echo "<li>Hai importato il file database.sql</li>";
    echo "<li>Credenziali database in config.php siano corrette</li>";
    echo "</ul>";
}
?>
