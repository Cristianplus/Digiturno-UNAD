<?php
/**
 * DIGITURNO UNAD - API: Historial / Reporte de turnos
 * GET: Retorna historial de turnos con filtros opcionales
 * Params: fecha_desde, fecha_hasta, dependencia_id, estado
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Historial visible desde Recepcion o Administrador.
requierePerfilApi(['recepcion', 'admin']);

try {
    ensureStructure();

    $db = getDB();

    $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
    $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
    $dep_id = $_GET['dependencia_id'] ?? null;
    $estado = $_GET['estado'] ?? null;

    $where = "WHERE date(t.fecha_ingreso) BETWEEN '$fecha_desde' AND '$fecha_hasta'";

    if ($dep_id) {
        $where .= " AND t.dependencia_id = " . intval($dep_id);
    }
    if ($estado) {
        $where .= " AND t.estado = '" . SQLite3::escapeString($estado) . "'";
    }

    $results = $db->query("
        SELECT t.*, d.nombre as dependencia_nombre, d.codigo as dependencia_codigo,
               l.nombre as lista_nombre, l.codigo as lista_codigo
        FROM turnos t
        JOIN dependencias d ON t.dependencia_id = d.id
        LEFT JOIN listas l ON t.lista_id = l.id
        $where
        ORDER BY t.fecha_ingreso DESC
        LIMIT 500
    ");

    $turnos = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $turnos[] = $row;
    }

    echo json_encode([
        'success' => true,
        'turnos' => $turnos,
        'total' => count($turnos)
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
