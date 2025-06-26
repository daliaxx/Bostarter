<?php
/**
 * BOSTARTER - Configurazione Database
 * File: config/database.php
 */

// === CONFIGURAZIONE MONGODB PER LOG ===
define('MONGO_LOG_URI', 'mongodb://localhost:27017'); // Cambia se necessario
define('MONGO_LOG_DB', 'bostarter_logs'); // Nome database per i log
// Se vuoi disabilitare il log su MongoDB, lascia MONGO_LOG_URI vuoto

class Database {
    // MODIFICA QUESTE IMPOSTAZIONI SECONDO LA TUA CONFIGURAZIONE
    private $host = "localhost";          // o "127.0.0.1" se localhost non funziona
    private $db_name = "BOSTARTER";
    private $username = "root";
    private $password = "";               // ← CAMBIATO: password VUOTA per XAMPP di default
    private $charset = "utf8mb4";
    public $conn;

    /**
     * Connessione al database con debug migliorato
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Log connessione riuscita
            Logger::info("Connessione database riuscita per host: {$this->host}");

        } catch(PDOException $e) {
            // Log errore dettagliato
            Logger::error("Errore connessione database: " . $e->getMessage());

            // Messaggio più specifico per debug
            $errorMessage = "Errore di connessione al database: " . $e->getMessage();
            $errorMessage .= "\n\nVerifica:";
            $errorMessage .= "\n- XAMPP è avviato?";
            $errorMessage .= "\n- MySQL è in running?";
            $errorMessage .= "\n- Host: {$this->host}";
            $errorMessage .= "\n- Database: {$this->db_name}";
            $errorMessage .= "\n- Username: {$this->username}";
            $errorMessage .= "\n- Password: " . (empty($this->password) ? '(vuota)' : '***');

            throw new Exception($errorMessage);
        }

        return $this->conn;
    }

    /**
     * Test di connessione rapido
     */
    public function testConnection() {
        try {
            $this->getConnection();
            return ['success' => true, 'message' => 'Connessione riuscita'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verifica se il database esiste
     */
    public function databaseExists() {
        try {
            // Connessione senza specificare il database
            $dsn = "mysql:host=" . $this->host . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $conn = new PDO($dsn, $this->username, $this->password, $options);

            // Controlla se il database esiste
            $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$this->db_name]);

            return $stmt->fetch() !== false;

        } catch(PDOException $e) {
            Logger::error("Errore verifica database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea il database se non esiste
     */
    public function createDatabaseIfNotExists() {
        try {
            $dsn = "mysql:host=" . $this->host . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $conn = new PDO($dsn, $this->username, $this->password, $options);
            $conn->exec("CREATE DATABASE IF NOT EXISTS {$this->db_name}");

            Logger::info("Database {$this->db_name} creato o verificato");
            return true;

        } catch(PDOException $e) {
            Logger::error("Errore creazione database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Chiusura connessione
     */
    public function closeConnection() {
        $this->conn = null;
    }

    /**
     * Esecuzione stored procedure con parametri e gestione errori migliorata
     */
    public function callStoredProcedure($procedureName, $params = []) {
        try {
            if ($this->conn === null) {
                $this->getConnection();
            }

            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            if (count($params) === 0) {
                $placeholders = '';
            }

            $sql = "CALL {$procedureName}({$placeholders})";

            Logger::info("Esecuzione stored procedure: {$sql} con parametri: " . json_encode($params));

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch(PDOException $e) {
            Logger::error("Errore stored procedure {$procedureName}: " . $e->getMessage());
            throw new Exception("Errore nell'esecuzione della stored procedure {$procedureName}: " . $e->getMessage());
        }
    }

    /**
     * Esecuzione query preparata
     */
    public function executeQuery($sql, $params = []) {
        try {
            if ($this->conn === null) {
                $this->getConnection();
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            Logger::error("Errore query: {$sql} - " . $e->getMessage());
            throw new Exception("Errore nell'esecuzione della query: " . $e->getMessage());
        }
    }

    /**
     * Recupero di una singola riga
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Recupero di tutte le righe
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Conteggio righe
     */
    public function count($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Ultimo ID inserito
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    /**
     * Inizio transazione
     */
    public function beginTransaction() {
        if ($this->conn === null) {
            $this->getConnection();
        }
        return $this->conn->beginTransaction();
    }

    /**
     * Commit transazione
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback transazione
     */
    public function rollback() {
        return $this->conn->rollback();
    }

    /**
     * Info debug per troubleshooting
     */
    public function getDebugInfo() {
        return [
            'host' => $this->host,
            'database' => $this->db_name,
            'username' => $this->username,
            'password' => empty($this->password) ? '(vuota)' : '***',
            'charset' => $this->charset,
            'connected' => $this->conn !== null
        ];
    }
}

// Classe per gestire le sessioni
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
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public static function remove($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
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

    public static function getUserNickname() {
        return self::get('user_nickname');
    }
}

// Funzioni di utilità
class Utils {

    /**
     * Sanitizzazione input
     */
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    /**
     * Validazione email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifica password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generazione token sicuro
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Formattazione data
     */
    public static function formatDate($date, $format = 'd/m/Y') {
        return date($format, strtotime($date));
    }

    /**
     * Formattazione importo
     */
    public static function formatCurrency($amount) {
        return "€ " . number_format($amount, 2, ',', '.');
    }

    /**
     * Redirect
     */
    public static function redirect($url) {
        header("Location: " . $url);
        exit();
    }

    /**
     * Messaggio di errore JSON
     */
    public static function jsonError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }

    /**
     * Messaggio di successo JSON
     */
    public static function jsonSuccess($data = [], $message = 'Operazione completata con successo') {
        echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
        exit();
    }

    /**
     * Controllo autorizzazione
     */
    public static function requireLogin() {
        if (!SessionManager::isLoggedIn()) {
            Utils::redirect('/login.php');
        }
    }

    public static function requireAdmin() {
        self::requireLogin();
        if (!SessionManager::isAdmin()) {
            Utils::redirect('/index.php?error=access_denied');
        }
    }

    public static function requireCreator() {
        self::requireLogin();
        if (!SessionManager::isCreator()) {
            Utils::redirect('/index.php?error=access_denied');
        }
    }
}

// Classe per log degli errori
class Logger {
    private static $logFile = 'logs/error.log';
    private static $mongoClient = null;
    private static $mongoCollection = null;
    private static $mongoInitTried = false;

    private static function logToMongo($message, $level) {
        if (!defined('MONGO_LOG_URI') || empty(MONGO_LOG_URI)) return;
        if (!self::$mongoInitTried) {
            self::$mongoInitTried = true;
            try {
                if (class_exists('MongoDB\\Client')) {
                    self::$mongoClient = new MongoDB\Client(MONGO_LOG_URI);
                    self::$mongoCollection = self::$mongoClient->{MONGO_LOG_DB}->logs;
                }
            } catch (Exception $e) {
                // Se fallisce la connessione, ignora e fallback su file
                self::$mongoClient = null;
                self::$mongoCollection = null;
            }
        }
        if (self::$mongoCollection) {
            try {
                self::$mongoCollection->insertOne([
                    'timestamp' => new MongoDB\BSON\UTCDateTime(),
                    'level' => $level,
                    'message' => $message,
                    'user' => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            } catch (Exception $e) {
                // Se fallisce il log su MongoDB, ignora
            }
        }
    }

    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Crea la directory se non esiste
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
        // Log anche su MongoDB
        self::logToMongo($message, $level);
    }

    public static function error($message) {
        self::log($message, 'ERROR');
    }

    public static function warning($message) {
        self::log($message, 'WARNING');
    }

    public static function info($message) {
        self::log($message, 'INFO');
    }
}

// Test rapido della configurazione - rimuovi in produzione
if (basename($_SERVER['PHP_SELF']) === 'database.php' || basename($_SERVER['PHP_SELF']) === 'config.php') {
    echo "<h2>Test Configurazione Database BOSTARTER</h2>";

    $db = new Database();
    $test = $db->testConnection();

    echo "<h3>Informazioni Configurazione:</h3>";
    $info = $db->getDebugInfo();
    echo "<ul>";
    foreach ($info as $key => $value) {
        echo "<li><strong>$key:</strong> $value</li>";
    }
    echo "</ul>";

    $color = $test['success'] ? 'green' : 'red';
    $symbol = $test['success'] ? '✓' : '✗';
    echo "<p style='color: $color; font-size: 18px;'>$symbol {$test['message']}</p>";

    if (!$test['success']) {
        echo "<h3>Soluzioni possibili:</h3>";
        echo "<ul>";
        echo "<li>Verifica che XAMPP sia avviato</li>";
        echo "<li>Controlla che MySQL sia in 'Running'</li>";
        echo "<li>Prova a cambiare host da 'localhost' a '127.0.0.1'</li>";
        echo "<li>Verifica username e password MySQL</li>";
        echo "<li>Importa il file database.sql in phpMyAdmin</li>";
        echo "</ul>";
    } else {
        // Se la connessione funziona, verifica le tabelle
        try {
            $tables = $db->fetchAll("SHOW TABLES");
            echo "<h3>Tabelle nel database:</h3>";
            if (empty($tables)) {
                echo "<p style='color: orange;'>⚠ Database vuoto - importa database.sql</p>";
            } else {
                echo "<ul>";
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    echo "<li>$tableName</li>";
                }
                echo "</ul>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Errore verifica tabelle: " . $e->getMessage() . "</p>";
        }
    }
}
?>