<?php
echo "<h2>🔍 Test MAMP - Porta 8889</h2>";

$host = 'localhost:8889';
$dbname = 'BOSTARTER';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    echo "✅ CONNESSO!<br>";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM PROGETTO");
    $result = $stmt->fetch();
    echo "✅ Database funziona - Progetti: " . $result['count'] . "<br>";

    echo "<br><strong>🎉 Backend completamente funzionante!</strong>";

} catch (PDOException $e) {
    echo "❌ Errore: " . $e->getMessage() . "<br>";
}
?>