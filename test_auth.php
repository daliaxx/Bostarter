<?php
echo "<h2>🔍 Test Auth Debug Completo</h2>";

// Test 1: File esistenti ✅ (già funziona)
echo "<h3>1. File esistenti: ✅</h3>";

// Test 2: Chiamata POST simulata
echo "<h3>2. Test Chiamata POST a auth/login.php:</h3>";

// Simuliamo una chiamata POST
if (isset($_POST['test_login'])) {
    echo "<p>📡 Chiamata POST ricevuta, redirect a auth/login.php...</p>";

    // Simula i dati che manderebbe il form
    $_POST['email'] = 'dalia.barone@email.com';  // Utente dal tuo database
    $_POST['password'] = 'password123';           // Password dal tuo database
    // Includi il file login per vedere se funziona
    try {
        ob_start();
        include 'auth/login.php';
        $output = ob_get_clean();
        echo "<p>✅ auth/login.php eseguito</p>";
        echo "<pre>Output: " . htmlspecialchars($output) . "</pre>";
    } catch (Exception $e) {
        echo "<p>❌ Errore auth/login.php: " . $e->getMessage() . "</p>";
    }
} else {
    // Form per testare POST
    echo '<form method="POST">
            <button type="submit" name="test_login" class="btn btn-primary">🧪 Test Login POST</button>
          </form>';
}

echo "<h3>3. Test Chiamata AJAX (JavaScript):</h3>";
?>

<script>
    // Test AJAX come fa index.html
    function testAjaxLogin() {
        console.log("🚀 Iniziando test AJAX...");

        const formData = new FormData();
        formData.append('email', 'dalia.barone@email.com');
        formData.append('password', 'password123');

        fetch('auth/login.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                console.log("📡 Response status:", response.status);
                console.log("📡 Response headers:", response.headers);
                return response.text(); // Prima prendi come testo
            })
            .then(data => {
                console.log("📄 Response data:", data);
                document.getElementById('ajaxResult').innerHTML = '<pre>' + data + '</pre>';

                // Prova a parsare come JSON
                try {
                    const jsonData = JSON.parse(data);
                    console.log("✅ JSON valido:", jsonData);
                } catch (e) {
                    console.log("❌ Non è JSON valido:", e.message);
                }
            })
            .catch(error => {
                console.log("❌ Errore AJAX:", error);
                document.getElementById('ajaxResult').innerHTML = '<p style="color: red;">Errore: ' + error.message + '</p>';
            });
    }
</script>

<button onclick="testAjaxLogin()" style="background: #007bff; color: white; padding: 10px; border: none; border-radius: 5px;">
    🧪 Test AJAX Login
</button>

<div id="ajaxResult" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
    <p>Clicca "Test AJAX Login" per vedere il risultato...</p>
</div>

<h3>4. Debug Console:</h3>
<p>Apri Developer Tools (F12) → Console per vedere i log dettagliati</p>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>