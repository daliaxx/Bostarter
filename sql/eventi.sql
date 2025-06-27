-- Evento pianificato: sincronizza stati ogni notte alle 00:05
DELIMITER //
CREATE EVENT IF NOT EXISTS bostarter_sync_progetti
  ON SCHEDULE
      EVERY 1 DAY
      STARTS TIMESTAMP(CURRENT_DATE, '00:05:00')
  DO
    UPDATE PROGETTO
       SET Stato = 'scaduto'
     WHERE Stato = 'aperto'
       AND Data_Limite < CURDATE();
//
DELIMITER ;
