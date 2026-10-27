<?php
define('APP_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
$baseUrl = getenv('APP_BASE_URL');
define('BASE_URL', $baseUrl !== false && $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' : '/mvc/System-inline-Store/public/');
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'supermarket_db');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
