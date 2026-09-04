<?php
/**
 * DIGITURNO UNAD - Recepcion (Registro de visitantes)
 * Acceso: perfil Recepcion (directo) o Administrador.
 */
require_once __DIR__ . '/api/auth.php';
$rol = requierePerfil(['recepcion', 'admin']);
$esAdmin = ($rol === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiTurno UNAD - Recepcion</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="header">
        <div class="header-inner">
            <div class="header-brand">
                <img class="header-logo" src="assets/img/logo-unad-header.png" alt="Logo UNAD">
                <div class="header-title">
                    <h1>DigiTurno UNAD</h1>
                    <p>Sistema de Gestion de Turnos - CEAD Acacias</p>
                </div>
            </div>
            <span class="header-badge"> <?= htmlspecialchars(sesionUsuarioNombre() ?: '') ?></span>
            <nav class="header-nav">
                <a href="index.php" class="active">Registrar</a>
                <a href="pantalla.php" target="_blank">Pantalla</a>
                <?php if ($esAdmin): ?>
                <a href="dependencia.php">Dependencia</a>
                <a href="configuracion.php">Configuracion</a>
                <?php endif; ?>
                <a href="api/logout.php" title="Cerrar sesion">Salir</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="reception-layout">
            <!-- Formulario de Registro -->
            <div class="reception-form">
                <div class="card">
                    <div class="card-header">
                        <h2>Registro de Visitante</h2>
                    </div>
                    <div class="card-body">
                        <div id="alert-container"></div>

                        <form id="form-registro">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="nombres" placeholder="Ej: JUAN CARLOS" autocomplete="off" spellcheck="false" required maxlength="100" oninput="normalizarTexto(this)">
                                </div>
                                <div class="form-group">
                                    <label>Apellido <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="apellidos" placeholder="Ej: PEREZ GOMEZ" autocomplete="off" spellcheck="false" required maxlength="100" oninput="normalizarTexto(this)">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Cedula <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="cedula" placeholder="Ej: 1234567890" required maxlength="20">
                                </div>
                                <div class="form-group">
                                    <label>Tipo de Visitante</label>
                                    <select class="form-control" id="tipo_visitante">
                                        <option value="visitante">Visitante</option>
                                        <option value="estudiante">Estudiante</option>
                                        <option value="funcionario">Funcionario</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Dependencia a la que se dirige <span class="required">*</span></label>
                                <select class="form-control" id="dependencia_id" required>
                                    <option value="">Seleccione una dependencia...</option>
                                </select>
                            </div>

                            <div class="form-group hidden" id="grupo-escuela">
                                <label>Escuela Academica <span class="required">*</span></label>
                                <select class="form-control" id="lista_id">
                                    <option value="">Seleccione la escuela...</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg btn-block" id="btn-registrar">
                                Registrar Turno
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Turnos Recientes de Hoy -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2>Turnos Registrados Hoy <span id="historial-fecha" style="font-size:0.85rem; color:var(--unad-text-light); font-weight:500;"></span></h2>
                    </div>
                    <div class="card-body">
                        <div id="historial-container">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Cargando turnos...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmacion -->
    <div class="modal-overlay" id="modal-confirmacion">
        <div class="modal">
            <div class="modal-header">
                <h3>Confirmar Registro</h3>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Se registrara el siguiente visitante:</p>
                <div id="modal-datos" style="margin-top:12px; padding:12px; background:var(--unad-gray-light); border-radius:var(--unad-radius);"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
                <button class="btn btn-success" id="btn-confirmar">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Modal Turno Generado (emergente) -->
    <div class="modal-overlay" id="modal-turno-generado">
        <div class="modal">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--unad-green) 0%, #008C44 100%);">
                <h3>Turno Generado</h3>
            </div>
            <div class="modal-body text-center">
                <p style="font-size:0.9rem; color: var(--unad-text-light); margin-bottom:8px;">Numero de Turno</p>
                <p id="turno-numero-display" style="font-size:3rem; font-weight:900; color: var(--unad-blue-dark); margin-bottom:8px;">-</p>
                <p id="turno-dep-display" style="font-size:1rem; color: var(--unad-text);"></p>
                <p id="turno-hora-display" style="font-size:0.85rem; color: var(--unad-text-light); margin-top:6px;"></p>
                <button class="btn btn-success mt-3" id="btn-nuevo-registro">Nuevo Registro</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <footer class="footer">
        DigiTurno UNAD &copy; 2026 - CEAD Acacias | Universidad Nacional Abierta y a Distancia
    </footer>

    <script src="assets/js/reception.js"></script>
</body>
</html>
