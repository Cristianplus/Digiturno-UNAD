<?php
/**
 * DIGITURNO UNAD - Configuracion (gestion de usuarios)
 * Acceso: solo Administrador.
 */
require_once __DIR__ . '/api/auth.php';
$rol = requierePerfil(['admin']);
$esAdmin = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiTurno UNAD - Configuracion</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="header-logo">UNAD</div>
                <div class="header-title">
                    <h1>DigiTurno UNAD</h1>
                    <p>Configuracion del Sistema</p>
                </div>
            </div>
            <span class="header-badge">Admin <?= htmlspecialchars(sesionUsuarioNombre() ?: '') ?></span>
            <nav class="header-nav">
                <a href="index.php">Recepcion</a>
                <a href="pantalla.php" target="_blank">Pantalla</a>
                <a href="dependencia.php">Dependencia</a>
                <a href="configuracion.php" class="active">Configuracion</a>
                <a href="api/logout.php" title="Cerrar sesion">Salir</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="config-layout">

            <!-- Gestion de usuarios de dependencias -->
            <div class="card">
                <div class="card-header">
                    <h2>Usuarios de Dependencias</h2>
                    <button class="btn btn-sm btn-warning" onclick="abrirModalNuevo()">+ Nuevo Funcionario</button>
                </div>
                <div class="card-body">
                    <div id="alert-container"></div>
                    <div id="usuarios-container">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Cargando usuarios...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contraseña del administrador -->
            <div class="card">
                <div class="card-header">
                    <h2>Contrasena del Administrador</h2>
                </div>
                <div class="card-body">
                    <p style="font-size:0.85rem; color:var(--unad-text-light); margin-bottom:16px;">
                        Cambie la contrasena del administrador del sistema. Se recomienda cambiar la
                        contrasena por defecto al primer ingreso.
                    </p>
                    <div id="alert-admin"></div>
                    <div class="form-group">
                        <label>Contrasena actual</label>
                        <input type="password" class="form-control" id="adm-pass-actual" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label>Nueva contrasena</label>
                        <input type="password" class="form-control" id="adm-pass-nueva" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Confirmar nueva contrasena</label>
                        <input type="password" class="form-control" id="adm-pass-confirm" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary btn-block" onclick="cambiarPasswordAdmin()">Cambiar Contrasena</button>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Nuevo/Editar funcionario -->
    <div class="modal-overlay" id="modal-usuario">
        <div class="modal">
            <div class="modal-header">
                <h3 id="usuario-modal-titulo">Nuevo Funcionario</h3>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="usuario-modal-error"></div>
                <div class="form-group">
                    <label>Nombre del Funcionario <span class="required">*</span></label>
                    <input type="text" class="form-control" id="usr-nombre" placeholder="Ej: LUIS GARCIA" maxlength="120" spellcheck="false">
                </div>
                <div class="form-group">
                    <label>Usuario <span class="required">*</span></label>
                    <input type="text" class="form-control" id="usr-usuario" placeholder="Ej: luis.garcia" maxlength="50" autocomplete="off" spellcheck="false">
                </div>
                <div class="form-group">
                    <label id="usr-pass-label">Contrasena <span class="required">*</span></label>
                    <input type="password" class="form-control" id="usr-password" placeholder="Contrasena del funcionario" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Dependencia Asignada <span class="required">*</span></label>
                    <select class="form-control" id="usr-dependencia">
                        <option value="">Seleccione la dependencia...</option>
                    </select>
                </div>
                <div class="form-group hidden" id="usr-activo-grupo">
                    <label class="d-flex align-center gap-1" style="font-weight:500;">
                        <input type="checkbox" id="usr-activo" checked> Usuario activo
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
                <button class="btn btn-success" id="btn-guardar-usuario" onclick="guardarUsuario()">Guardar</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <footer class="footer">
        DigiTurno UNAD &copy; 2026 - CEAD Acacias | Universidad Nacional Abierta y a Distancia
    </footer>

    <script src="assets/js/configuracion.js"></script>
</body>
</html>