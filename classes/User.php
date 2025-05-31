<?php
/**
 * BOSTARTER - Classe User
 * File: classes/User.php
 */

require_once '../config/database.php';

class User {
    private $db;
    private $conn;

    // Proprietà utente
    public $email;
    public $nickname;
    public $password;
    public $nome;
    public $cognome;
    public $anno_di_nascita;
    public $luogo_di_nascita;
    public $data_registrazione;

    // Ruoli
    public $is_admin = false;
    public $is_creator = false;
    public $codice_sicurezza;
    public $nr_progetti;
    public $affidabilita;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Registrazione nuovo utente
     */
    public function register($email, $nickname, $password, $nome, $cognome, $anno_nascita, $luogo_nascita) {
        try {
            // Validazione dati
            if (!Utils::validateEmail($email)) {
                throw new Exception("Email non valida");
            }

            if (strlen($password) < 6) {
                throw new Exception("La password deve essere di almeno 6 caratteri");
            }

            // Controlla se email o nickname esistono già
            if ($this->emailExists($email)) {
                throw new Exception("Email già registrata");
            }

            if ($this->nicknameExists($nickname)) {
                throw new Exception("Nickname già in uso");
            }

            // Hash password
            $hashedPassword = Utils::hashPassword($password);

            // Chiama stored procedure per registrazione
            $stmt = $this->db->callStoredProcedure(
                'RegistraUtente',
                [$email, $nickname, $hashedPassword, $nome, $cognome, $anno_nascita, $luogo_nascita]
            );

            Logger::info("Nuovo utente registrato: $email");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore registrazione utente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Login utente
     */
    public function login($email, $password) {
        try {
            // Recupera utente
            $user = $this->getUserByEmail($email);

            if (!$user) {
                throw new Exception("Credenziali non valide");
            }

            // Verifica password (per ora usiamo confronto diretto, poi miglioreremo con hash)
            if ($password !== $user['Password']) {
                throw new Exception("Credenziali non valide");
            }

            // Imposta sessione
            $this->setUserSession($user);

            Logger::info("Login effettuato: $email");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore login: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Login amministratore
     */
    public function loginAdmin($email, $password, $codiceSicurezza) {
        try {
            $stmt = $this->db->callStoredProcedure(
                'LoginAmministratore',
                [$email, $password, $codiceSicurezza]
            );

            $result = $stmt->fetch();

            if ($result && $result['Email']) {
                $user = $this->getUserByEmail($email);
                $this->setUserSession($user);
                SessionManager::set('is_admin', true);

                Logger::info("Login amministratore: $email");
                return true;
            }

            throw new Exception("Credenziali amministratore non valide");

        } catch (Exception $e) {
            Logger::error("Errore login amministratore: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Promuovi utente a creatore
     */
    public function promoteToCreator($email) {
        try {
            $sql = "INSERT INTO CREATORE (Email, Nr_Progetti, Affidabilita) VALUES (?, 0, 0)";
            $this->db->executeQuery($sql, [$email]);

            Logger::info("Utente promosso a creatore: $email");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore promozione creatore: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Promuovi utente ad amministratore
     */
    public function promoteToAdmin($email, $codiceSicurezza) {
        try {
            $sql = "INSERT INTO AMMINISTRATORE (Email, Codice_Sicurezza) VALUES (?, ?)";
            $this->db->executeQuery($sql, [$email, $codiceSicurezza]);

            Logger::info("Utente promosso ad amministratore: $email");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore promozione amministratore: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Aggiungi skill al curriculum
     */
    public function addSkillToCurriculum($email, $competenza, $livello) {
        try {
            // Prima verifica che la skill esista
            if (!$this->skillExists($competenza, $livello)) {
                throw new Exception("Skill non trovata nel sistema");
            }

            $stmt = $this->db->callStoredProcedure(
                'InserisciSkillCurriculum',
                [$email, $competenza, $livello]
            );

            Logger::info("Skill aggiunta al curriculum: $email - $competenza ($livello)");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore aggiunta skill: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Recupera skill curriculum utente
     */
    public function getUserSkills($email) {
        $sql = "SELECT sc.Competenza, sc.Livello 
                FROM SKILL_CURRICULUM sc 
                WHERE sc.Email_Utente = ? 
                ORDER BY sc.Competenza, sc.Livello DESC";

        return $this->db->fetchAll($sql, [$email]);
    }

    /**
     * Recupera tutte le skill disponibili
     */
    public function getAllSkills() {
        $sql = "SELECT DISTINCT Competenza FROM SKILL ORDER BY Competenza";
        return $this->db->fetchAll($sql);
    }

    /**
     * Recupera livelli per una competenza
     */
    public function getSkillLevels($competenza) {
        $sql = "SELECT Livello FROM SKILL WHERE Competenza = ? ORDER BY Livello";
        return $this->db->fetchAll($sql, [$competenza]);
    }

    /**
     * Aggiungi nuova skill (solo admin)
     */
    public function addSkill($competenza, $livello) {
        try {
            $stmt = $this->db->callStoredProcedure('InserisciSkill', [$competenza, $livello]);

            Logger::info("Nuova skill aggiunta: $competenza ($livello)");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore aggiunta skill: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Recupera profilo utente completo
     */
    public function getUserProfile($email) {
        try {
            $user = $this->getUserByEmail($email);
            if (!$user) {
                return null;
            }

            // Aggiungi informazioni creatore se applicabile
            $creatorInfo = $this->getCreatorInfo($email);
            if ($creatorInfo) {
                $user['is_creator'] = true;
                $user['nr_progetti'] = $creatorInfo['Nr_Progetti'];
                $user['affidabilita'] = $creatorInfo['Affidabilita'];
            }

            // Aggiungi informazioni admin se applicabile
            $adminInfo = $this->getAdminInfo($email);
            if ($adminInfo) {
                $user['is_admin'] = true;
                $user['codice_sicurezza'] = $adminInfo['Codice_Sicurezza'];
            }

            // Aggiungi skills
            $user['skills'] = $this->getUserSkills($email);

            return $user;

        } catch (Exception $e) {
            Logger::error("Errore recupero profilo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Aggiorna profilo utente
     */
    public function updateProfile($email, $data) {
        try {
            $allowedFields = ['Nome', 'Cognome', 'Luogo_Di_Nascita'];
            $setParts = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setParts[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($setParts)) {
                throw new Exception("Nessun campo da aggiornare");
            }

            $params[] = $email;
            $sql = "UPDATE UTENTE SET " . implode(', ', $setParts) . " WHERE Email = ?";

            $this->db->executeQuery($sql, $params);

            Logger::info("Profilo aggiornato: $email");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore aggiornamento profilo: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cambia password
     */
    public function changePassword($email, $oldPassword, $newPassword) {
        try {
            $user = $this->getUserByEmail($email);

            if (!$user || $user['Password'] !== $oldPassword) {
                throw new Exception("Password attuale non corretta");
            }

            if (strlen($newPassword) < 6) {
                throw new Exception("La nuova password deve essere di almeno 6 caratteri");
            }

            $hashedPassword = Utils::hashPassword($newPassword);
            $sql = "UPDATE UTENTE SET Password = ? WHERE Email = ?";

            $this->db->executeQuery($sql, [$hashedPassword, $email]);

            Logger::info("Password cambiata: $email");
            return true;

        } catch (Exception $e) {
            Logger::error("Errore cambio password: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Logout
     */
    public function logout() {
        Logger::info("Logout effettuato: " . SessionManager::getUserEmail());
        SessionManager::destroy();
    }

    // Metodi privati di utilità

    private function emailExists($email) {
        $sql = "SELECT COUNT(*) as count FROM UTENTE WHERE Email = ?";
        $result = $this->db->fetchOne($sql, [$email]);
        return $result['count'] > 0;
    }

    private function nicknameExists($nickname) {
        $sql = "SELECT COUNT(*) as count FROM UTENTE WHERE Nickname = ?";
        $result = $this->db->fetchOne($sql, [$nickname]);
        return $result['count'] > 0;
    }

    private function getUserByEmail($email) {
        $sql = "SELECT * FROM UTENTE WHERE Email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }

    private function getCreatorInfo($email) {
        $sql = "SELECT * FROM CREATORE WHERE Email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }

    private function getAdminInfo($email) {
        $sql = "SELECT * FROM AMMINISTRATORE WHERE Email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }

    private function skillExists($competenza, $livello) {
        $sql = "SELECT COUNT(*) as count FROM SKILL WHERE Competenza = ? AND Livello = ?";
        $result = $this->db->fetchOne($sql, [$competenza, $livello]);
        return $result['count'] > 0;
    }

    private function setUserSession($user) {
        SessionManager::set('user_email', $user['Email']);
        SessionManager::set('user_nickname', $user['Nickname']);
        SessionManager::set('user_nome', $user['Nome']);
        SessionManager::set('user_cognome', $user['Cognome']);

        // Controlla se è creatore
        $creatorInfo = $this->getCreatorInfo($user['Email']);
        if ($creatorInfo) {
            SessionManager::set('is_creator', true);
            SessionManager::set('nr_progetti', $creatorInfo['Nr_Progetti']);
            SessionManager::set('affidabilita', $creatorInfo['Affidabilita']);
        }

        // Controlla se è admin
        $adminInfo = $this->getAdminInfo($user['Email']);
        if ($adminInfo) {
            SessionManager::set('is_admin', true);
        }
    }
}

/**
 * Classe per gestire le statistiche degli utenti
 */
class UserStats {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Classifica creatori per affidabilità
     */
    public function getTopCreatorsByReliability() {
        $sql = "SELECT * FROM classifica_affidabilita";
        return $this->db->fetchAll($sql);
    }

    /**
     * Classifica finanziatori
     */
    public function getTopFunders() {
        $sql = "SELECT * FROM ClassificaFinanziatori";
        return $this->db->fetchAll($sql);
    }

    /**
     * Statistiche utente specifico
     */
    public function getUserStats($email) {
        $stats = [
            'progetti_creati' => 0,
            'progetti_finanziati' => 0,
            'totale_finanziato' => 0,
            'candidature_inviate' => 0,
            'candidature_accettate' => 0,
            'commenti_inseriti' => 0
        ];

        // Progetti creati
        $sql = "SELECT COUNT(*) as count FROM PROGETTO WHERE Email_Creatore = ?";
        $result = $this->db->fetchOne($sql, [$email]);
        $stats['progetti_creati'] = $result['count'];

        // Finanziamenti
        $sql = "SELECT COUNT(DISTINCT Nome_Progetto) as count, SUM(Importo) as totale 
                FROM FINANZIAMENTO WHERE Email_Utente = ?";
        $result = $this->db->fetchOne($sql, [$email]);
        $stats['progetti_finanziati'] = $result['count'] ?: 0;
        $stats['totale_finanziato'] = $result['totale'] ?: 0;

        // Candidature
        $sql = "SELECT COUNT(*) as count FROM CANDIDATURA WHERE Email_Utente = ?";
        $result = $this->db->fetchOne($sql, [$email]);
        $stats['candidature_inviate'] = $result['count'];

        $sql = "SELECT COUNT(*) as count FROM CANDIDATURA WHERE Email_Utente = ? AND Esito = 1";
        $result = $this->db->fetchOne($sql, [$email]);
        $stats['candidature_accettate'] = $result['count'];

        // Commenti
        $sql = "SELECT COUNT(*) as count FROM COMMENTO WHERE Email_Utente = ?";
        $result = $this->db->fetchOne($sql, [$email]);
        $stats['commenti_inseriti'] = $result['count'];

        return $stats;
    }
}
?>