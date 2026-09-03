<?php
/**
 * DIGITURNO UNAD - API: Turnos para pantalla de espera
 * GET: Retorna turnos llamados recientes + proximos por llamar
 * Solo muestra turnos del dia actual
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    ensureStructure();

    $db = getDB();

    $hoy = date('Y-m-d');

    // Turnos en atencion (llamados): TODOS los llamados o en atencion del dia.
    // Se muestran todos a la vez y solo desaparecen al finalizar el turno.
    // Ordenados del mas antiguo en ser llamado al mas reciente.
    $en_atencion_results = $db->query("
        SELECT t.numero_turno, t.nombres, t.apellidos, d.nombre as dependencia_nombre, d.codigo as dependencia_codigo,
               t.estado, t.fecha_llamada, l.nombre as lista_nombre, l.codigo as lista_codigo
        FROM turnos t
        JOIN dependencias d ON t.dependencia_id = d.id
        LEFT JOIN listas l ON t.lista_id = l.id
        WHERE date(t.fecha_ingreso) = '$hoy'
          AND t.estado IN ('llamado', 'en_atencion')
        ORDER BY t.fecha_llamada ASC, t.id ASC
    ");
    $en_atencion = [];
    while ($row = $en_atencion_results->fetchArray(SQLITE3_ASSOC)) {
        $en_atencion[] = $row;
    }

    // Proximos turnos por llamar (estado registrado, ordenados por fecha de registro)
    $proximos_results = $db->query("
        SELECT t.numero_turno, d.codigo as dependencia_codigo, d.nombre as dependencia_nombre,
               t.fecha_ingreso, l.nombre as lista_nombre, l.codigo as lista_codigo
        FROM turnos t
        JOIN dependencias d ON t.dependencia_id = d.id
        LEFT JOIN listas l ON t.lista_id = l.id
        WHERE date(t.fecha_ingreso) = '$hoy'
          AND t.estado = 'registrado'
        ORDER BY t.id ASC
        LIMIT 20
    ");

    $proximos = [];
    while ($row = $proximos_results->fetchArray(SQLITE3_ASSOC)) {
        $proximos[] = $row;
    }

    // Estadisticas del dia
    $stats = [];
    $stats['total_hoy'] = $db->querySingle("SELECT COUNT(*) FROM turnos WHERE date(fecha_ingreso) = '$hoy'");
    $stats['atendidos'] = $db->querySingle("SELECT COUNT(*) FROM turnos WHERE date(fecha_ingreso) = '$hoy' AND estado = 'finalizado'");
    $stats['en_cola'] = $db->querySingle("SELECT COUNT(*) FROM turnos WHERE date(fecha_ingreso) = '$hoy' AND estado IN ('registrado', 'llamado', 'en_atencion')");

    echo json_encode([
        'success' => true,
        'en_atencion' => $en_atencion,
        'proximos' => $proximos,
        'estadisticas' => $stats,
        'fecha' => $hoy,
        'hora' => date('H:i:s')
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
