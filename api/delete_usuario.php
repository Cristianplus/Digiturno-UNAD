<?php
/**
 * DIGITURNO UNAD - API: Eliminar usuario de dependencia (solo admin)
 * POST: {id}
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
$id = isset($data['id']) ? intval($data['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'id requerido']);
    exit;
}

try {
    ensureStructure();
    $db = getDB();

    // Solo usuarios del rol dependencia (nunca el admin)
    $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id AND rol = 'dependencia'");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();

    if ($db->changes() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o no editable']);
        $db->close();
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Funcionario eliminado correctamente']);

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}