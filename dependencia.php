<?php
/**
 * DIGITURNO UNAD - Panel de Dependencia
 * Acceso: perfil Dependencia (solo su dependencia asignada) o Administrador (elige cual).
 */
require_once __DIR__ . '/api/auth.php';
$rol = requierePerfil(['dependencia', 'admin']);
$esAdmin = ($rol === 'admin');
$esDependencia = ($rol === 'dependencia');

$depFijaId = null;
$depFijaNombre = null;
if ($esDependencia) {
    $depFijaId = sesionDependenciaId();
    $depFijaNombre = sesionUsuarioNombre();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiTurno UNAD - Panel de Dependencia</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="header">
        <div class="header-inner">
            <div class="header-brand">
                <img class="header-logo" src="assets/img/Logo_unad_color.png" alt="Logo UNAD">
                <div class="header-title">
                    <h1>DigiTurno UNAD</h1>
                    <p>Panel de Dependencia</p>
                </div>
            </div>
            <span class="header-badge" id="dep-badge"><?= $esAdmin ? 'Admin' : 'Dependencia' ?></span>
            <nav class="header-nav">
                <?php if ($esAdmin): ?>
                <a href="index.php">Recepcion</a>
                <a href="pantalla.php" target="_blank">Pantalla</a>
                <a href="dependencia.php" class="active">Dependencia</a>
                <a href="configuracion.php">Configuracion</a>
                <?php endif; ?>
                <a href="api/logout.php" title="Cerrar sesion">Salir</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <?php if ($esAdmin): ?>
        <!-- Selector de Dependencia (solo admin): listado completo visible a la vez -->
        <div class="card mb-2" id="card-seleccion">
            <div class="card-header">
                <h2>Seleccionar Dependencia</h2>
            </div>
            <div class="card-body">
                <p class="selector-hint">Seleccione la dependencia que desea atender</p>
                <div class="deps-grid" id="deps-grid">
                    <div class="empty-state">
                        <p>Cargando dependencias...</p>
                    </div>
                </div>

                <!-- Escuelas de ESC (se muestran al seleccionar ESC) -->
                <div class="escuelas-block hidden" id="escuelas-block">
                    <div class="escuelas-header">
                        <button class="btn btn-sm btn-outline" onclick="volverADependencias()">&larr; Volver a Dependencias</button>
                        <h3>Seleccionar Escuela</h3>
                    </div>
                    <div class="escs-grid" id="escs-grid"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Panel de Gestion (oculto inicialmente; para rol dependencia se muestra directo) -->
        <div id="panel-gestion" class="hidden">
            <div class="dependency-header">
                <h2 id="dep-titulo">Panel de Atencion</h2>
                <div class="d-flex gap-1">
                    <span class="header-badge" id="dep-hora"></span>
                </div>
            </div>

            <!-- Tabs -->
            <div class="dependency-tabs">
                <button class="dependency-tab active" data-tab="tab-pendientes" onclick="cambiarTab(this)">
                    Pendientes (<span id="count-pendientes">0</span>)
                </button>
                <button class="dependency-tab" data-tab="tab-historial" onclick="cambiarTab(this)">
                    Atendidos Hoy (<span id="count-historial">0</span>)
                </button>
            </div>

            <!-- Tab: Pendientes (en cola + llamados/en atencion) -->
            <div class="tab-content active" id="tab-pendientes">
                <div id="lista-pendientes">
                    <div class="empty-state">
                        <div class="empty-icon">&#128203;</div>
                        <h3>Sin turnos pendientes</h3>
                        <p>No hay visitantes en cola o llamados para esta dependencia</p>
                    </div>
                </div>
            </div>

            <!-- Tab: Historial del dia -->
            <div class="tab-content" id="tab-historial">
                <div id="lista-historial">
                    <div class="empty-state">
                        <div class="empty-icon">&#9989;</div>
                        <h3>Sin atenciones hoy</h3>
                        <p>Aun no se han atendido visitantes hoy</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Finalizar Atencion -->
    <div class="modal-overlay" id="modal-finalizar">
        <div class="modal">
            <div class="modal-header">
                <h3>Finalizar Atencion</h3>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Marcar como finalizado el turno:</p>
                <p style="font-weight:700; font-size:1.2rem; color:var(--unad-blue-dark); margin:8px 0;" id="modal-turno-num"></p>
                <p style="font-size:0.9rem; color:var(--unad-text);" id="modal-turno-nombre"></p>
                <div class="form-group mt-2">
                    <label>Observaciones (opcional)</label>
                    <textarea class="form-control" id="modal-obs" rows="3" placeholder="Notas sobre la atencion..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
                <button class="btn btn-success" id="btn-confirmar-finalizar">Finalizar y Egresar</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <footer class="footer">
        DigiTurno UNAD &copy; 2026 - CEAD Acacias | Universidad Nacional Abierta y a Distancia
    </footer>

    <script>
        const DEP_FIJA = <?= json_encode($depFijaId) ?>;
        const DEP_FIJA_NOMBRE = <?= json_encode($depFijaNombre) ?>;
    </script>
    <script src="assets/js/dependency.js"></script>
</body>
</html>