<?php
/**
 * DIGITURNO UNAD - Configuracion (gestion de usuarios)
 * Acceso: solo Administrador.
 */
require_once __DIR__ . '/api/auth.php';
requierePerfil(['admin']);
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
                <img class="header-logo" src="assets/img/logo-unad-header.png" alt="Logo UNAD">
                <div class="header-title">
                    <h1>DigiTurno UNAD</h1>
                    <p>Configuracion del Sistema</p>
                </div>
            </div>
            <span class="header-badge"> <?= htmlspecialchars(sesionUsuarioNombre() ?: 'Administrador') ?></span>
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
                    <h2>Contraseña del Administrador</h2>
                </div>
                <div class="card-body">
                    <p style="font-size:0.85rem; color:var(--unad-text-light); margin-bottom:16px;">
                        Cambie la contraseña del administrador del sistema. Se recomienda cambiar la
                        contraseña por defecto al primer ingreso.
                    </p>
                    <div id="alert-admin"></div>
                    <div class="form-group">
                        <label>Contraseña actual</label>
                        <div class="pass-wrap">
                            <input type="password" class="form-control" id="adm-pass-actual" autocomplete="current-password" data-caps-indicator="caps-admin-actual">
                            <button type="button" class="pass-toggle" tabindex="-1" data-pass-toggle="adm-pass-actual" onclick="togglePassVisibility(this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar / ocultar contraseña">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye-off" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="caps-lock-slot">
                            <span class="caps-lock-text" id="caps-admin-actual" style="display:none">Bloq Mayus activado</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nueva contraseña</label>
                        <div class="pass-wrap">
                            <input type="password" class="form-control" id="adm-pass-nueva" autocomplete="new-password" data-caps-indicator="caps-admin-nueva">
                            <button type="button" class="pass-toggle" tabindex="-1" data-pass-toggle="adm-pass-nueva" onclick="togglePassVisibility(this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar / ocultar contraseña">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye-off" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="caps-lock-slot">
                            <span class="caps-lock-text" id="caps-admin-nueva" style="display:none">Bloq Mayus activado</span>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-block" onclick="cambiarPasswordAdmin()">Cambiar Contraseña</button>
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
                    <input type="text" class="form-control" id="usr-nombre" placeholder="Ej: LUIS" maxlength="60" spellcheck="false" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-group">
                    <label>Apellido <span class="required">*</span></label>
                    <input type="text" class="form-control" id="usr-apellido" placeholder="Ej: GARCIA" maxlength="60" spellcheck="false" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-group">
                    <label>Usuario <span class="required">*</span></label>
                    <input type="text" class="form-control" id="usr-usuario" placeholder="Se genera automaticamente" autocomplete="off" spellcheck="false" readonly>
                </div>
                <div class="form-group">
                    <label id="usr-pass-label">Contraseña <span class="required">*</span></label>
                    <div class="pass-wrap">
                        <input type="password" class="form-control" id="usr-password" placeholder="Contraseña del funcionario" autocomplete="new-password" data-caps-indicator="caps-usuario">
                        <button type="button" class="pass-toggle" id="usr-pass-toggle" tabindex="-1" data-pass-toggle="usr-password" onclick="toggleUsuarioPassword()" aria-label="Mostrar u ocultar contraseña" title="Mostrar / ocultar contraseña">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye-off" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    <div class="caps-lock-slot">
                        <span class="caps-lock-text" id="caps-usuario" style="display:none">Bloq Mayus activado</span>
                        <small>confirma la contraseña antes de guardar</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>Dependencia Asignada <span class="required">*</span></label>
                    <select class="form-control" id="usr-dependencia">
                        <option value="">Seleccione la dependencia...</option>
                    </select>
                </div>
                <div class="form-group hidden" id="grupo-escuela-funcionario">
                    <label>Escuela Academica <span class="required">*</span></label>
                    <select class="form-control" id="usr-lista">
                        <option value="">Seleccione la escuela...</option>
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