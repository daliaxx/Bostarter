<?php

// Configurazione database
define('DB_HOST', 'localhost'); 
define('DB_NAME', 'BOSTARTER');
define('DB_USER', 'root');
define('DB_PASS', 'root');  
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
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];

            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Imposta il charset dopo la connessione
            $this->conn->exec("SET NAMES " . DB_CHARSET);

            if (DEBUG_MODE) {
                error_log("Database connesso: " . DB_HOST . "/" . DB_NAME);
            }

        } catch(PDOException $e) {
            $error = "Errore connessione database: " . $e->getMessage();
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

    public function getAffectedRows() {
        return $this->conn->rowCount();
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

    //Verifica e attiva l'evento per la chiusura automatica dei progetti scaduti
    public function ensureEventScheduler() {
        try {
            // Verifica se l'event scheduler è attivo
            $result = $this->fetchOne("SHOW VARIABLES LIKE 'event_scheduler'");
            if ($result && $result['Value'] !== 'ON') {
                // Attiva l'event scheduler
                $this->execute("SET GLOBAL event_scheduler = ON");
            }
            
            //se l'evento esiste
            $eventExists = $this->fetchOne("
                SELECT COUNT(*) as count 
                FROM information_schema.EVENTS 
                WHERE EVENT_SCHEMA = DATABASE() 
                AND EVENT_NAME = 'ChiudiProgettiScaduti'
            ");
            
            if ($eventExists['count'] == 0) {
                // Crea l'evento se non esiste
                $this->execute("
                    CREATE EVENT ChiudiProgettiScaduti
                    ON SCHEDULE EVERY 1 DAY
                    DO
                    BEGIN
                        UPDATE PROGETTO
                        SET Stato = 'chiuso'
                        WHERE Stato = 'aperto' AND Data_Limite <= CURDATE();
                    END
                ");
            } else {
                // Aggiorna l'evento esistente se necessario
                $this->execute("
                    DROP EVENT IF EXISTS ChiudiProgettiScaduti
                ");
                $this->execute("
                    CREATE EVENT ChiudiProgettiScaduti
                    ON SCHEDULE EVERY 1 DAY
                    DO
                    BEGIN
                        UPDATE PROGETTO
                        SET Stato = 'chiuso'
                        WHERE Stato = 'aperto' AND Data_Limite <= CURDATE();
                    END
                ");
            }
        } catch (Exception $e) {
            // Log dell'errore ma non bloccare l'applicazione
            error_log("Errore nell'attivazione dell'event scheduler: " . $e->getMessage());
        }
    }
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
        
        // Pulisci tutte le variabili di sessione
        $_SESSION = array();
        
        // Se viene usato un cookie di sessione, distruggilo
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Distruggi la sessione
        session_destroy();
        
        // Assicurati che la sessione sia completamente chiusa
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function isLoggedIn() {
        return self::get('user_email') !== null && self::isSessionValid();
    }

    public static function isSessionValid() {
        self::start();
        
        // Verifica se la sessione esiste e ha un ID valido
        if (session_status() !== PHP_SESSION_ACTIVE || empty(session_id())) {
            return false;
        }
        
        // Verifica se il timestamp di login è presente e valido
        $loginTime = self::get('login_time');
        if ($loginTime && (time() - $loginTime) > 86400) { // 24 ore
            self::destroy();
            return false;
        }
        
        return true;
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
            header("Location: /Bostarter/public/projects/projects.php");
            exit;
        }
    }

    public static function requireCreator() {
        self::requireLogin();
        if (!self::isCreator()) {
            header("Location: /Bostarter/public/projects/projects.php");
            exit;
        }
    }

    public static function regenerateSession() {
        self::start();
        
        // Rigenera l'ID di sessione per sicurezza
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
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
    echo "<h2>Test Configurazione BOSTARTER</h2>";

    try {
        $db = Database::getInstance();
        echo "<p style='color: green;'>Connessione database riuscita!</p>";

        // Test tabelle
        $tables = $db->fetchAll("SHOW TABLES");
        echo "<h3>Tabelle trovate (" . count($tables) . "):</h3>";
        if (empty($tables)) {
            echo "<p style='color: orange;'>Nessuna tabella trovata. Importa database.sql!</p>";
        } else {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . array_values($table)[0] . "</li>";
            }
            echo "</ul>";
        }

        // Test stored procedures
        $procedures = $db->fetchAll("SHOW PROCEDURE STATUS WHERE Db = '" . DB_NAME . "'");
        echo "<h3> Stored Procedures (" . count($procedures) . "):</h3>";
        if (!empty($procedures)) {
            echo "<ul>";
            foreach ($procedures as $proc) {
                echo "<li>" . $proc['Name'] . "</li>";
            }
            echo "</ul>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'> " . $e->getMessage() . "</p>";
    }

    echo "<hr><p><strong>Setup:</strong> Host: " . DB_HOST . " | DB: " . DB_NAME . " | User: " . DB_USER . "</p>";
}
?>