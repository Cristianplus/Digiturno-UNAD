<?php
/**
 * DIGITURNO UNAD - Conexion a Base de Datos SQLite
 */

require_once __DIR__ . '/config.php';

function getDB() {
    try {
        if (!is_dir(DB_DIR)) {
            @mkdir(DB_DIR, 0777, true);
        }
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        $db->busyTimeout(10000);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error al abrir la base de datos SQLite: ' . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Verifica si una columna existe en una tabla.
 */
function columnaExiste($db, $tabla, $columna) {
    $cols = $db->query("PRAGMA table_info($tabla)");
    while ($c = $cols->fetchArray(SQLITE3_ASSOC)) {
        if ($c['name'] === $columna) {
            return true;
        }
    }
    return false;
}

/**
 * Verifica si una tabla existe.
 */
function tablaExiste($db, $tabla) {
    $r = $db->querySingle(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='$tabla'"
    );
    return !empty($r);
}

/**
 * Reconstruye la tabla turnos eliminando restricciones antiguas
 * (columna 'ocupacion', UNIQUE global de cedula) y agregando 'lista_id',
 * preservando los datos existentes. La cedula se libera al finalizar el turno.
 */
function migrarTablaTurnos($db) {
    // Recrear turnos sin la columna ocupacion, con lista_id y sin UNIQUE de cedula
    $db->exec("DROP TABLE IF EXISTS turnos_vieja");
    $db->exec("
        CREATE TABLE turnos_vieja (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_turno TEXT NOT NULL,
            nombres TEXT NOT NULL,
            apellidos TEXT NOT NULL,
            cedula TEXT NOT NULL, -- la cedula se libera al finalizar/no_asistio
            dependencia_id INTEGER NOT NULL,
            lista_id INTEGER,
            tipo_visitante TEXT DEFAULT 'visitante',
            estado TEXT DEFAULT 'registrado',
            fecha_ingreso TEXT DEFAULT (datetime('now', 'localtime')),
            fecha_llamada TEXT,
            fecha_inicio_atencion TEXT,
            fecha_fin TEXT,
            observaciones TEXT
        )
    ");

    // Copiar datos existentes cuando sea posible.
    // Se agrupa por cedula para no duplicar (la cedula es UNIQUE en la nueva estructura).
    $db->exec("
        INSERT INTO turnos_vieja (id, numero_turno, nombres, apellidos, cedula, dependencia_id, tipo_visitante, estado,
                                  fecha_ingreso, fecha_llamada, fecha_inicio_atencion, fecha_fin, observaciones)
        SELECT id, numero_turno, nombres, apellidos, cedula, dependencia_id,
               COALESCE(tipo_visitante, 'visitante'), COALESCE(estado, 'registrado'),
               fecha_ingreso, fecha_llamada, fecha_inicio_atencion, fecha_fin, observaciones
        FROM turnos
        GROUP BY cedula
    ");

    // Desactivar claves foraneas temporalmente y reemplazar
    $db->exec("PRAGMA foreign_keys = OFF");
    $db->exec("DROP TABLE turnos");
    $db->exec("ALTER TABLE turnos_vieja RENAME TO turnos");
    $db->exec("PRAGMA foreign_keys = ON");

    // Recrear indices
    $db->exec("CREATE INDEX IF NOT EXISTS idx_turnos_estado ON turnos(estado)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_turnos_fecha ON turnos(fecha_ingreso)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_turnos_dependencia ON turnos(dependencia_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_turnos_numero ON turnos(numero_turno)");
    $db->exec("DROP INDEX IF EXISTS idx_turnos_cedula");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_turnos_cedula_activos
        ON turnos(cedula) WHERE estado IN ('registrado', 'llamado', 'en_atencion')");
}

/**
 * Detecta si la tabla turnos conserva el UNIQUE global de cedula (columna
 * "UNIQUE" o el indice idx_turnos_cedula), que impide liberar la cedula
 * cuando un turno se finaliza o marca no_asistio.
 */
function turnosTieneCedulaUnica($db) {
    if (!tablaExiste($db, 'turnos')) {
        return false;
    }
    $idx = $db->query("PRAGMA index_list(turnos)");
    while ($row = $idx->fetchArray(SQLITE3_ASSOC)) {
        // Indice auto de la restriccion UNIQUE en columna
        if ($row['origin'] === 'u' && strpos((string)$row['name'], 'sqlite_autoindex') === 0) {
            return true;
        }
    }
    // Indice UNIQUE explicito global sobre cedula
    $old = $db->querySingle(
        "SELECT 1 FROM sqlite_master WHERE type='index' AND name='idx_turnos_cedula'"
    );
    return !empty($old);
}

/**
 * Crea/actualiza el esquema y aplica migraciones sobre bases de datos existentes.
 * Se ejecuta de forma segura en multiples pasos para no romper BD antiguas.
 */
function initDB() {
    $db = getDB();

    try {
        // =====================================================================
        // PASO 1: Migraciones estructurales sobre BD EXISTENTES (antes del schema)
        // =====================================================================

        // 1a. Reconstruir turnos si conserva la columna antigua 'ocupacion'
        //     o el UNIQUE global de cedula (impide liberar la cedula al finalizar)
        if (tablaExiste($db, 'turnos') &&
            (columnaExiste($db, 'turnos', 'ocupacion') || turnosTieneCedulaUnica($db))) {
            migrarTablaTurnos($db);
        }

        // 1b. Agregar columna usaListas a dependencias si no existe
        if (tablaExiste($db, 'dependencias') && !columnaExiste($db, 'dependencias', 'usaListas')) {
            $db->exec("ALTER TABLE dependencias ADD COLUMN usaListas INTEGER DEFAULT 0");
        }

        // 1c. Agregar columna lista_id a turnos si no existe (puede faltar si no
        //     se reconstruyo, p.ej. tablas creadas con otra version intermedia)
        if (tablaExiste($db, 'turnos') && !columnaExiste($db, 'turnos', 'lista_id')) {
            $db->exec("ALTER TABLE turnos ADD COLUMN lista_id INTEGER REFERENCES listas(id)");
        }

        // 1d. Agregar columna apellido a usuarios si no existe (separacion nombre/apellido)
        if (tablaExiste($db, 'usuarios') && !columnaExiste($db, 'usuarios', 'apellido')) {
            $db->exec("ALTER TABLE usuarios ADD COLUMN apellido TEXT DEFAULT ''");
        }

        // 1e. Agregar columna lista_id a usuarios si no existe (escuela para funcionarios de ESC)
        if (tablaExiste($db, 'usuarios') && !columnaExiste($db, 'usuarios', 'lista_id')) {
            $db->exec("ALTER TABLE usuarios ADD COLUMN lista_id INTEGER REFERENCES listas(id)");
        }

        // =====================================================================
        // PASO 2: Ejecutar schema (CREATE IF NOT EXISTS + datos iniciales)
        // =====================================================================
        $schema = file_get_contents(BASE_PATH . '/db/schema.sql');
        $db->exec($schema);

        // 2a. Revalidar/eliminar dependencias obsoletas
        $nuevosCodigos = ['RYC', 'VISAE', 'BIU', 'ESC', 'STCV', 'BIUN', 'SAI'];
        $placeholders = implode(',', array_fill(0, count($nuevosCodigos), '?'));
        $stmt = $db->prepare("DELETE FROM dependencias WHERE codigo NOT IN ($placeholders)");
        foreach ($nuevosCodigos as $i => $codigo) {
            $stmt->bindValue($i + 1, $codigo, SQLITE3_TEXT);
        }
        $stmt->execute();

        // 2b. Asegurar bandera de escuelas
        $db->exec("UPDATE dependencias SET usaListas = 1 WHERE codigo = 'ESC'");
        $db->exec("UPDATE dependencias SET usaListas = 0 WHERE codigo != 'ESC'");

        // 2c. Recargar listas (escuelas) para la dependencia ESC
        $db->exec("DELETE FROM listas");
        $escuelas = [
            ['ECACEN', 'Escuela de Ciencias Administrativas, Contables, Economicas y de Negocios'],
            ['ECAPMA', 'Escuela de Ciencias Agricolas, Pecuarias y del Medio Ambiente'],
            ['ECBTI', 'Escuela de Ciencias Basicas, Tecnologia e Ingenieria'],
            ['ECISA', 'Escuela de Ciencias de la Salud'],
            ['ECEDU', 'Escuela de Ciencias de la Educacion'],
            ['ECSAH', 'Escuela de Ciencias Sociales, Artes y Humanidades'],
            ['ECJP', 'Escuela de Ciencias Juridicas y Politicas'],
        ];
        $depEsc = $db->querySingle("SELECT id FROM dependencias WHERE codigo = 'ESC'");
        if ($depEsc) {
            $stmt = $db->prepare("
                INSERT OR IGNORE INTO listas (dependencia_id, nombre, codigo, descripcion)
                VALUES (:dep, :nombre, :codigo, :codigo)
            ");
            foreach ($escuelas as $e) {
                $stmt->reset();
                $stmt->bindValue(':dep', intval($depEsc), SQLITE3_INTEGER);
                $stmt->bindValue(':nombre', $e[1], SQLITE3_TEXT);
                $stmt->bindValue(':codigo', $e[0], SQLITE3_TEXT);
                $stmt->execute();
            }
        }

        // 2d. Indice UNIQUE parcial: la cedula solo se bloquea mientras el turno
        //     esta activo. Al finalizar/no_asistio se libera.
        try {
            $db->exec("DROP INDEX IF EXISTS idx_turnos_cedula");
            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_turnos_cedula_activos
                ON turnos(cedula) WHERE estado IN ('registrado', 'llamado', 'en_atencion')");
        } catch (Exception $e) {
            error_log('initDB cedula partial: ' . $e->getMessage());
        }

        // 2d2. Usuario administrador por defecto (si no existe ninguno)
        $countAdmin = $db->querySingle("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'");
        if (intval($countAdmin) === 0) {
            $stmtAdmin = $db->prepare("
                INSERT OR IGNORE INTO usuarios (nombre, usuario, password_hash, rol, activo)
                VALUES (:nombre, :usuario, :hash, 'admin', 1)
            ");
            $stmtAdmin->bindValue(':nombre', 'Administrador', SQLITE3_TEXT);
            $stmtAdmin->bindValue(':usuario', 'admin', SQLITE3_TEXT);
            $stmtAdmin->bindValue(':hash', password_hash('admin123', PASSWORD_DEFAULT), SQLITE3_TEXT);
            $stmtAdmin->execute();
        }

        // 2e. Finalizar turnos activos de DIAS ANTERIORES (pendientes abandonados),
        //     para liberar sus cedulas y mantener la BD coherente. Solo hoy importa.
        $hoy = date('Y-m-d');
        $stmtStale = $db->prepare("
            UPDATE turnos
            SET estado = 'finalizado',
                fecha_fin = COALESCE(fecha_fin, datetime('now', 'localtime')),
                observaciones = COALESCE(observaciones, '') || 'Expirado por cierre de dia.'
            WHERE estado IN ('registrado', 'llamado', 'en_atencion')
              AND date(fecha_ingreso) < :hoy
        ");
        $stmtStale->bindValue(':hoy', $hoy, SQLITE3_TEXT);
        $stmtStale->execute();
    } catch (Exception $e) {
        error_log('initDB DigiTurno: ' . $e->getMessage());
    }

    $db->close();
}

/**
 * Asegura que la estructura de la BD este al dia.
 * Se llama al inicio de cada API para que funcione sobre BD nuevas o existentes.
 */
function ensureStructure() {
    $db = getDB();

    try {
        // Si no existe la tabla principal, ejecutar esquema completo
        if (!tablaExiste($db, 'turnos')) {
            $db->close();
            initDB();
            return;
        }

        // Detectar si hacen falta migraciones
        $needsMigracion = false;

        if (columnaExiste($db, 'turnos', 'ocupacion')) {
            $needsMigracion = true; // requiere reconstruccion de turnos
        }
        if (turnosTieneCedulaUnica($db)) {
            $needsMigracion = true; // liberar cedula al finalizar requiere quitar UNIQUE global
        }
        if (!columnaExiste($db, 'turnos', 'lista_id')) {
            $needsMigracion = true;
        }
        if (!columnaExiste($db, 'dependencias', 'usaListas')) {
            $needsMigracion = true;
        }
        if (!tablaExiste($db, 'listas')) {
            $needsMigracion = true;
        }
        if (!tablaExiste($db, 'usuarios')) {
            $needsMigracion = true;
        }
        if (tablaExiste($db, 'usuarios') && !columnaExiste($db, 'usuarios', 'apellido')) {
            $needsMigracion = true; // separacion nombre/apellido
        }
        if (tablaExiste($db, 'usuarios') && !columnaExiste($db, 'usuarios', 'lista_id')) {
            $needsMigracion = true; // escuela para funcionarios de ESC
        }

        if ($needsMigracion) {
            $db->close();
            initDB();
            return;
        }

        // Validar que las dependencias nuevas esten presentes y limpias
        $countNuevas = $db->querySingle(
            "SELECT COUNT(*) FROM dependencias WHERE codigo IN ('RYC','VISAE','BIU','ESC','STCV','BIUN','SAI')"
        );
        if (intval($countNuevas) < 7) {
            $db->close();
            initDB();
            return;
        }

        // Finalizar turnos activos de dias anteriores (pendientes abandonados) en
        // cada arranque/consulta para liberar las cedulas del dia actual.
        $hoy = date('Y-m-d');
        $db->exec("
            UPDATE turnos
            SET estado = 'finalizado',
                fecha_fin = COALESCE(fecha_fin, datetime('now', 'localtime')),
                observaciones = COALESCE(observaciones, '') || 'Expirado por cierre de dia.'
            WHERE estado IN ('registrado', 'llamado', 'en_atencion')
              AND date(fecha_ingreso) < '$hoy'
        ");
    } catch (Exception $e) {
        error_log('ensureStructure: ' . $e->getMessage());
    }

    $db->close();
}
