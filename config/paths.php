<?php
// config/paths.php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
    define('BASE_URL', dirname($_SERVER['PHP_SELF']));
    
    // Caminhos absolutos
    define('INCLUDES_PATH', APP_ROOT . '/includes');
    define('CONFIG_PATH', APP_ROOT . '/config');
    define('API_PATH', APP_ROOT . '/api');
    define('ASSETS_PATH', APP_ROOT . '/assets');
    define('UPLOADS_PATH', APP_ROOT . '/uploads');
    define('LOGS_PATH', APP_ROOT . '/logs');
}
