<?php
/**
 * DIGITURNO UNAD - API: Marcar turno como no asistio
 * POST: Marca un turno como no_asistio
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

// Accion exclusiva del panel de dependencias: solo perfiles dependencia/admin.
$rol = requierePerfilApi(['dependencia', 'admin']);

try {
    ensureStructure();

    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['turno_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'turno_id requerido']);
        exit;
    }

    $turno_id = intval($data['turno_id']);

    $turno = $db->querySingle(
        "SELECT * FROM turnos WHERE id = $turno_id AND estado IN ('registrado', 'llamado')",
        true
    );

    if (!$turno) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Turno no encontrado o ya finalizado']);
        exit;
    }

    // Rol dependencia: solo puede operar turnos de SU dependencia.
    if ($rol === 'dependencia' && intval($turno['dependencia_id']) !== intval(sesionDependenciaId())) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Turno pertenece a otra dependencia']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE turnos
        SET estado = 'no_asistio',
            fecha_fin = datetime('now', 'localtime')
        WHERE id = :id
    ");
    $stmt->bindValue(':id', $turno_id, SQLITE3_INTEGER);
    $stmt->execute();

    $log_stmt = $db->prepare("
        INSERT INTO actividad_log (turno_id, accion, detalle, dependencia_id)
        VALUES (:turno_id, 'no_asistio', 'Visitante no se presento', :dep_id)
    ");
    $log_stmt->bindValue(':turno_id', $turno_id, SQLITE3_INTEGER);
    $log_stmt->bindValue(':dep_id', intval($turno['dependencia_id']), SQLITE3_INTEGER);
    $log_stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Turno marcado como no asistido'
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
