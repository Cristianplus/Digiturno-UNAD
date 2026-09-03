<?php
/**
 * DIGITURNO UNAD - Configuracion del Sistema
 */

// Ruta base del proyecto
define('BASE_PATH', dirname(__DIR__));

// Ruta de la base de datos
define('DB_PATH', BASE_PATH . '/db/digiturno.db');

// Ruta del directorio de la base de datos
define('DB_DIR', dirname(DB_PATH));

// Zona horaria
date_default_timezone_set('America/Bogota');

// ============================================================
// Verificaciones de entorno (compatibilidad AppServ / XAMPP / WAMP)
// ============================================================

// Verificar que la extension SQLite esta disponible en PHP
if (!extension_loaded('sqlite3') && !class_exists('SQLite3')) {
    if (file_exists(DB_PATH) && unlink(DB_PATH)) {
        // re-marcar para recrear
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'La extension SQLite3 no esta habilitada en PHP. Actívela en el archivo php.ini (extension=php_sqlite3.dll / extension=sqlite3). No necesitas XAMPP, tu servidor AppServ solo requiere activar SQLite.'
    ]);
    exit;
}

// Asegurar que el directorio de la BD existe y es escribible
if (!is_dir(DB_DIR)) {
    @mkdir(DB_DIR, 0777, true);
}

// Headers CORS para API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}
