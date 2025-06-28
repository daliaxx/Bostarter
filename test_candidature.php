<?php
/**
 * Test Candidature - BOSTARTER
 * File di test per verificare il funzionamento delle candidature
 */

require_once 'config/database.php';

echo "<h1>Test Candidature BOSTARTER</h1>";

try {
    $db = Database::getInstance();
    
    echo "<h2>✅ Database connesso</h2>";
    
    // Test 1: Verifica stored procedures
    echo "<h3>Test 1: Verifica Stored Procedures</h3>";
    
    // Test InserisciCandidatura
    try {
        $stmt = $db->callStoredProcedure('InserisciCandidatura', ['test@email.com', 1]);
        echo "✅ Stored procedure InserisciCandidatura OK<br>";
    } catch (Exception $e) {
        echo "❌ Errore InserisciCandidatura: " . $e->getMessage() . "<br>";
    }
    
    // Test AccettaCandidatura
    try {
        $stmt = $db->callStoredProcedure('AccettaCandidatura', [1, 1]);
        echo "✅ Stored procedure AccettaCandidatura OK<br>";
    } catch (Exception $e) {
        echo "❌ Errore AccettaCandidatura: " . $e->getMessage() . "<br>";
    }
    
    // Test 2: Verifica tabelle
    echo "<h3>Test 2: Verifica Tabelle</h3>";
    
    $tables = ['UTENTE', 'PROGETTO', 'PROFILO', 'CANDIDATURA', 'SKILL', 'SKILL_CURRICULUM'];
    
    foreach ($tables as $table) {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
            echo "✅ Tabella $table: " . $result['count'] . " record<br>";
        } catch (Exception $e) {
            echo "❌ Errore tabella $table: " . $e->getMessage() . "<br>";
        }
    }
    
    // Test 3: Verifica dati di esempio
    echo "<h3>Test 3: Verifica Dati di Esempio</h3>";
    
    $utenti = $db->fetchAll("SELECT Email, Nickname FROM UTENTE LIMIT 3");
    echo "Utenti trovati: " . count($utenti) . "<br>";
    
    $progetti = $db->fetchAll("SELECT Nome, Tipo, Stato FROM PROGETTO LIMIT 3");
    echo "Progetti trovati: " . count($progetti) . "<br>";
    
    $profili = $db->fetchAll("SELECT ID, Nome, Nome_Progetto FROM PROFILO LIMIT 3");
    echo "Profili trovati: " . count($profili) . "<br>";
    
    // Test 4: Verifica candidature esistenti
    echo "<h3>Test 4: Verifica Candidature</h3>";
    
    $candidature = $db->fetchAll("
        SELECT c.ID, c.Email_Utente, c.Esito, pr.Nome as Profilo, p.Nome as Progetto
        FROM CANDIDATURA c
        JOIN PROFILO pr ON c.ID_Profilo = pr.ID
        JOIN PROGETTO p ON pr.Nome_Progetto = p.Nome
        LIMIT 5
    ");
    
    echo "Candidature trovate: " . count($candidature) . "<br>";
    foreach ($candidature as $cand) {
        $esito = $cand['Esito'] === null ? 'In attesa' : ($cand['Esito'] ? 'Accettata' : 'Rifiutata');
        echo "- ID: {$cand['ID']}, Utente: {$cand['Email_Utente']}, Profilo: {$cand['Profilo']}, Progetto: {$cand['Progetto']}, Esito: $esito<br>";
    }
    
    echo "<h2>✅ Test completati</h2>";
    
} catch (Exception $e) {
    echo "<h2>❌ Errore generale: " . $e->getMessage() . "</h2>";
}
?> 