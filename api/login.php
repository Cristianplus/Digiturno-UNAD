<?php
/**
 * DIGITURNO UNAD - API: Iniciar sesion segun perfil
 * POST: {perfil: 'recepcion'|'dependencia'|'admin', usuario?, password?}
 * - recepcion   : acceso directo sin credenciales
 * - dependencia : usuario/contraseña asignados por el admin
 * - admin       : usuario/contraseña del administrador
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['perfil'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Perfil requerido']);
    exit;
}

$perfil = $data['perfil'];

// Perfil Recepcion: acceso directo, sin credenciales.
if ($perfil === 'recepcion') {
    iniciarSesion('recepcion', ['usuario_nombre' => 'Recepcion']);
    echo json_encode(['success' => true, 'message' => 'Bienvenido', 'redirect' => destinoSegunRol('recepcion')]);
    exit;
}

if ($perfil !== 'dependencia' && $perfil !== 'admin') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Perfil no valido']);
    exit;
}

$usuario = trim($data['usuario'] ?? '');
$password = (string)($data['password'] ?? '');

if ($usuario === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingrese usuario y contraseña']);
    exit;
}

try {
    ensureStructure();
    $db = getDB();

    $stmt = $db->prepare("
        SELECT * FROM usuarios
        WHERE usuario = :usuario COLLATE NOCASE AND rol = :rol
        LIMIT 1
    ");
    $stmt->bindValue(':usuario', $usuario, SQLITE3_TEXT);
    $stmt->bindValue(':rol', $perfil, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado para este perfil']);
        $db->close();
        exit;
    }

    if (intval($user['activo']) !== 1) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Este usuario esta desactivado. Contacte al administrador.']);
        $db->close();
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        $db->close();
        exit;
    }

    $dependencia_id = isset($user['dependencia_id']) ? intval($user['dependencia_id']) : null;
    iniciarSesion($perfil, [
        'user_id' => intval($user['id']),
        'username' => $user['usuario'],
        'usuario_nombre' => $user['nombre'],
        'dependencia_id' => $dependencia_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Bienvenido ' . $user['nombre'],
        'redirect' => destinoSegunRol($perfil)
    ]);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}