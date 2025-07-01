<?php

if (!defined('BOSTARTER_APP')) {
    die('Accesso non autorizzato');
}

// Percorsi
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/assets/images/uploads');

// URL base
define('BASE_URL', 'http://localhost/bostarter');  // Modifica secondo il tuo setup
define('ASSETS_URL', BASE_URL . '/public/assets');
define('API_URL', BASE_URL . '/api');

// Limiti applicazione
define('MAX_PROJECT_IMAGES', 5);
define('MAX_REWARD_PER_PROJECT', 10);
define('MIN_PASSWORD_LENGTH', 6);
define('MAX_DESCRIPTION_LENGTH', 2000);
define('MAX_COMMENT_LENGTH', 500);

// Stati progetto
define('PROJECT_OPEN', 'aperto');
define('PROJECT_CLOSED', 'chiuso');

// Ruoli utente
define('ROLE_USER', 'user');
define('ROLE_CREATOR', 'creator');
define('ROLE_ADMIN', 'admin');

// Messaggi di errore comuni
define('ERROR_UNAUTHORIZED', 'Accesso non autorizzato');
define('ERROR_INVALID_INPUT', 'Dati inseriti non validi');
define('ERROR_DATABASE', 'Errore del database');
define('ERROR_FILE_UPLOAD', 'Errore nell\'upload del file');

// Formati data
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');