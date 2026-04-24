<?php
/**
 * CanastaMX - API de Notificaciones
 * Gestiona notificaciones para admin y usuarios
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db/conexion.php';
$pdo = getDB();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $action = $_GET['action'] ?? 'listar';
        
        // ── Para Admins ──
        if (isset($_SESSION['admin'])) {
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
        }
        
        // ── Para Usuarios ──
        if (isset($_SESSION['user_id'])) {
            if ($action === 'listar') {
                $userId = (int)$_SESSION['user_id'];
                $limit = (int)($_GET['limit'] ?? 50);
                
                // Obtener notificaciones de alertas del usuario o globales
                $stmt = $pdo->prepare("
                    SELECT n.id, n.alerta_id, n.tipo, n.producto_nombre, n.region, 
                           n.precio_anterior, n.precio_actual, n.mensaje, n.leida, n.created_at
                    FROM notificaciones n
                    JOIN alertas a ON n.alerta_id = a.id
                    WHERE a.usuario_id = ? OR a.usuario_id IS NULL
                    ORDER BY n.created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$userId, $limit]);
                $notificaciones = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'data' => $notificaciones]);
            } elseif ($action === 'sin_leer') {
                $userId = (int)$_SESSION['user_id'];
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total FROM notificaciones n
                    JOIN alertas a ON n.alerta_id = a.id
                    WHERE (a.usuario_id = ? OR a.usuario_id IS NULL) AND n.leida = 0
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                echo json_encode(['success' => true, 'sin_leer' => (int)$result['total']]);
            }
            break;
        }
        
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        break;

    case 'PUT':
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['id'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            break;
        }

        // ── Para Admins ──
        if (isset($_SESSION['admin'])) {
            $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ?')->execute([(int)$d['id']]);
            echo json_encode(['success' => true, 'mensaje' => 'Notificación marcada como leída']);
            break;
        }

        // ── Para Usuarios ──
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
            $notifId = (int)$d['id'];
            
            // Verificar que la notificación pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT n.id FROM notificaciones n
                JOIN alertas a ON n.alerta_id = a.id
                WHERE n.id = ? AND (a.usuario_id = ? OR a.usuario_id IS NULL)
            ");
            $stmt->execute([$notifId, $userId]);
            $notif = $stmt->fetch();
            
            if (!$notif) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notificación no encontrada']);
                break;
            }
            
            $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ?')->execute([$notifId]);
            echo json_encode(['success' => true, 'mensaje' => 'Notificación marcada como leída']);
            break;
        }

        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            break;
        }

        // ── Para Admins ──
        if (isset($_SESSION['admin'])) {
            $pdo->prepare('DELETE FROM notificaciones WHERE id = ?')->execute([$id]);
            echo json_encode(['success' => true, 'mensaje' => 'Notificación eliminada']);
            break;
        }

        // ── Para Usuarios ──
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
            
            // Verificar que la notificación pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT n.id FROM notificaciones n
                JOIN alertas a ON n.alerta_id = a.id
                WHERE n.id = ? AND (a.usuario_id = ? OR a.usuario_id IS NULL)
            ");
            $stmt->execute([$id, $userId]);
            $notif = $stmt->fetch();
            
            if (!$notif) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Notificación no encontrada']);
                break;
            }
            
            $pdo->prepare('DELETE FROM notificaciones WHERE id = ?')->execute([$id]);
            echo json_encode(['success' => true, 'mensaje' => 'Notificación eliminada']);
            break;
        }

        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>

