<?php
/**
 * DIGITURNO UNAD - Inicializacion de Base de Datos
 * Ejecutar una sola vez para crear la BD y datos iniciales
 */

require_once __DIR__ . '/api/db.php';

try {
    initDB();
    echo json_encode([
        'success' => true,
        'message' => 'Base de datos inicializada correctamente',
        'path' => DB_PATH
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al inicializar: ' . $e->getMessage()
    ]);
}
