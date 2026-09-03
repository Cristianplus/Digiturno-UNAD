<?php
/**
 * DIGITURNO UNAD - API: Crear o actualizar usuario de dependencia (solo admin)
 * POST: {id? (para editar), nombre, usuario, password? (requerida en alta), dependencia_id, activo?}
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
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos no proporcionados']);
    exit;
}

/**
 * Genera automaticamente el nombre de usuario en minusculas a partir de
 * nombre + apellido + id de creacion (solo alfanumericos, sin tildes/espacios).
 * Ej: LUIS + PEREZ + id 1 => luisperez0001
 */
function generarNombreUsuario($nombre, $apellido, $id) {
    $base = mb_strtolower($nombre . $apellido, 'UTF-8');
    $sinAcentos = strtr($base, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ]);
    // Quitar caracteres no alfanumericos (espacios, puntos, guiones, etc.)
    $limpio = preg_replace('/[^a-z0-9]/', '', $sinAcentos);
    if ($limpio === '') {
        $limpio = 'usuario';
    }
    return $limpio . sprintf('%04d', $id);
}

try {
    ensureStructure();
    $db = getDB();

    $id = isset($data['id']) ? intval($data['id']) : 0;
    $nombre = trim($data['nombre'] ?? '');
    $apellido = trim($data['apellido'] ?? '');
    $usuario = trim($data['usuario'] ?? '');
    $password = trim($data['password'] ?? '');
    $dependencia_id = isset($data['dependencia_id']) ? intval($data['dependencia_id']) : 0;
    $activo = isset($data['activo']) ? (intval($data['activo']) === 1 ? 1 : 0) : 1;

    if ($nombre === '' || $dependencia_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre y dependencia son obligatorios']);
        $db->close();
        exit;
    }

    // Validar que la dependencia exista
    $dep = $db->querySingle("SELECT id FROM dependencias WHERE id = $dependencia_id AND activa = 1");
    if (!$dep) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dependencia no valida']);
        $db->close();
        exit;
    }

    if ($id > 0) {
        // EDITAR: el nombre de usuario se mantiene; se valida si viene en el request.
        if ($usuario === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre de usuario es obligatorio']);
            $db->close();
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $usuario)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El usuario solo puede contener letras, numeros, punto, guion o guion bajo']);
            $db->close();
            exit;
        }
        // Verificar unicidad de usuario (salvo el mismo registro)
        $stmtCheck = $db->prepare("
            SELECT id FROM usuarios
            WHERE usuario = :usuario COLLATE NOCASE AND id != :id
            LIMIT 1
        ");
        $stmtCheck->bindValue(':usuario', $usuario, SQLITE3_TEXT);
        $stmtCheck->bindValue(':id', $id, SQLITE3_INTEGER);
        $existente = $stmtCheck->execute()->fetchArray(SQLITE3_ASSOC);
        if ($existente) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Ese nombre de usuario ya esta en uso por otro usuario']);
            $db->close();
            exit;
        }

        // EDITAR
        $stmt = $db->prepare("
            UPDATE usuarios
            SET nombre = :nombre, apellido = :apellido, usuario = :usuario,
                dependencia_id = :dep, activo = :activo
            WHERE id = :id AND rol = 'dependencia'
        ");
        $stmt->bindValue(':nombre', mb_strtoupper($nombre, 'UTF-8'), SQLITE3_TEXT);
        $stmt->bindValue(':apellido', mb_strtoupper($apellido, 'UTF-8'), SQLITE3_TEXT);
        $stmt->bindValue(':usuario', strtolower($usuario), SQLITE3_TEXT);
        $stmt->bindValue(':dep', $dependencia_id, SQLITE3_INTEGER);
        $stmt->bindValue(':activo', $activo, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();

        if ($db->changes() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            $db->close();
            exit;
        }

        // Si viene nueva contrasena, actualizarla
        $editado = $db->querySingle("SELECT rol FROM usuarios WHERE id = $id", true);
        if ($editado && $editado['rol'] === 'dependencia') {
            if ($password !== '') {
                $stmtPass = $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
                $stmtPass->bindValue(':hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
                $stmtPass->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmtPass->execute();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Funcionario actualizado correctamente']);
    } else {
        // NUEVO
        if ($password === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña es obligatoria para un funcionario nuevo']);
            $db->close();
            exit;
        }
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            $db->close();
            exit;
        }

        // Generar automaticamente el nombre de usuario con el proximo id de creacion
        $proximoId = intval($db->querySingle("SELECT COALESCE(MAX(id), 0) + 1 FROM usuarios"));
        $usuarioGenerado = generarNombreUsuario($nombre, $apellido, $proximoId);

        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre, apellido, usuario, password_hash, rol, dependencia_id, activo)
            VALUES (:nombre, :apellido, :usuario, :hash, 'dependencia', :dep, :activo)
        ");
        $stmt->bindValue(':nombre', mb_strtoupper($nombre, 'UTF-8'), SQLITE3_TEXT);
        $stmt->bindValue(':apellido', mb_strtoupper($apellido, 'UTF-8'), SQLITE3_TEXT);
        $stmt->bindValue(':usuario', $usuarioGenerado, SQLITE3_TEXT);
        $stmt->bindValue(':hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':dep', $dependencia_id, SQLITE3_INTEGER);
        $stmt->bindValue(':activo', $activo, SQLITE3_INTEGER);
        $stmt->execute();

        $nuevoId = intval($db->lastInsertRowID());

        // Si el id real difiere del estimado (raro), re-escribir el usuario con el id definitivo
        if ($nuevoId !== $proximoId) {
            $usuarioGenerado = generarNombreUsuario($nombre, $apellido, $nuevoId);
            $stmtUpd = $db->prepare("UPDATE usuarios SET usuario = :usuario WHERE id = :id");
            $stmtUpd->bindValue(':usuario', $usuarioGenerado, SQLITE3_TEXT);
            $stmtUpd->bindValue(':id', $nuevoId, SQLITE3_INTEGER);
            $stmtUpd->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Funcionario creado correctamente. Usuario: ' . $usuarioGenerado,
            'usuario' => $usuarioGenerado
        ]);
    }

    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}