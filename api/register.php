<?php
/**
 * DIGITURNO UNAD - API: Registrar nuevo turno/visitante
 * POST: Registra un nuevo visitante y genera su turno
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// El registro de visitantes se hace desde el perfil Recepcion o Administrador.
$rol = requierePerfilApi(['recepcion', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

try {
    // Asegurar estructura de la BD (nueva o existente)
    ensureStructure();

    $db = getDB();

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos no proporcionados']);
        exit;
    }

    // Validar campos requeridos
    $required = ['nombres', 'apellidos', 'cedula', 'dependencia_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Campo requerido: $field"]);
            exit;
        }
    }

    // La cedula solo se bloquea mientras exista un turno ACTIVO (registrado, llamado
    // o en atencion) del DIA ACTUAL. Si todos los turnos previos de esa cedula estan
    // finalizados, no_asistio o corresponden a dias anteriores (pendientes abandonados),
    // la cedula queda liberada y puede re-registrase.
    $cedula = trim($data['cedula']);
    $hoy = date('Y-m-d');
    $cedulaActiva = $db->querySingle(
        "SELECT id FROM turnos
         WHERE cedula = '" . SQLite3::escapeString($cedula) . "'
           AND estado IN ('registrado', 'llamado', 'en_atencion')
           AND date(fecha_ingreso) = '$hoy'
         LIMIT 1"
    );
    if ($cedulaActiva) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Esta cedula ya tiene un turno pendiente o en atencion hoy. Espere a que sea finalizado para volver a registrarse.']);
        exit;
    }

    // Obtener prefijo de turno
    $prefijo = $db->querySingle("SELECT valor FROM configuracion WHERE parametro = 'prefijo_turno'");
    if (!$prefijo) $prefijo = 'V';

    // Generar numero de turno (prefijo + incremental por dia)
    $hoy = date('Y-m-d');
    $count = $db->querySingle(
        "SELECT COUNT(*) FROM turnos WHERE date(fecha_ingreso) = '$hoy'"
    );
    $numero = $prefijo . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    // Verificar que no exista el numero (por si se reinicia)
    while (true) {
        $exists = $db->querySingle(
            "SELECT COUNT(*) FROM turnos WHERE numero_turno = '$numero'"
        );
        if ($exists == 0) break;
        $count++;
        $numero = $prefijo . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    // Verificar si la dependencia existe y esta activa
    $dep = $db->querySingle(
        "SELECT * FROM dependencias WHERE id = " . intval($data['dependencia_id']) . " AND activa = 1",
        true
    );
    if (!$dep) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dependencia no valida o inactiva']);
        exit;
    }

    // Si la dependencia requiere lista (escuelas), validar lista_id
    $lista_id = null;
    if (intval($dep['usaListas']) === 1) {
        if (empty($data['lista_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar la escuela academica']);
            exit;
        }
        $lista = $db->querySingle(
            "SELECT id FROM listas WHERE id = " . intval($data['lista_id']) . " AND dependencia_id = " . intval($data['dependencia_id']) . " AND activa = 1"
        );
        if (!$lista) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Escuela academica no valida']);
            exit;
        }
        $lista_id = intval($data['lista_id']);
    }

    // Insertar turno
    $stmt = $db->prepare("
        INSERT INTO turnos (numero_turno, nombres, apellidos, cedula, dependencia_id, lista_id, tipo_visitante)
        VALUES (:numero, :nombres, :apellidos, :cedula, :dep_id, :lista_id, :tipo)
    ");

    $stmt->bindValue(':numero', $numero, SQLITE3_TEXT);
    $stmt->bindValue(':nombres', mb_strtoupper(trim($data['nombres']), 'UTF-8'), SQLITE3_TEXT);
    $stmt->bindValue(':apellidos', mb_strtoupper(trim($data['apellidos']), 'UTF-8'), SQLITE3_TEXT);
    $stmt->bindValue(':cedula', trim($data['cedula']), SQLITE3_TEXT);
    if ($lista_id) {
        $stmt->bindValue(':lista_id', $lista_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':lista_id', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':dep_id', intval($data['dependencia_id']), SQLITE3_INTEGER);
    $stmt->bindValue(':tipo', $data['tipo_visitante'] ?? 'visitante', SQLITE3_TEXT);

    $result = $stmt->execute();

    if ($result) {
        $turno_id = $db->lastInsertRowID();

        // Registrar en log
        $log_stmt = $db->prepare("
            INSERT INTO actividad_log (turno_id, accion, detalle, dependencia_id)
            VALUES (:turno_id, 'registro', :detalle, :dep_id)
        ");
        $log_stmt->bindValue(':turno_id', $turno_id, SQLITE3_INTEGER);
        $log_stmt->bindValue(':detalle', 'Turno registrado desde recepcion', SQLITE3_TEXT);
        $log_stmt->bindValue(':dep_id', intval($data['dependencia_id']), SQLITE3_INTEGER);
        $log_stmt->execute();

        // Obtener datos del turno registrado
        $turno = $db->querySingle("
            SELECT t.*, d.nombre as dependencia_nombre, d.codigo as dependencia_codigo
            FROM turnos t
            JOIN dependencias d ON t.dependencia_id = d.id
            WHERE t.id = $turno_id
        ", true);

        echo json_encode([
            'success' => true,
            'message' => 'Turno registrado exitosamente',
            'turno' => $turno
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al registrar turno']);
    }

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
