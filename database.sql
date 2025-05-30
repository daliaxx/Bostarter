-- ================================================================
-- BOSTARTER DATABASE - Versione Pulita e Ottimizzata
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
    Email VARCHAR(100) PRIMARY KEY,
    Nickname VARCHAR(50) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Nome VARCHAR(50) NOT NULL,
    Cognome VARCHAR(50) NOT NULL,
    Anno_Di_Nascita DATE NOT NULL,
    Luogo_Di_Nascita VARCHAR(100) NOT NULL,
    Data_Registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella SKILL
CREATE TABLE SKILL(
    COMPETENZA VARCHAR(100),
    LIVELLO INT CHECK (LIVELLO BETWEEN 0 AND 5),
    PRIMARY KEY (COMPETENZA, LIVELLO)
);

-- Tabella SKILL_CURRICULUM
CREATE TABLE SKILL_CURRICULUM(
    Email_Utente VARCHAR(100),
    Competenza VARCHAR(100),
    Livello INT,
    PRIMARY KEY (Email_Utente, Competenza, Livello),
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE,
    FOREIGN KEY (Competenza, Livello) REFERENCES SKILL(Competenza, LIVELLO) ON DELETE CASCADE
);

-- Tabella AMMINISTRATORE
CREATE TABLE AMMINISTRATORE(
    Email VARCHAR(100) PRIMARY KEY,
    Codice_Sicurezza VARCHAR(50) NOT NULL,
    FOREIGN KEY (Email) REFERENCES UTENTE(Email) ON DELETE CASCADE
);

-- Tabella CREATORE
CREATE TABLE CREATORE (
    Email VARCHAR(100) PRIMARY KEY,
    Nr_Progetti INT DEFAULT 0,
    Affidabilita FLOAT DEFAULT 0,
    FOREIGN KEY (Email) REFERENCES UTENTE(Email) ON DELETE CASCADE
);

-- Tabella PROGETTO
CREATE TABLE PROGETTO(
    Nome VARCHAR(100) PRIMARY KEY,
    Descrizione TEXT NOT NULL,
    Data_Inserimento DATE NOT NULL,
    Stato ENUM('aperto', 'chiuso') NOT NULL DEFAULT 'aperto',
    Budget DECIMAL(10,2) NOT NULL,
    Data_Limite DATE NOT NULL,
    Email_Creatore VARCHAR(100) NOT NULL,
    FOREIGN KEY (Email_Creatore) REFERENCES CREATORE(Email) ON DELETE CASCADE
);

-- Tabella FOTO
CREATE TABLE FOTO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    percorso VARCHAR(255) NOT NULL,
    Nome_Progetto VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella HARDWARE
CREATE TABLE HARDWARE(
    Nome VARCHAR(100) PRIMARY KEY,
    FOREIGN KEY (Nome) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella SOFTWARE
CREATE TABLE SOFTWARE(
    Nome VARCHAR(100) PRIMARY KEY,
    FOREIGN KEY (Nome) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella COMPONENTI
CREATE TABLE COMPONENTI(
    Nome VARCHAR(100) PRIMARY KEY,
    Descrizione TEXT NOT NULL,
    Prezzo DECIMAL(10,2) NOT NULL,
    Quantita INT NOT NULL
);

-- Tabella COMPONENTI_HARDWARE
CREATE TABLE COMPONENTI_HARDWARE(
    Nome_Progetto VARCHAR(100),
    Nome_Componente VARCHAR(100),
    PRIMARY KEY (Nome_Progetto, Nome_Componente),
    FOREIGN KEY (Nome_Progetto) REFERENCES HARDWARE(Nome) ON DELETE CASCADE,
    FOREIGN KEY (Nome_Componente) REFERENCES COMPONENTI(Nome) ON DELETE CASCADE
);

-- Tabella PROFILO
CREATE TABLE PROFILO(
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Nome VARCHAR(100) NOT NULL
);

-- Tabella PROFILO_SOFTWARE
CREATE TABLE PROFILO_SOFTWARE(
    Nome_Progetto VARCHAR(100),
    ID_Profilo INT,
    PRIMARY KEY (Nome_Progetto, ID_Profilo),
    FOREIGN KEY (Nome_Progetto) REFERENCES SOFTWARE(Nome) ON DELETE CASCADE,
    FOREIGN KEY (ID_Profilo) REFERENCES PROFILO(ID) ON DELETE CASCADE
);

-- Tabella SKILL_RICHIESTA
CREATE TABLE SKILL_RICHIESTA(
    ID_Profilo INT,
    Competenza VARCHAR(100),
    Livello INT,
    PRIMARY KEY (ID_Profilo, Competenza, Livello),
    FOREIGN KEY (ID_Profilo) REFERENCES PROFILO(ID) ON DELETE CASCADE,
    FOREIGN KEY (Competenza, Livello) REFERENCES SKILL(Competenza, LIVELLO) ON DELETE CASCADE
);

-- Tabella COMMENTO
CREATE TABLE COMMENTO(
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Testo TEXT NOT NULL,
    Nome_Progetto VARCHAR(100) NOT NULL,
    Email_Utente VARCHAR(100) NOT NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE,
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE
);

-- Tabella RISPOSTA
CREATE TABLE RISPOSTA(
    ID_Commento INT PRIMARY KEY,
    Email_Creatore VARCHAR(100) NOT NULL,
    Testo TEXT NOT NULL,
    Data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_Commento) REFERENCES COMMENTO(ID) ON DELETE CASCADE,
    FOREIGN KEY (Email_Creatore) REFERENCES CREATORE(Email) ON DELETE CASCADE
);

-- Tabella REWARD
CREATE TABLE REWARD(
    Codice VARCHAR(100) PRIMARY KEY,
    Descrizione TEXT NOT NULL
);

-- Tabella PROGETTO_REWARD
CREATE TABLE PROGETTO_REWARD(
    Nome_Progetto VARCHAR(100),
    Codice_Reward VARCHAR(100),
    PRIMARY KEY (Nome_Progetto, Codice_Reward),
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE,
    FOREIGN KEY (Codice_Reward) REFERENCES REWARD(Codice) ON DELETE CASCADE
);

-- Tabella FINANZIAMENTO
CREATE TABLE FINANZIAMENTO(
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Importo DECIMAL(10,2) NOT NULL,
    Email_Utente VARCHAR(100) NOT NULL,
    Codice_Reward VARCHAR(100),
    Nome_Progetto VARCHAR(100) NOT NULL,
    FOREIGN KEY (Email_Utente) REFERENCES UTENTE(Email) ON DELETE CASCADE,
    FOREIGN KEY (Codice_Reward) REFERENCES REWARD(Codice) ON DELETE SET NULL,
    FOREIGN KEY (Nome_Progetto) REFERENCES PROGETTO(Nome) ON DELETE CASCADE
);

-- Tabella CANDIDATURA
CREATE TABLE CANDIDATURA(
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Esito BOOLEAN DEFAULT FALSE,
    Data_Candidatura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Email_Utente VARCHAR(100) NOT NULL,
    ID_Profilo INT NOT NULL,
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
    IN p_Luogo_Di_Nascita VARCHAR(100)
)
BEGIN
    INSERT INTO UTENTE (
        Email, Nickname, Password, Nome, Cognome, 
        Anno_Di_Nascita, Luogo_Di_Nascita
    ) VALUES (
        p_Email, p_Nickname, p_Password, p_Nome, p_Cognome,
        p_Anno_Di_Nascita, p_Luogo_Di_Nascita
    );
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

-- Procedure per inserimento progetto
CREATE PROCEDURE InserisciProgetto(
    IN p_Nome VARCHAR(100),
    IN p_Descrizione TEXT,
    IN p_DataInserimento DATE,
    IN p_Budget DECIMAL(10,2),
    IN p_DataLimite DATE,
    IN p_Stato ENUM('aperto', 'chiuso'),
    IN p_EmailCreatore VARCHAR(100)
)
BEGIN
    INSERT INTO PROGETTO (Nome, Descrizione, Data_Inserimento, Stato, Budget, Data_Limite, Email_Creatore)
    VALUES (p_Nome, p_Descrizione, p_DataInserimento, p_Stato, p_Budget, p_DataLimite, p_EmailCreatore);
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

    INSERT INTO PROGETTO_REWARD (Nome_Progetto, Codice_Reward)
    VALUES (p_NomeProgetto, p_Codice);
END $$

-- Procedure per inserimento skill
CREATE PROCEDURE InserisciSkill(
    IN p_Competenza VARCHAR(100),
    IN p_Livello INT
)
BEGIN
    INSERT INTO SKILL (COMPETENZA, LIVELLO)
    VALUES (p_Competenza, p_Livello);
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
-- VIEWS PER STATISTICHE
-- ================================================================

-- View classifica affidabilità creatori
CREATE VIEW classifica_affidabilita AS
SELECT u.Nickname, c.affidabilita
FROM UTENTE u
JOIN CREATORE c ON u.Email = c.Email
ORDER BY c.affidabilita DESC
LIMIT 3;

-- View progetti quasi completati
CREATE VIEW ProgettiQuasiCompletati AS
SELECT p.Nome,
       p.Descrizione,
       p.Budget - COALESCE(SUM(f.Importo), 0) AS DifferenzaResidua
FROM PROGETTO p
LEFT JOIN FINANZIAMENTO f ON p.Nome = f.Nome_Progetto
WHERE p.Stato = 'aperto'
GROUP BY p.Nome, p.Descrizione, p.Budget
ORDER BY DifferenzaResidua ASC
LIMIT 3;

-- View classifica finanziatori
CREATE VIEW ClassificaFinanziatori AS
SELECT u.Nickname, SUM(f.Importo) AS Totale
FROM UTENTE u
JOIN FINANZIAMENTO f ON u.Email = f.Email_Utente
GROUP BY u.Nickname
ORDER BY Totale DESC
LIMIT 3;

-- ================================================================
-- DATI DI ESEMPIO (usando STORED PROCEDURES)
-- ================================================================

-- Inserimento utenti usando RegistraUtente
CALL RegistraUtente('dalia.barone@email.com','dalia28','password123','Dalia','Barone','2004-02-20','Termoli');
CALL RegistraUtente('mattia.veroni@email.com','mattiav','mypassword','Mattia','Veroni','2002-12-31','Carpi');
CALL RegistraUtente('sofia.neamtu@email.com','sofia_n','securepass','Sofia','Neamtu','2003-12-10','Padova');
CALL RegistraUtente('admin@bostarter.com','admin','password123','Admin','System','1990-01-01','Bologna');

-- Inserimento amministratore
INSERT INTO AMMINISTRATORE (Email, Codice_Sicurezza) VALUES
('admin@bostarter.com','ADMIN2024'),
('dalia.barone@email.com','SEC123'),
('sofia.neamtu@email.com','SEC456');

-- Inserimento creatori
INSERT INTO CREATORE (Email, Affidabilita) VALUES
('dalia.barone@email.com',0),
('mattia.veroni@email.com',0),
('sofia.neamtu@email.com',0);

-- Inserimento skill usando InserisciSkill
CALL InserisciSkill('AI', 1);
CALL InserisciSkill('AI', 2);
CALL InserisciSkill('AI', 3);
CALL InserisciSkill('AI', 4);
CALL InserisciSkill('AI', 5);
CALL InserisciSkill('Machine Learning', 1);
CALL InserisciSkill('Machine Learning', 2);
CALL InserisciSkill('Machine Learning', 3);
CALL InserisciSkill('Machine Learning', 4);
CALL InserisciSkill('Machine Learning', 5);
CALL InserisciSkill('Web Development', 1);
CALL InserisciSkill('Web Development', 2);
CALL InserisciSkill('Web Development', 3);
CALL InserisciSkill('Web Development', 4);
CALL InserisciSkill('Web Development', 5);
CALL InserisciSkill('Database Management', 1);
CALL InserisciSkill('Database Management', 2);
CALL InserisciSkill('Database Management', 3);
CALL InserisciSkill('Database Management', 4);
CALL InserisciSkill('Database Management', 5);
CALL InserisciSkill('Cybersecurity', 1);
CALL InserisciSkill('Cybersecurity', 2);
CALL InserisciSkill('Cybersecurity', 3);
CALL InserisciSkill('Cybersecurity', 4);
CALL InserisciSkill('Cybersecurity', 5);
CALL InserisciSkill('Data Analysis', 1);
CALL InserisciSkill('Data Analysis', 2);
CALL InserisciSkill('Data Analysis', 3);
CALL InserisciSkill('Data Analysis', 4);
CALL InserisciSkill('Data Analysis', 5);
CALL InserisciSkill('Cloud Computing', 1);
CALL InserisciSkill('Cloud Computing', 2);
CALL InserisciSkill('Cloud Computing', 3);
CALL InserisciSkill('Cloud Computing', 4);
CALL InserisciSkill('Cloud Computing', 5);
CALL InserisciSkill('Networking', 1);
CALL InserisciSkill('Networking', 2);
CALL InserisciSkill('Networking', 3);
CALL InserisciSkill('Networking', 4);
CALL InserisciSkill('Networking', 5);
CALL InserisciSkill('Software Engineering', 1);
CALL InserisciSkill('Software Engineering', 2);
CALL InserisciSkill('Software Engineering', 3);
CALL InserisciSkill('Software Engineering', 4);
CALL InserisciSkill('Software Engineering', 5);
CALL InserisciSkill('Embedded Systems', 1);
CALL InserisciSkill('Embedded Systems', 2);
CALL InserisciSkill('Embedded Systems', 3);
CALL InserisciSkill('Embedded Systems', 4);
CALL InserisciSkill('Embedded Systems', 5);

-- Inserimento skill curriculum usando InserisciSkillCurriculum
CALL InserisciSkillCurriculum('dalia.barone@email.com','Web Development',4);
CALL InserisciSkillCurriculum('dalia.barone@email.com','Database Management',3);
CALL InserisciSkillCurriculum('mattia.veroni@email.com','Cybersecurity',4);
CALL InserisciSkillCurriculum('mattia.veroni@email.com','Networking',3);
CALL InserisciSkillCurriculum('sofia.neamtu@email.com','Data Analysis',3);
CALL InserisciSkillCurriculum('sofia.neamtu@email.com','AI',4);
CALL InserisciSkillCurriculum('sofia.neamtu@email.com','Machine Learning',5);

-- Inserimento progetti usando InserisciProgetto
CALL InserisciProgetto('SmartHome AI','Sistema di automazione domestica basato su AI','2025-03-01',5000,'2025-06-01','aperto','dalia.barone@email.com');
CALL InserisciProgetto('EduTech Platform','Piattaforma di e-learning avanzata','2025-02-20',8000,'2025-05-15','aperto','mattia.veroni@email.com');
CALL InserisciProgetto('CyberShield','Firewall AI per la sicurezza informatica','2025-01-15',12000,'2025-04-30','chiuso','sofia.neamtu@email.com');
CALL InserisciProgetto('AutoPilot System','Sistema di guida autonoma per auto','2025-02-10',15000,'2025-08-01','aperto','dalia.barone@email.com');
CALL InserisciProgetto('E-Health Monitor','Sistema di monitoraggio remoto della salute','2025-03-05',7000,'2025-06-30','aperto','mattia.veroni@email.com');

-- Classificazione progetti come hardware/software
INSERT INTO HARDWARE (Nome) VALUES 
('SmartHome AI'),
('AutoPilot System');

INSERT INTO SOFTWARE (Nome) VALUES 
('EduTech Platform'),
('CyberShield'),
('E-Health Monitor');

-- Inserimento componenti per progetti hardware
INSERT INTO COMPONENTI (Nome, Descrizione, Prezzo, Quantita) VALUES
('Sensore di Movimento','Sensore per rilevare il movimento in ambienti domestici',20.00,10),
('Modulo Bluetooth','Modulo di comunicazione Bluetooth per connessione remota',15.00,8),
('Camera HD','Telecamera ad alta risoluzione per sicurezza',50.00,5),
('Motore Elettrico','Motore per guida autonoma',120.00,4),
('Sensore LiDAR','Sensore per rilevamento ostacoli in guida autonoma',200.00,2),
('Batteria al Litio','Batteria ricaricabile ad alta capacita',90.00,6);

INSERT INTO COMPONENTI_HARDWARE (Nome_Progetto, Nome_Componente) VALUES
('SmartHome AI','Sensore di Movimento'),
('SmartHome AI','Modulo Bluetooth'),
('SmartHome AI','Camera HD'),
('AutoPilot System','Motore Elettrico'),
('AutoPilot System','Sensore LiDAR'),
('AutoPilot System','Batteria al Litio');

-- Inserimento profili per progetti software
INSERT INTO PROFILO (ID, Nome) VALUES
(1,'Esperto AI'),
(2,'Sviluppatore Full Stack'),
(3,'Analista di Sicurezza'),
(4,'Ingegnere DevOps'),
(5,'Data Scientist'),
(6,'Cloud Architect');

INSERT INTO PROFILO_SOFTWARE (Nome_Progetto, ID_Profilo) VALUES
('EduTech Platform',1),
('EduTech Platform',2),
('CyberShield',3),
('CyberShield',4),
('E-Health Monitor',5),
('E-Health Monitor',6);

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

-- Inserimento rewards usando InserisciReward
CALL InserisciReward('RWD1','Accesso beta esclusivo al prodotto',NULL,'SmartHome AI');
CALL InserisciReward('RWD2','T-shirt personalizzata del progetto',NULL,'EduTech Platform');
CALL InserisciReward('RWD3','Menzione speciale nel sito ufficiale',NULL,'CyberShield');
CALL InserisciReward('RWD4','Invito a evento esclusivo di presentazione',NULL,'AutoPilot System');
CALL InserisciReward('RWD5','Pacchetto premium di funzioni avanzate',NULL,'E-Health Monitor');

-- Inserimento finanziamenti usando FinanziaProgetto
CALL FinanziaProgetto('dalia.barone@email.com','SmartHome AI',100.00,'RWD1');
CALL FinanziaProgetto('mattia.veroni@email.com','EduTech Platform',200.00,'RWD2');
CALL FinanziaProgetto('sofia.neamtu@email.com','CyberShield',150.00,'RWD3');
CALL FinanziaProgetto('dalia.barone@email.com','AutoPilot System',300.00,'RWD4');
CALL FinanziaProgetto('mattia.veroni@email.com','E-Health Monitor',250.00,'RWD5');

-- Inserimento foto progetti
INSERT INTO FOTO (percorso, Nome_Progetto) VALUES 
('smarthome.jpg', 'SmartHome AI'),
('edutech.jpg', 'EduTech Platform'),
('cybershield.jpg', 'CyberShield'),
('autopilot.jpg','AutoPilot System'),
('ehealth.jpg','E-Health Monitor');

COMMIT;
