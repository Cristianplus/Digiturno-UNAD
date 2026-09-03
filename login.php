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
        <img class="header-logo" src="assets/img/Logo_unad_color.png" alt="Logo UNAD">
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
                    <label for="login-password">Contraseña</label>
                    <div class="pass-wrap">
                        <input type="password" class="form-control" id="login-password" placeholder="Ingrese su contraseña" autocomplete="current-password" data-caps-indicator="caps-login">
                        <button type="button" class="pass-toggle" tabindex="-1" data-pass-toggle="login-password" onclick="togglePassVisibility(this)" aria-label="Mostrar u ocultar contraseña" title="Mostrar / ocultar contraseña">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pass-eyes-eye-off" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <p class="login-forgot">
                    <span class="caps-lock-aviso" id="caps-login" style="display:none">&#8682; Bloq Mayus activado</span>
                    <a href="#" id="link-olvide" onclick="mostrarOlvido(); return false;">Olvidaste tu contraseña?</a>
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
                <h3>Olvidaste tu contraseña?</h3>
                <button class="modal-close" onclick="cerrarOlvido()">&times;</button>
            </div>
            <div class="modal-body">
                <p>
                    Las credenciales de las dependencias las asigna el <strong>administrador del software</strong>.
                    No existe registro por cuenta propia.
                </p>
                <p style="margin-top:10px;">
                    Para restablecer su contraseña, solicitelo de manera <strong>presencial en el CEAD</strong>.
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