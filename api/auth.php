<?php
/**
 * DIGITURNO UNAD - Autenticacion por roles
 * Maneja la sesion PHP y el control de acceso segun el perfil:
 *   - recepcion   : acceso directo (sin credenciales), ve Recepcion + Pantalla
 *   - dependencia : requiere usuario/contraseña asignado por el admin, solo su panel
 *   - admin       : acceso total + Configuracion (gestion de usuarios)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Devuelve el rol activo en la sesion o null si no hay sesion.
 */
function sesionRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Devuelve el id de la dependencia asignada (rol dependencia) o null.
 */
function sesionDependenciaId() {
    return $_SESSION['dependencia_id'] ?? null;
}

/**
 * Devuelve el nombre del funcionario/usuario logueado o null.
 */
function sesionUsuarioNombre() {
    return $_SESSION['usuario_nombre'] ?? null;
}

/**
 * Devuelve el id del usuario logueado en la tabla usuarios o null.
 */
function sesionUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Inicia la sesion con el rol y datos asociados.
 */
function iniciarSesion($role, $data = []) {
    session_regenerate_id(true);
    $_SESSION['role'] = $role;
    $_SESSION['user_id'] = $data['user_id'] ?? null;
    $_SESSION['username'] = $data['username'] ?? null;
    $_SESSION['usuario_nombre'] = $data['usuario_nombre'] ?? null;
    $_SESSION['dependencia_id'] = $data['dependencia_id'] ?? null;
}

/**
 * Destruye la sesion actual.
 */
function cerrarSesion() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Guarda de PAGINAS HTML: redirige a login.php si el rol no esta permitido.
 * Devuelve el rol activo.
 */
function requierePerfil(array $rolesPermitidos) {
    $rol = sesionRole();
    if (!in_array($rol, $rolesPermitidos, true)) {
        header('Location: login.php');
        exit;
    }
    return $rol;
}

/**
 * Guarda de APIS JSON: responde 403 si el rol no esta permitido.
 * Devuelve el rol activo.
 */
function requierePerfilApi(array $rolesPermitidos) {
    $rol = sesionRole();
    if (!in_array($rol, $rolesPermitidos, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    return $rol;
}

/**
 * Destino segun el rol (para redirigir desde login.php).
 */
function destinoSegunRol($role) {
    if ($role === 'recepcion' || $role === 'admin') {
        return 'index.php';
    }
    if ($role === 'dependencia') {
        return 'dependencia.php';
    }
    return 'login.php';
}