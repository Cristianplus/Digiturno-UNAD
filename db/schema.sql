-- ============================================================
-- DIGITURNO UNAD - Esquema de Base de Datos SQLite
-- Sistema de Gestion de Turnos para Visitantes
-- ============================================================

-- Tabla de dependencias universitarias
CREATE TABLE IF NOT EXISTS dependencias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    codigo TEXT NOT NULL UNIQUE,
    descripcion TEXT,
    usaListas INTEGER DEFAULT 0, -- 1 = requiere lista secundaria (ej. escuelas)
    activa INTEGER DEFAULT 1,
    fecha_creacion TEXT DEFAULT (datetime('now', 'localtime'))
);

-- Tabla de listas / sub-categorias (ej. Escuelas Academicas)
CREATE TABLE IF NOT EXISTS listas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dependencia_id INTEGER NOT NULL,
    nombre TEXT NOT NULL,
    codigo TEXT NOT NULL,
    descripcion TEXT,
    activa INTEGER DEFAULT 1,
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id),
    UNIQUE(dependencia_id, codigo)
);

-- Tabla principal de visitantes/turnos
CREATE TABLE IF NOT EXISTS turnos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero_turno TEXT NOT NULL,
    nombres TEXT NOT NULL,
    apellidos TEXT NOT NULL,
    cedula TEXT NOT NULL, -- la cedula puede repetirse si el turno previo fue finalizado/no_asistio
    dependencia_id INTEGER NOT NULL,
    lista_id INTEGER,
    tipo_visitante TEXT DEFAULT 'visitante', -- visitante, estudiante, funcionario
    estado TEXT DEFAULT 'registrado', -- registrado, llamado, en_atencion, finalizado, no_asistio
    fecha_ingreso TEXT DEFAULT (datetime('now', 'localtime')),
    fecha_llamada TEXT,
    fecha_inicio_atencion TEXT,
    fecha_fin TEXT,
    observaciones TEXT,
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id),
    FOREIGN KEY (lista_id) REFERENCES listas(id)
);

-- Tabla de log de actividad del sistema
CREATE TABLE IF NOT EXISTS actividad_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    turno_id INTEGER,
    accion TEXT NOT NULL,
    detalle TEXT,
    dependencia_id INTEGER,
    fecha TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (turno_id) REFERENCES turnos(id),
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id)
);

-- Tabla de configuracion del sistema
CREATE TABLE IF NOT EXISTS configuracion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parametro TEXT NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descripcion TEXT
);

-- Tabla de usuarios del sistema (perfiles de acceso)
-- rol: 'admin' (acceso total) o 'dependencia' (solo su dependencia)
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    apellido TEXT DEFAULT '',
    usuario TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    rol TEXT NOT NULL DEFAULT 'dependencia',
    dependencia_id INTEGER,
    lista_id INTEGER, -- escuela para funcionarios de ESC (usaListas)
    activo INTEGER DEFAULT 1,
    fecha_creacion TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id),
    FOREIGN KEY (lista_id) REFERENCES listas(id)
);

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Dependencias de la UNAD
INSERT OR IGNORE INTO dependencias (nombre, codigo, descripcion, usaListas) VALUES
('Registro y Control', 'RYC', 'Registro y Control Academico', 0),
('Consejeria academica y orientacion', 'VISAE', 'Consejeria Academica y Orientacion', 0),
('Bienestar integral unadista', 'BIU', 'Bienestar Integral Unadista', 0),
('Acompanamiento por Escuelas Academicas y programas', 'ESC', 'Acompanamiento por Escuelas Academicas y programas', 1),
('Soporte tecnologico y campus virtual', 'STCV', 'Soporte Tecnologico y Campus Virtual', 0),
('Servicios de biblioteca', 'BIUN', 'Servicios de Biblioteca - BiUNAD', 0),
('Sistema de atencion integral', 'SAI', 'Sistema de Atencion Integral', 0);

-- Escuelas Academicas (listas para la dependencia de Acompanamiento)
INSERT OR IGNORE INTO listas (dependencia_id, nombre, codigo, descripcion) VALUES
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias Administrativas, Contables, Economicas y de Negocios', 'ECACEN', 'ECACEN'),
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias Agricolas, Pecuarias y del Medio Ambiente', 'ECAPMA', 'ECAPMA'),
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias Basicas, Tecnologia e Ingenieria', 'ECBTI', 'ECBTI'),
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias de la Salud', 'ECISA', 'ECISA'),
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias de la Educacion', 'ECEDU', 'ECEDU'),
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias Sociales, Artes y Humanidades', 'ECSAH', 'ECSAH'),
((SELECT id FROM dependencias WHERE codigo = 'ESC'), 'Escuela de Ciencias Juridicas y Politicas', 'ECJP', 'ECJP');

-- Configuracion del sistema
INSERT OR IGNORE INTO configuracion (parametro, valor, descripcion) VALUES
('prefijo_turno', 'V', 'Prefijo para los numeros de turno'),
('turnos_por_dia', 'S', 'Reiniciar numeracion cada dia (S/N)'),
('max_turnos_simultaneos', '5', 'Maximo de turnos en pantalla a la vez'),
('mensaje_bienvenida', 'Bienvenido a la Universidad Nacional Abierta y a Distancia - UNAD', 'Mensaje en pantalla de espera'),
('tiempo_refresco', '5', 'Segundos entre actualizaciones de pantalla');

-- ============================================================
-- INDICES PARA RENDIMIENTO
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_turnos_estado ON turnos(estado);
CREATE INDEX IF NOT EXISTS idx_turnos_fecha ON turnos(fecha_ingreso);
CREATE INDEX IF NOT EXISTS idx_turnos_dependencia ON turnos(dependencia_id);
CREATE INDEX IF NOT EXISTS idx_turnos_numero ON turnos(numero_turno);

-- La cedula solo se bloquea mientras el turno esta activo (registrado/llamado/en_atencion).
-- Al finalizar o marcar no_asistio, la cedula se libera y el usuario puede re-registrarse.
CREATE UNIQUE INDEX IF NOT EXISTS idx_turnos_cedula_activos
    ON turnos(cedula) WHERE estado IN ('registrado', 'llamado', 'en_atencion');

CREATE INDEX IF NOT EXISTS idx_actividad_turno ON actividad_log(turno_id);
CREATE INDEX IF NOT EXISTS idx_listas_dependencia ON listas(dependencia_id);
