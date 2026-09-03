<?php
/**
 * DIGITURNO UNAD - API: Listar usuarios (solo admin)
 * GET: Retorna los usuarios del sistema (admin y dependencias) con nombre de dependencia.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

requierePerfilApi(['admin']);

try {
    ensureStructure();
    $db = getDB();

    $results = $db->query("
        SELECT u.id, u.nombre, u.apellido, u.usuario, u.rol, u.dependencia_id, u.activo, u.fecha_creacion,
               d.nombre as dependencia_nombre, d.codigo as dependencia_codigo
        FROM usuarios u
        LEFT JOIN dependencias d ON u.dependencia_id = d.id
        ORDER BY u.rol DESC, u.nombre ASC
    ");

    $usuarios = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $usuarios[] = $row;
    }

    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}