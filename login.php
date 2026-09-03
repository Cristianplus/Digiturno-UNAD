<?php
/**
 * DIGITURNO UNAD - Seleccion de Perfil (estilo Netflix)
 * Muestra los 3 perfiles: Recepcion, Dependencias, Administrador.
 */
require_once __DIR__ . '/api/auth.php';

// Si ya hay sesion activa, ir directamente al modulo correspondiente
$rolActivo = sesionRole();
if ($rolActivo) {
    header('Location: ' . destinoSegunRol($rolActivo));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiTurno UNAD - Seleccion de Perfil</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="profiles-screen">

    <div class="profiles-header">
        <div class="header-logo">UNAD</div>
        <h1>DigiTurno UNAD</h1>
        <p>Sistema de Gestion de Turnos - CEAD Acacias</p>
    </div>

    <div class="profiles-body">
        <h2 class="profiles-title">Quien esta utilizando el sistema?</h2>
        <div class="profiles-grid">

            <!-- Perfil: Recepcion (acceso directo) -->
            <div class="profile-card" data-perfil="recepcion" role="button" tabindex="0">
                <div class="profile-avatar avatar-recepcion">
                    <span>&#128100;</span>
                </div>
                <span class="profile-name">Recepcion</span>
                <span class="profile-sub">Registro de visitantes<br>+ Pantalla TV</span>
            </div>

            <!-- Perfil: Dependencias (requiere credenciales) -->
            <div class="profile-card" data-perfil="dependencia" role="button" tabindex="0">
                <div class="profile-avatar avatar-dependencia">
                    <span>&#128188;</span>
                </div>
                <span class="profile-name">Dependencias</span>
                <span class="profile-sub">Panel del funcionario<br>(con usuario)</span>
            </div>

            <!-- Perfil: Administrador (requiere credenciales) -->
            <div class="profile-card" data-perfil="admin" role="button" tabindex="0">
                <div class="profile-avatar avatar-admin">
                    <span>&#9881;&#65039;</span>
                </div>
                <span class="profile-name">Administrador</span>
                <span class="profile-sub">Acceso total<br>+ Configuracion</span>
            </div>

        </div>
    </div>

    <!-- Modal: Formulario de acceso (Dependencias / Administrador) -->
    <div class="modal-overlay" id="modal-login">
        <div class="modal">
            <div class="modal-header">
                <h3 id="login-titulo">Iniciar Sesion</h3>
                <button class="modal-close" onclick="cerrarLogin()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="login-error"></div>
                <div class="form-group">
                    <label for="login-usuario">Usuario</label>
                    <input type="text" class="form-control" id="login-usuario" placeholder="Ingrese su usuario" autocomplete="username" spellcheck="false">
                </div>
                <div class="form-group">
                    <label for="login-password">Contrasena</label>
                    <input type="password" class="form-control" id="login-password" placeholder="Ingrese su contrasena" autocomplete="current-password">
                </div>
                <p class="login-forgot">
                    <a href="#" id="link-olvide" onclick="mostrarOlvido(); return false;">Olvidaste tu contrasena?</a>
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="cerrarLogin()">Volver</button>
                <button class="btn btn-primary" id="btn-login" onclick="enviarLogin()">Ingresar</button>
            </div>
        </div>
    </div>

    <!-- Modal: Olvidaste tu contrasena -->
    <div class="modal-overlay" id="modal-olvido">
        <div class="modal">
            <div class="modal-header">
                <h3>Olvidaste tu contrasena?</h3>
                <button class="modal-close" onclick="cerrarOlvido()">&times;</button>
            </div>
            <div class="modal-body">
                <p>
                    Las credenciales de las dependencias las asigna el <strong>administrador del software</strong>.
                    No existe registro por cuenta propia.
                </p>
                <p style="margin-top:10px;">
                    Para restablecer su contrasena, solicitelo de manera <strong>presencial en el CEAD</strong>.
                    El administrador se la restablecera.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="cerrarOlvido()">Entendido</button>
            </div>
        </div>
    </div>

    <footer class="footer">
        DigiTurno UNAD &copy; 2026 - CEAD Acacias | Universidad Nacional Abierta y a Distancia
    </footer>

    <script src="assets/js/login.js"></script>
</body>
</html>