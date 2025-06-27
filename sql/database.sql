-- ================================================================
-- BOSTARTER DATABASE - Versione Corretta per phpMyAdmin
-- ================================================================

-- Eliminazione e Creazione del Database
DROP DATABASE IF EXISTS BOSTARTER;
CREATE DATABASE IF NOT EXISTS BOSTARTER;
USE BOSTARTER;

-- ================================================================
-- TABELLE PRINCIPALI
-- ================================================================

-- Tabella UTENTE
CREATE TABLE UTENTE(
    Email               VARCHAR(100) PRIMARY KEY,
    Nickname            VARCHAR(50) UNIQUE NOT NULL,
    Password            VARCHAR(255) NOT NULL,
    Nome                VARCHAR(50) NOT NULL,
    Cognome             VARCHAR(50) NOT NULL,
    Anno_Di_Nascita     DATE NOT NULL,
    Luogo_Di_Nascita    VARCHAR(100) NOT NULL
);

-- Tabella SKILL
CREATE TABLE SKILL(
    Competenza  VARCHAR(100),
    Livello     INT CHECK (LIVELLO BETWEEN 0 AND 5),
    PRIMARY KEY (Competenza, Livello)
);

-- Tabella SKILL_CURRICULUM
CREATE TABLE SKILL_CURRICULUM(
    Email_Utente    VARCHAR(100),
    Competenza      VARCHAR(100),
    Livello         INT,
    PRIMARY KEY (Email_Utente, Competenza, Livello),
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE,
    FOREIGN KEY (Competenza, Livello) REFERENCES SKILL(Competenza, LIVELLO) ON DELETE CASCADE
);

-- Tabella AMMINISTRATORE
CREATE TABLE AMMINISTRATORE(
    Email               VARCHAR(100) PRIMARY KEY,
    Codice_Sicurezza    VARCHAR(50) NOT NULL,
    FOREIGN KEY (Email) REFERENCES UTENTE(Email) ON DELETE CASCADE
);

-- Tabella CREATORE
CREATE TABLE CREATORE (
    Email           VARCHAR(100) PRIMARY KEY,
    Nr_Progetti     INT DEFAULT 0,
    Affidabilita    FLOAT DEFAULT 0,
    FOREIGN KEY (Email) REFERENCES UTENTE(Email) ON DELETE CASCADE
);

-- Tabella PROGETTO
CREATE TABLE PROGETTO(
    Nome                VARCHAR(100) PRIMARY KEY,
    Descrizione         TEXT NOT NULL,
    Data_Inserimento    DATE NOT NULL,
    Stato               ENUM('aperto', 'chiuso')NOT NULL DEFAULT 'aperto',
    Budget              DECIMAL(10,2) NOT NULL,
    Data_Limite         DATE NOT NULL,
    Tipo                ENUM('Hardware', 'Software') NOT NULL,
    Email_Creatore      VARCHAR(100) NOT NULL,
    FOREIGN KEY (Email_Creatore) REFERENCES CREATORE(Email) ON DELETE CASCADE
);

-- Tabella FOTO
CREATE TABLE FOTO (
    ID              INT AUTO_INCREMENT PRIMARY KEY,
    Percorso        TEXT NOT NULL,
    Nome_Progetto   VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella COMPONENTI
CREATE TABLE COMPONENTE(
    ID              INT AUTO_INCREMENT PRIMARY KEY,
    Nome            VARCHAR(100),
    Descrizione     TEXT NOT NULL,
    Prezzo          DECIMAL(10,2) NOT NULL,
    Quantita        INT NOT NULL,
    Nome_Progetto   VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella PROFILO
CREATE TABLE PROFILO(
    ID              INT AUTO_INCREMENT PRIMARY KEY,
    Nome            VARCHAR(100) NOT NULL,
    Nome_Progetto   VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella SKILL_RICHIESTA
CREATE TABLE SKILL_RICHIESTA(
    ID_Profilo  INT,
    Competenza  VARCHAR(100),
    Livello     INT,
    PRIMARY KEY (ID_Profilo, Competenza, Livello),
    FOREIGN KEY (ID_Profilo) REFERENCES PROFILO(ID) ON DELETE CASCADE,
    FOREIGN KEY (Competenza, Livello) REFERENCES SKILL(Competenza, LIVELLO) ON DELETE CASCADE
);

-- Tabella COMMENTO
CREATE TABLE COMMENTO(
    ID              INT AUTO_INCREMENT PRIMARY KEY,
    Data            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Testo           TEXT NOT NULL,
    Nome_Progetto   VARCHAR(100) NOT NULL,
    Email_Utente    VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE,
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE
);

-- Tabella RISPOSTA
CREATE TABLE RISPOSTA(
    ID_Commento     INT PRIMARY KEY,
    Email_Creatore  VARCHAR(100) NOT NULL,
    Testo           TEXT NOT NULL,
    Data            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_Commento) REFERENCES COMMENTO(ID) ON DELETE CASCADE,
    FOREIGN KEY (Email_Creatore) REFERENCES CREATORE(Email) ON DELETE CASCADE
);

-- Tabella REWARD
CREATE TABLE REWARD(
    Codice          VARCHAR(100) PRIMARY KEY,
    Descrizione     TEXT NOT NULL,
    Foto            TEXT NOT NULL,
    Nome_Progetto   VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella FINANZIAMENTO
CREATE TABLE FINANZIAMENTO(
    ID              INT AUTO_INCREMENT PRIMARY KEY,
    Data            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Importo         DECIMAL(10,2) NOT NULL,
    Email_Utente    VARCHAR(100) NOT NULL,
    Codice_Reward   VARCHAR(100),
    Nome_Progetto   VARCHAR(100) NOT NULL,
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE,
    FOREIGN KEY (Codice_Reward) REFERENCES REWARD(Codice) ON DELETE SET NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella CANDIDATURA
CREATE TABLE CANDIDATURA(
    ID                  INT AUTO_INCREMENT PRIMARY KEY,
    Esito               BOOLEAN DEFAULT NULL,
    Data_Candidatura    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Email_Utente        VARCHAR(100) NOT NULL,
    ID_Profilo          INT NOT NULL,
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE,
    FOREIGN KEY (ID_Profilo) REFERENCES PROFILO(ID) ON DELETE CASCADE
);

-- ================================================================
-- STORED PROCEDURES
-- ================================================================

DELIMITER $$

-- Procedure per autenticazione utente
CREATE PROCEDURE AutenticaUtente(
    IN p_Email VARCHAR(100),
    IN p_Password VARCHAR(255)
)
BEGIN
    DECLARE v_Count INT;
SELECT COUNT(*) INTO v_Count
FROM UTENTE
WHERE Email = p_Email AND Password = p_Password
    LIMIT 1;

IF v_Count = 1 THEN
SELECT 'Autenticazione riuscita' AS Messaggio, p_Email AS Email;
ELSE
SELECT 'Autenticazione fallita' AS Messaggio, NULL AS Email;
END IF;
END $$

-- Procedure per registrazione utente
CREATE PROCEDURE RegistraUtente(
    IN p_Email VARCHAR(100),
    IN p_Nickname VARCHAR(50),
    IN p_Password VARCHAR(255),
    IN p_Nome VARCHAR(50),
    IN p_Cognome VARCHAR(50),
    IN p_Anno_Di_Nascita DATE,
    IN p_Luogo_Di_Nascita VARCHAR(100),
    IN p_IsCreatore BOOLEAN
)
BEGIN
    INSERT INTO UTENTE (
        Email, Nickname, Password, Nome, Cognome,
        Anno_Di_Nascita, Luogo_Di_Nascita
    ) VALUES (
        p_Email, p_Nickname, p_Password, p_Nome, p_Cognome,
        p_Anno_Di_Nascita, p_Luogo_Di_Nascita
    );

    IF p_IsCreatore THEN
        INSERT INTO CREATORE (Email, Nr_Progetti, Affidabilita)
        VALUES (p_Email, 0, 0);
    END IF;
END$$

CREATE PROCEDURE LoginUtente (
    IN in_email VARCHAR(255),
    IN in_password VARCHAR(255)
)
BEGIN
    SELECT Email
    FROM UTENTE
    WHERE Email = in_email
      AND Password = in_password;
END $$

-- Procedure per login amministratore
CREATE PROCEDURE LoginAmministratore(
    IN p_Email VARCHAR(100),
    IN p_Password VARCHAR(255),
    IN p_CodiceSicurezza VARCHAR(50)
)
BEGIN
    DECLARE v_Count INT;
SELECT COUNT(*) INTO v_Count
FROM UTENTE U
         JOIN AMMINISTRATORE A ON U.Email = A.Email
WHERE U.Email = p_Email AND U.Password = p_Password AND A.Codice_Sicurezza = p_CodiceSicurezza;

IF v_Count = 1 THEN
SELECT 'Login amministratore riuscito' AS Messaggio, p_Email AS Email;
ELSE
SELECT 'Credenziali amministratore non valide' AS Messaggio, NULL AS Email;
END IF;
END $$

-- Procedure per diventare creatore
CREATE PROCEDURE PromuoviACreatore(IN p_Email VARCHAR(100))
BEGIN
    -- Controlla se l'utente esiste
    IF EXISTS (SELECT 1 FROM UTENTE WHERE Email = p_Email) THEN
        -- Controlla se è già creatore
        IF NOT EXISTS (SELECT 1 FROM CREATORE WHERE Email = p_Email) THEN
            INSERT INTO CREATORE (Email, Nr_Progetti, Affidabilita)
            VALUES (p_Email, 0, 0);
        END IF;
    END IF;
END$$

-- Procedure per inserimento progetto
CREATE PROCEDURE InserisciProgetto(
    IN p_Nome VARCHAR(100),
    IN p_Descrizione TEXT,
    IN p_DataInserimento DATE,
    IN p_Budget DECIMAL(10,2),
    IN p_DataLimite DATE,
    IN p_Stato ENUM('aperto', 'chiuso'),
    IN p_EmailCreatore VARCHAR(100),
    IN p_Tipo ENUM('Hardware', 'Software')

        )
BEGIN
INSERT INTO PROGETTO (Nome, Descrizione, Data_Inserimento, Stato, Budget, Data_Limite, Tipo, Email_Creatore)
VALUES (p_Nome, p_Descrizione, p_DataInserimento, p_Stato, p_Budget, p_DataLimite, p_Tipo, p_EmailCreatore);
END $$

-- Procedure per inserimento foto
CREATE PROCEDURE InserisciFoto(
    IN p_Percorso TEXT,
    IN p_NomeProgetto VARCHAR(100)
)
BEGIN
    INSERT INTO FOTO (Percorso, Nome_Progetto)
    VALUES (p_Percorso, p_NomeProgetto);
END $$

-- Procedure per inserimento reward
CREATE PROCEDURE InserisciReward(
    IN p_Codice VARCHAR(100),
    IN p_Descrizione TEXT,
    IN p_idFoto INT,
    IN p_NomeProgetto VARCHAR(100)
)
BEGIN
INSERT INTO REWARD (Codice, Descrizione)
VALUES (p_Codice, p_Descrizione);

END $$

-- Procedure per inserimento skill
CREATE PROCEDURE InserisciSkill(
    IN p_Competenza VARCHAR(100),
    IN p_Livello INT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM SKILL
        WHERE Competenza = p_Competenza AND Livello = p_Livello
    ) THEN
        INSERT INTO SKILL (Competenza, Livello)
        VALUES (p_Competenza, p_Livello);
    END IF;
END $$

-- Procedure per finanziamento progetto
CREATE PROCEDURE FinanziaProgetto(
    IN p_Email VARCHAR(100),
    IN p_NomeProgetto VARCHAR(100),
    IN p_Importo DECIMAL(10,2),
    IN p_CodiceReward VARCHAR(100)
)
BEGIN
INSERT INTO FINANZIAMENTO (Data, Importo, Email_Utente, Codice_Reward, Nome_Progetto)
VALUES (NOW(), p_Importo, p_Email, p_CodiceReward, p_NomeProgetto);
END $$

-- Procedure per inserimento candidatura
CREATE PROCEDURE InserisciCandidatura(
    IN p_Email VARCHAR(100),
    IN p_IDProfilo INT
)
BEGIN
    DECLARE v_Count INT;

SELECT COUNT(*) INTO v_Count
FROM (
         SELECT sr.Competenza, sr.Livello
         FROM SKILL_RICHIESTA sr
                  LEFT JOIN SKILL_CURRICULUM sc
                            ON sc.Email_Utente = p_Email
                                AND sc.Competenza = sr.Competenza
                                AND sc.Livello >= sr.Livello
         WHERE sr.ID_Profilo = p_IDProfilo
           AND sc.Email_Utente IS NULL
     ) AS Mancanti;

IF v_Count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'L utente non possiede tutte le skill richieste per questo profilo';
ELSE
        INSERT INTO CANDIDATURA (Email_Utente, ID_Profilo, Esito, Data_Candidatura)
        VALUES (p_Email, p_IDProfilo, NULL, NOW());
END IF;
END $$

-- Procedure per accettare candidatura
CREATE PROCEDURE AccettaCandidatura(
    IN p_IDCandidatura INT,
    IN p_Esito BOOLEAN
)
BEGIN
UPDATE CANDIDATURA
SET Esito = p_Esito
WHERE ID = p_IDCandidatura;
END $$

-- Procedure per inserimento profilo richiesto
CREATE PROCEDURE InserisciProfiloRichiesto(
    IN p_NomeProfilo VARCHAR(100),
    IN p_NomeProgetto VARCHAR(100)
)
BEGIN
    INSERT INTO PROFILO (Nome, Nome_Progetto)
    VALUES (p_NomeProfilo, p_NomeProgetto);
    SELECT LAST_INSERT_ID() AS ID_Profilo; -- Restituisce l'ID del profilo appena inserito
END $$

-- Procedure per inserimento risposta commento
CREATE PROCEDURE InserisciRisposta(
    IN p_IDCommento INT,
    IN p_EmailCreatore VARCHAR(100),
    IN p_Testo TEXT
)
BEGIN
INSERT INTO RISPOSTA (ID_Commento, Email_Creatore, Testo)
VALUES (p_IDCommento, p_EmailCreatore, p_Testo);
END $$

-- Procedure per inserimento skill curriculum
CREATE PROCEDURE InserisciSkillCurriculum(
    IN p_Email VARCHAR(100),
    IN p_Competenza VARCHAR(100),
    IN p_Livello INT
)
BEGIN
    DECLARE v_LivelloAttuale INT;

    -- Controlla se esiste già una skill per quell'utente e competenza
    SELECT MAX(Livello) INTO v_LivelloAttuale
    FROM SKILL_CURRICULUM
    WHERE Email_Utente = p_Email AND Competenza = p_Competenza;

    IF v_LivelloAttuale IS NULL THEN
        -- Non esiste, inserisci nuova skill
        INSERT INTO SKILL_CURRICULUM (Email_Utente, Competenza, Livello)
        VALUES (p_Email, p_Competenza, p_Livello);
    ELSEIF p_Livello > v_LivelloAttuale THEN
        -- Esiste già ma il nuovo livello è superiore: elimina il vecchio e inserisci il nuovo
        DELETE FROM SKILL_CURRICULUM
        WHERE Email_Utente = p_Email AND Competenza = p_Competenza AND Livello = v_LivelloAttuale;

        INSERT INTO SKILL_CURRICULUM (Email_Utente, Competenza, Livello)
        VALUES (p_Email, p_Competenza, p_Livello);
    END IF;
    -- Se il livello è uguale o inferiore, non fa nulla
END $$

-- Procedure per inserimento skill richiesta
CREATE PROCEDURE InserisciSkillRichiesta(
    IN p_IDProfilo INT,
    IN p_Competenza VARCHAR(100),
    IN p_Livello INT
)
BEGIN
    INSERT INTO SKILL_RICHIESTA (ID_Profilo, Competenza, Livello)
    VALUES (p_IDProfilo, p_Competenza, p_Livello);
END $$

-- Procedure per inserimento commento
CREATE PROCEDURE InserisciCommento(
    IN p_Email VARCHAR(100),
    IN p_NomeProgetto VARCHAR(100),
    IN p_Testo TEXT
)
BEGIN
INSERT INTO COMMENTO (Data, Testo, Nome_Progetto, Email_Utente)
VALUES (NOW(), p_Testo, p_NomeProgetto, p_Email);
END $$

DELIMITER ;

-- ================================================================
-- TRIGGERS
-- ================================================================

DELIMITER $$

-- Trigger per incrementare numero progetti
CREATE TRIGGER IncrementaNrProgetti
    AFTER INSERT ON PROGETTO
    FOR EACH ROW
BEGIN
    UPDATE CREATORE
    SET Nr_Progetti = Nr_Progetti + 1
    WHERE Email = NEW.Email_Creatore;
END $$

-- Trigger per aggiornare affidabilità creatore
CREATE TRIGGER AggiornaAffidabilita
    AFTER INSERT ON FINANZIAMENTO
    FOR EACH ROW
BEGIN
    DECLARE v_CreatoreEmail VARCHAR(100);
    DECLARE v_Finanziati INT DEFAULT 0;
    DECLARE v_Totale INT DEFAULT 0;
    DECLARE v_NuovaAffidabilita DECIMAL(5,2);

    SELECT Email_Creatore INTO v_CreatoreEmail
    FROM PROGETTO WHERE Nome = NEW.Nome_Progetto;

    SELECT COUNT(DISTINCT p.Nome) INTO v_Finanziati
    FROM PROGETTO p
             JOIN FINANZIAMENTO f ON f.Nome_Progetto = p.Nome
    WHERE p.Email_Creatore = v_CreatoreEmail;

    SELECT COUNT(*) INTO v_Totale
    FROM PROGETTO WHERE Email_Creatore = v_CreatoreEmail;

    IF v_Totale > 0 THEN
        SET v_NuovaAffidabilita = CAST(v_Finanziati AS DECIMAL(5,2)) / CAST(v_Totale AS DECIMAL(5,2));
    UPDATE CREATORE
    SET Affidabilita = v_NuovaAffidabilita
    WHERE Email = v_CreatoreEmail;
END IF;
END $$

-- Trigger per ricalcolare affidabilità dopo nuovo progetto
CREATE TRIGGER RicalcolaAffidabilitaDopoNuovoProgetto
    AFTER INSERT ON PROGETTO
    FOR EACH ROW
BEGIN
    DECLARE v_Finanziati INT DEFAULT 0;
    DECLARE v_Totale INT DEFAULT 0;
    DECLARE v_NuovaAffidabilita DECIMAL(5,2);

    SELECT COUNT(DISTINCT p.Nome) INTO v_Finanziati
    FROM PROGETTO p
             JOIN FINANZIAMENTO f ON f.Nome_Progetto = p.Nome
    WHERE p.Email_Creatore = NEW.Email_Creatore;

    SELECT COUNT(*) INTO v_Totale
    FROM PROGETTO WHERE Email_Creatore = NEW.Email_Creatore;

    IF v_Totale > 0 THEN
        SET v_NuovaAffidabilita = CAST(v_Finanziati AS DECIMAL(5,2)) / CAST(v_Totale AS DECIMAL(5,2));
    UPDATE CREATORE
    SET Affidabilita = v_NuovaAffidabilita
    WHERE Email = NEW.Email_Creatore;
END IF;
END $$

-- Trigger per chiudere progetto quando budget raggiunto
CREATE TRIGGER ChiudiProgettoBudget
    AFTER INSERT ON FINANZIAMENTO
    FOR EACH ROW
BEGIN
    DECLARE v_TotaleFinanziamenti DECIMAL(10,2);
    DECLARE v_Budget DECIMAL(10,2);

    SELECT SUM(Importo) INTO v_TotaleFinanziamenti
    FROM FINANZIAMENTO WHERE Nome_Progetto = NEW.Nome_Progetto;

    SELECT Budget INTO v_Budget
    FROM PROGETTO WHERE Nome = NEW.Nome_Progetto;

    IF v_TotaleFinanziamenti >= v_Budget THEN
    UPDATE PROGETTO
    SET Stato = 'chiuso'
    WHERE Nome = NEW.Nome_Progetto;
END IF;
END $$

DELIMITER ;

-- ================================================================
-- EVENTO PER CHIUDERE PROGETTI SCADUTI
-- ================================================================

DELIMITER $$

CREATE EVENT ChiudiProgettiScaduti
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    UPDATE PROGETTO
    SET Stato = 'chiuso'
    WHERE Stato = 'aperto' AND Data_Limite <= CURDATE();
END $$

DELIMITER ;

-- ================================================================
-- VIEWS PER STATISTICHE
-- ================================================================

-- View classifica affidabilità creatori
CREATE VIEW classifica_affidabilita AS
SELECT u.Nickname, c.affidabilita
FROM CREATORE c
         JOIN UTENTE u ON  c.Email = u.Email
ORDER BY c.affidabilita DESC
    LIMIT 3;

-- View progetti quasi completati
CREATE VIEW ProgettiQuasiCompletati AS
SELECT p.Nome,
       p.Budget - COALESCE(SUM(f.Importo), 0) AS DifferenzaResidua
FROM PROGETTO p
         LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
WHERE p.Stato = 'aperto'
GROUP BY p.Nome, p.Budget
ORDER BY DifferenzaResidua ASC
    LIMIT 3;

-- View classifica finanziatori
CREATE VIEW ClassificaFinanziatori AS
SELECT u.Nickname, SUM(f.Importo) AS Totale
FROM FINANZIAMENTO f
         JOIN UTENTE u ON f.Email_Utente = u.Email
GROUP BY u.Nickname
ORDER BY Totale DESC
    LIMIT 3;

-- View per debug
CREATE VIEW DebugProgetti AS
SELECT
    p.Nome AS Progetto,
    p.Stato,
    p.Budget,
    COALESCE(SUM(f.Importo), 0) AS TotaleFinanziato,
    DATEDIFF(p.Data_Limite, CURDATE()) AS GiorniResidui,
    c.Nr_Progetti,
    c.Affidabilita,
    u.Nickname AS Creatore
FROM PROGETTO p
         LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
         JOIN CREATORE c ON p.Email_Creatore = c.Email
         JOIN UTENTE u ON u.Email = c.Email
GROUP BY p.Nome, p.Stato, p.Budget, p.Data_Limite, c.Nr_Progetti, c.Affidabilita, u.Nickname;

-- ================================================================
-- DATI DI ESEMPIO
-- ================================================================

-- Inserimento utenti
INSERT INTO UTENTE (Email, Nickname, Password, Nome, Cognome, Anno_Di_Nascita, Luogo_Di_Nascita) VALUES
    ('dalia.barone@email.com','dalia28','password123','Dalia','Barone','2004-02-20','Termoli'),
    ('sofia.neamtu@email.com','sofia_n','securepass','Sofia','Neamtu','2003-12-10','Padova'),
    ('admin@bostarter.com','admin','password123','Admin','System','1990-01-01','Bologna');

-- Inserimento amministratori
INSERT INTO AMMINISTRATORE (Email, Codice_Sicurezza) VALUES
    ('admin@bostarter.com','ADMIN2024');

-- Inserimento creatori
INSERT INTO CREATORE (Email, Affidabilita) VALUES
    ('dalia.barone@email.com',0),
    ('sofia.neamtu@email.com',0);

-- Inserimento skill base
INSERT INTO SKILL (COMPETENZA, LIVELLO) VALUES
    ('AI', 1), ('AI', 2), ('AI', 3), ('AI', 4), ('AI', 5),
    ('Machine Learning', 1), ('Machine Learning', 2), ('Machine Learning', 3), ('Machine Learning', 4), ('Machine Learning', 5),
    ('Web Development', 1), ('Web Development', 2), ('Web Development', 3), ('Web Development', 4), ('Web Development', 5),
    ('Database Management', 1), ('Database Management', 2), ('Database Management', 3), ('Database Management', 4), ('Database Management', 5),
    ('Cybersecurity', 1), ('Cybersecurity', 2), ('Cybersecurity', 3), ('Cybersecurity', 4), ('Cybersecurity', 5),
    ('Data Analysis', 1), ('Data Analysis', 2), ('Data Analysis', 3), ('Data Analysis', 4), ('Data Analysis', 5),
    ('Cloud Computing', 1), ('Cloud Computing', 2), ('Cloud Computing', 3), ('Cloud Computing', 4), ('Cloud Computing', 5),
    ('Networking', 1), ('Networking', 2), ('Networking', 3), ('Networking', 4), ('Networking', 5),
    ('Software Engineering', 1), ('Software Engineering', 2), ('Software Engineering', 3), ('Software Engineering', 4), ('Software Engineering', 5),
    ('Embedded Systems', 1), ('Embedded Systems', 2), ('Embedded Systems', 3), ('Embedded Systems', 4), ('Embedded Systems', 5);

-- Inserimento skill curriculum
INSERT INTO SKILL_CURRICULUM (Email_Utente, Competenza, Livello) VALUES
    ('dalia.barone@email.com','Web Development',4),
    ('dalia.barone@email.com','Database Management',3),
    ('sofia.neamtu@email.com','Data Analysis',3),
    ('sofia.neamtu@email.com','AI',4),
    ('sofia.neamtu@email.com','Machine Learning',5);

-- Inserimento progetti
INSERT INTO PROGETTO (Nome, Descrizione, Data_Inserimento, Stato, Budget, Data_Limite, Tipo, Email_Creatore) VALUES
    ('SmartHome AI','Sistema di automazione domestica basato su AI','2025-03-01','aperto',5000,'2025-06-01','Hardware','dalia.barone@email.com'),
    ('CyberShield','Firewall AI per la sicurezza informatica','2025-01-15','chiuso',12000,'2025-04-30','Hardware','sofia.neamtu@email.com'),
    ('AutoPilot System','Sistema di guida autonoma per auto','2025-02-10','aperto',15000,'2025-08-01', 'Software','dalia.barone@email.com'),
    ('E-Health Monitor','Sistema di monitoraggio remoto della salute','2025-03-05','aperto',7000,'2025-06-30','Software','sofia.neamtu@email.com');

-- Inserimento componenti per progetti hardware
INSERT INTO COMPONENTE (Nome, Descrizione, Prezzo, Quantita, Nome_Progetto) VALUES
    ('Sensore di Movimento','Sensore per rilevare il movimento in ambienti domestici',20.00,10,'SmartHome AI'),
    ('Modulo Bluetooth','Modulo di comunicazione Bluetooth per connessione remota',15.00,8, 'SmartHome AI'),
    ('Camera HD','Telecamera ad alta risoluzione per sicurezza',50.00,5,'CyberShield'),
    ('Motore Elettrico','Motore per guida autonoma',120.00,4,'AutoPilot System'),
    ('Sensore LiDAR','Sensore per rilevamento ostacoli in guida autonoma',200.00,2,'AutoPilot System'),
    ('Batteria al Litio','Batteria ricaricabile ad alta capacita',90.00,6,'CyberShield');

-- Inserimento profili per progetti software
INSERT INTO PROFILO (Nome, Nome_Progetto) VALUES
    ('Esperto AI','SmartHome AI'),
    ('Sviluppatore Full Stack', 'SmartHome AI'),
    ('Analista di Sicurezza','CyberShield'),
    ('Ingegnere DevOps','CyberShield'),
    ('Data Scientist', 'E-Health Monitor'),
    ('Cloud Architect','CyberShield');

INSERT INTO SKILL_RICHIESTA (ID_Profilo, Competenza, Livello) VALUES
    (1,'AI',4),
    (1,'Machine Learning',5),
    (2,'Web Development',4),
    (2,'Database Management',3),
    (3,'Cybersecurity',4),
    (4,'Cloud Computing',5),
    (5,'Data Analysis',3),
    (5,'AI',4),
    (6,'Cloud Computing',4),
    (6,'Networking',3);

-- Inserimento rewards
INSERT INTO REWARD (Codice, Descrizione, Foto, Nome_Progetto) VALUES
    ('RWD1','Accesso beta esclusivo al prodotto', '/img/rwd2.jpeg','AutoPilot System'),
    ('RWD2','T-shirt personalizzata del progetto','/img/rwd2.jpeg','AutoPilot System'),
    ('RWD3','Menzione speciale nel sito ufficiale','/img/rwd2.jpeg','AutoPilot System'),
    ('RWD4','Invito a evento esclusivo di presentazione','/img/rwd2.jpeg','AutoPilot System'),
    ('RWD5','Pacchetto premium di funzioni avanzate','/img/rwd2.jpeg','AutoPilot System');

-- Inserimento finanziamenti
INSERT INTO FINANZIAMENTO (Data, Importo, Email_Utente, Codice_Reward, Nome_Progetto) VALUES
    ('2025-06-05',100.00, 'dalia.barone@email.com', 'RWD1', 'SmartHome AI'),
    ('2025-06-01',150.00, 'sofia.neamtu@email.com', 'RWD3', 'CyberShield'),
    ('2025-06-01',300.00, 'dalia.barone@email.com', 'RWD4', 'AutoPilot System'),
    ('2025-06-01',250.00, 'sofia.neamtu@email.com', 'RWD5', 'E-Health Monitor');

-- Inserimento foto progetti
INSERT INTO FOTO (Percorso, Nome_Progetto) VALUES
    ('smarthome.jpg', 'SmartHome AI'),
    ('cybershield.jpg', 'CyberShield'),
    ('autopilot.jpg','AutoPilot System'),
    ('e-health.png','E-Health Monitor');

-- Inserimento candidature
INSERT INTO CANDIDATURA (Esito, Email_Utente, ID_Profilo) VALUES
    (FALSE, 'dalia.barone@email.com', 1),
    (FALSE, 'sofia.neamtu@email.com', 3);