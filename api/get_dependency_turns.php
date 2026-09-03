<?php
/**
 * DIGITURNO UNAD - API: Turnos para vista de dependencia
 * GET: Retorna los turnos asignados a una dependencia especifica
 * Params: dependencia_id
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Control de acceso: solo perfiles dependencia/admin.
// El rol dependencia solo puede ver SU dependencia asignada (ignora el parametro).
$rol = sesionRole();
if ($rol === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$dep_id = null;
if ($rol === 'dependencia') {
    $dep_id = sesionDependenciaId();
} else if ($rol === 'admin') {
    $dep_id = $_GET['dependencia_id'] ?? null;
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

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

    // Turnos pendientes para esta dependencia:
    // registrado (en cola, se llama), llamado y en_atencion.
    // Ordenados del mas antiguo al mas reciente (id ASC).
    $pendientes_results = $db->query("
        SELECT t.*, l.nombre as lista_nombre, l.codigo as lista_codigo
        FROM turnos t
        LEFT JOIN listas l ON t.lista_id = l.id
        WHERE t.dependencia_id = $dep_id
          AND date(t.fecha_ingreso) = '$hoy'
          AND t.estado IN ('registrado', 'llamado', 'en_atencion')
        ORDER BY t.id ASC
    ");
    $pendientes = [];
    while ($row = $pendientes_results->fetchArray(SQLITE3_ASSOC)) {
        $pendientes[] = $row;
    }
    $en_cola = [];

    // Turnos atendidos hoy en esta dependencia
    $atendidos_results = $db->query("
        SELECT t.*, l.nombre as lista_nombre, l.codigo as lista_codigo
        FROM turnos t
        LEFT JOIN listas l ON t.lista_id = l.id
        WHERE t.dependencia_id = $dep_id
          AND date(t.fecha_ingreso) = '$hoy'
          AND t.estado IN ('finalizado', 'no_asistio')
        ORDER BY t.fecha_fin DESC
    ");
    $atendidos = [];
    while ($row = $atendidos_results->fetchArray(SQLITE3_ASSOC)) {
        $atendidos[] = $row;
    }

    // Info de la dependencia
    $dep = $db->querySingle(
        "SELECT id, nombre, codigo FROM dependencias WHERE id = $dep_id",
        true
    );

    echo json_encode([
        'success' => true,
        'dependencia' => $dep,
        'pendientes' => $pendientes,
        'en_cola' => $en_cola,
        'atendidos_hoy' => $atendidos,
        'fecha' => $hoy
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
