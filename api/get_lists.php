<?php
/**
 * DIGITURNO UNAD - API: Obtener listas (escuelas) de una dependencia
 * GET: Retorna las listas/sub-categorias de una dependencia (ej. escuelas)
 * Params: dependencia_id
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$dep_id = $_GET['dependencia_id'] ?? null;

if (!$dep_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'dependencia_id requerido']);
    exit;
}

try {
    ensureStructure();

    $db = getDB();

    $hoy = date('Y-m-d');
    $dep_id = intval($dep_id);

    $results = $db->query(
        "SELECT id, nombre, codigo, descripcion FROM listas
         WHERE dependencia_id = $dep_id AND activa = 1 ORDER BY nombre"
    );
    $listas = [];

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        // Personas en espera (turnos registrados, aun sin llamar) para esta escuela hoy
        $row['en_espera'] = $db->querySingle(
            "SELECT COUNT(*) FROM turnos
             WHERE lista_id = " . intval($row['id']) . "
               AND date(fecha_ingreso) = '$hoy'
               AND estado = 'registrado'"
        );
        $listas[] = $row;
    }

    echo json_encode([
        'success' => true,
        'listas' => $listas
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
