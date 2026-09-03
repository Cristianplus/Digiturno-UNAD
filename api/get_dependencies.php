<?php
/**
 * DIGITURNO UNAD - API: Obtener dependencias
 * GET: Lista todas las dependencias activas
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    ensureStructure();

    $db = getDB();

    $results = $db->query("SELECT id, nombre, codigo, descripcion, usaListas FROM dependencias WHERE activa = 1 ORDER BY nombre");
    $dependencias = [];

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $dependencias[] = $row;
    }

    echo json_encode([
        'success' => true,
        'dependencias' => $dependencias
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
