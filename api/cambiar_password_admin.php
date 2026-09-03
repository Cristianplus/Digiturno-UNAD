<?php
/**
 * DIGITURNO UNAD - API: Cambiar contrasena del administrador (solo admin)
 * POST: {password_actual, password_nueva}
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

requierePerfilApi(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$passwordActual = (string)($data['password_actual'] ?? '');
$passwordNueva = trim($data['password_nueva'] ?? '');

if ($passwordActual === '' || $passwordNueva === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingrese la contrasena actual y la nueva']);
    exit;
}
if (strlen($passwordNueva) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contrasena nueva debe tener al menos 6 caracteres']);
    exit;
}

try {
    ensureStructure();
    $db = getDB();

    $userId = sesionUserId();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesion de administrador no valida']);
        $db->close();
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id AND rol = 'admin' AND activo = 1");
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $admin = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$admin) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Administrador no encontrado']);
        $db->close();
        exit;
    }

    if (!password_verify($passwordActual, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'La contrasena actual no es correcta']);
        $db->close();
        exit;
    }

    $stmtUpd = $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
    $stmtUpd->bindValue(':hash', password_hash($passwordNueva, PASSWORD_DEFAULT), SQLITE3_TEXT);
    $stmtUpd->bindValue(':id', $userId, SQLITE3_INTEGER);
    $stmtUpd->execute();

    echo json_encode(['success' => true, 'message' => 'Contrasena del administrador actualizada']);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}