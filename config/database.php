<?php
/**
 * BOSTARTER - Configurazione Database Unificata
 * File: config/database.php
 */

// Configurazione database - MODIFICA SECONDO IL TUO SETUP
define('DB_HOST', 'localhost');  // Per MAMP usa :8889, per XAMPP usa solo 'localhost'
define('DB_NAME', 'BOSTARTER');
define('DB_USER', 'root');
define('DB_PASS', 'root');  // Per MAMP usa 'root', per XAMPP spesso è vuoto ''
define('DB_CHARSET', 'utf8mb4');

// Configurazione applicazione
define('DEBUG_MODE', true);  // Cambia a false in produzione

// Classe principale per gestire il database
class Database {
    private static $instance = null;
    private $conn = null;

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);

            /* Failsafe: chiude i progetti la cui data è passata */
                $this->conn->exec("
                    UPDATE PROGETTO
                    SET Stato = 'scaduto'
                    WHERE Stato      = 'aperto'
                    AND Data_Limite < CURDATE()
                ");


            if (DEBUG_MODE) {
                error_log("✅ Database connesso: " . DB_HOST . "/" . DB_NAME);
            }

        } catch(PDOException $e) {
            $error = "❌ Errore connessione database: " . $e->getMessage();
            error_log($error);

            if (DEBUG_MODE) {
                die("<h3>Errore Database</h3><p>$error</p><p><strong>Verifica:</strong><br>
                    - MAMP/XAMPP avviato?<br>
                    - MySQL in running?<br>
                    - Database BOSTARTER creato?<br>
                    - Credenziali corrette in config/database.php?</p>");
            } else {
                die("Errore di sistema. Riprova più tardi.");
            }
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    // Metodi di utilità
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Errore database: " . $e->getMessage());
        }
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function callStoredProcedure($procedureName, $params = []) {
        $placeholders = implode(',', array_fill(0, count($params), '?'));
        $sql = "CALL $procedureName($placeholders)";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Stored procedure error: $procedureName - " . $e->getMessage());
            throw new Exception("Errore procedura: " . $e->getMessage());
        }
    }

    public function beginTransaction() { return $this->conn->beginTransaction(); }
    public function commit() { return $this->conn->commit(); }
    public function rollback() { return $this->conn->rollback(); }
}

// Gestione sessioni
class SessionManager {
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        self::start();
        session_destroy();
    }

    public static function isLoggedIn() {
        return self::get('user_email') !== null;
    }

    public static function isAdmin() {
        return self::get('is_admin', false);
    }

    public static function isCreator() {
        return self::get('is_creator', false);
    }

    public static function getUserEmail() {
        return self::get('user_email');
    }

    public static function requireLogin($redirectTo = '/index.html') {
        if (!self::isLoggedIn()) {
            header("Location: $redirectTo");
            exit;
        }
    }

    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header("Location: /public/projects.php");
            exit;
        }
    }

    public static function requireCreator() {
        self::requireLogin();
        if (!self::isCreator()) {
            header("Location: /public/projects.php");
            exit;
        }
    }
}

// Funzioni utility globali
class Utils {
    public static function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function jsonResponse($success, $message, $data = null, $redirect = null) {
        header('Content-Type: application/json');
        $response = [
            'success' => $success,
            'message' => $message
        ];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        if (!empty($redirect)) {
            $response['redirect'] = $redirect;
        }
        echo json_encode($response);
        exit;
    }

    public static function formatCurrency($amount) {
        return '€ ' . number_format($amount, 2, ',', '.');
    }

    public static function formatDate($date, $format = 'd/m/Y') {
        return date($format, strtotime($date));
    }

    public static function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// Autoload semplice per le classi
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Funzioni helper per retrocompatibilità
function getDBConnection() {
    return Database::getInstance()->getConnection();
}

function callStoredProcedure($procedureName, $params = []) {
    return Database::getInstance()->callStoredProcedure($procedureName, $params);
}

function sanitizeInput($data) {
    return Utils::sanitize($data);
}

function validateEmail($email) {
    return Utils::validateEmail($email);
}

function sendJSONResponse($success, $message, $data = null, $redirect = null) {
    Utils::jsonResponse($success, $message, $data, $redirect);
}

function startSession() {
    SessionManager::start();
}

function isLoggedIn() {
    return SessionManager::isLoggedIn();
}

function isAdmin() {
    return SessionManager::isAdmin();
}

function isCreator() {
    return SessionManager::isCreator();
}

// Test configurazione (solo se chiamato direttamente)
if (basename($_SERVER['PHP_SELF']) === 'database.php') {
    echo "<h2>🧪 Test Configurazione BOSTARTER</h2>";

    try {
        $db = Database::getInstance();
        echo "<p style='color: green;'>✅ Connessione database riuscita!</p>";

        // Test tabelle
        $tables = $db->fetchAll("SHOW TABLES");
        echo "<h3>📋 Tabelle trovate (" . count($tables) . "):</h3>";
        if (empty($tables)) {
            echo "<p style='color: orange;'>⚠️ Nessuna tabella trovata. Importa database.sql!</p>";
        } else {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . array_values($table)[0] . "</li>";
            }
            echo "</ul>";
        }

        // Test stored procedures
        $procedures = $db->fetchAll("SHOW PROCEDURE STATUS WHERE Db = '" . DB_NAME . "'");
        echo "<h3>⚙️ Stored Procedures (" . count($procedures) . "):</h3>";
        if (!empty($procedures)) {
            echo "<ul>";
            foreach ($procedures as $proc) {
                echo "<li>" . $proc['Name'] . "</li>";
            }
            echo "</ul>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ " . $e->getMessage() . "</p>";
    }

    echo "<hr><p><strong>Setup:</strong> Host: " . DB_HOST . " | DB: " . DB_NAME . " | User: " . DB_USER . "</p>";
}
?>