<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../db/conexion.php';
$pdo = getDB();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        if ($action === 'listar') {
            $limit = (int)($_GET['limit'] ?? 10);
            $stmt = $pdo->prepare("SELECT id, alerta_id, tipo, producto_nombre, region, precio_anterior, precio_actual, mensaje, leida, created_at FROM notificaciones ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $notificaciones = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $notificaciones]);
        } elseif ($action === 'sin_leer') {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM notificaciones WHERE leida = 0");
            $result = $stmt->fetch();
            echo json_encode(['success' => true, 'sin_leer' => (int)$result['total']]);
        }
        break;

    case 'PUT':
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['id'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            break;
        }
        $stmt = $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ?');
        $stmt->execute([(int)$d['id']]);
        echo json_encode(['success' => true, 'mensaje' => 'Notificación marcada como leída']);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            break;
        }
        $pdo->prepare('DELETE FROM notificaciones WHERE id = ?')->execute([$id]);
        echo json_encode(['success' => true, 'mensaje' => 'Notificación eliminada']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
