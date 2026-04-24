<?php
// CanastaMX - Servicio de Alertas de Precios con Integración de APIs Reales
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/mercadolibre.php'; // Para obtener precios reales
require_once __DIR__ . '/inegi.php';        // Para obtener factor de inflación
require_once __DIR__ . '/../scripts/email_sender.php'; // Para enviar notificaciones

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // ── Listar y Procesar Alertas ───────────────────────────────────────────
    case 'GET':
        $action = $_GET['action'] ?? 'list';
        if ($action === 'check') {
            $indiceInegi = obtenerDatoRealINEGI() ?? 100;
            $factorReferencia = $indiceInegi / 100;

            if (!empty($_GET['producto_id'])) {
                $productoId = (int)$_GET['producto_id'];
                $stmt = $pdo->prepare("SELECT nombre, precio_base FROM productos WHERE id=?");
                $stmt->execute([$productoId]);
                $producto = $stmt->fetch();

                if (!$producto) {
                    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
                    break;
                }

                $precioReal = obtenerPrecioRealML($producto['nombre']);
                if (!$precioReal) {
                    $precioReal = $producto['precio_base'] * $factorReferencia;
                }

                $delta = $precioReal - $producto['precio_base'];
                $porcentaje = $producto['precio_base'] > 0 ? ($delta / $producto['precio_base']) * 100 : 0;
                echo json_encode([
                    'success' => true,
                    'tipo' => 'producto',
                    'producto' => $producto['nombre'],
                    'precio_base' => (float)$producto['precio_base'],
                    'precio_actual' => round($precioReal, 2),
                    'delta' => round($delta, 2),
                    'porcentaje' => round($porcentaje, 2),
                    'recomendacion' => $delta > 0 ? 'El precio sube. Considera alternativas más económicas o esperar una oferta.' : 'El precio está estable o bajo. Es buen momento para comprar.'
                ]);
                break;
            }

            $scope = $_GET['scope'] ?? 'complete';
            $categoriasBasica = ['Abarrotes','Lácteos','Verduras','Frutas','Carnes','Bebidas','Básicos','Panadería','Enlatados'];
            $sqlBasica = "SELECT SUM(precio_base) as total FROM productos WHERE categoria IN ('" . implode("','", $categoriasBasica) . "')";
            $totalBasica = (float)$pdo->query($sqlBasica)->fetchColumn();
            $totalCompleta = (float)$pdo->query('SELECT SUM(precio_base) FROM productos')->fetchColumn();
            $costoBasica = $totalBasica * $factorReferencia;
            $costoCompleta = $totalCompleta * $factorReferencia;
            $porcBasica = ($totalBasica > 0) ? (($costoBasica - $totalBasica) / $totalBasica) * 100 : 0;
            $porcCompleta = ($totalCompleta > 0) ? (($costoCompleta - $totalCompleta) / $totalCompleta) * 100 : 0;

            $recomendaciones = [];
            $recomendaciones[] = sprintf('INEGI indica un aumento de %.2f%% en los precios de alimentos.', $indiceInegi - 100);
            if ($scope === 'basica') {
                $recomendaciones[] = sprintf('La canasta básica sube %.2f%% según INEGI. Busca ofertas en abarrotes y productos locales.', $porcBasica);
            } else {
                $recomendaciones[] = sprintf('La canasta completa sube %.2f%% según INEGI. Revisa marcas económicas y compras por volumen.', $porcCompleta);
            }

            echo json_encode([
                'success' => true,
                'action' => 'check',
                'scope' => $scope,
                'indice_inegi' => round($indiceInegi, 2),
                'costo_basica' => round($costoBasica, 2),
                'costo_completa' => round($costoCompleta, 2),
                'recomendaciones' => $recomendaciones
            ]);
            break;
        }

        $onlyActive = isset($_GET['activas']) ? (int)$_GET['activas'] : null;
        $mine = isset($_GET['mine']) ? (int)$_GET['mine'] : null;
        $general = isset($_GET['general']) ? (int)$_GET['general'] : null;
        $sql = "SELECT a.id, a.producto_id, a.tipo, a.precio_limite, a.estado_id, a.activa,
                       a.created_at, p.nombre AS producto_nombre, p.emoji, p.precio_base,
                       u.usuario AS usuario_nombre
                FROM alertas a
                JOIN productos p ON a.producto_id = p.id
                LEFT JOIN usuarios u ON a.usuario_id = u.id";
        
        $conditions = [];
        $params = [];
        if ($onlyActive !== null) {
            $conditions[] = 'a.activa = ?';
            $params[] = $onlyActive ? 1 : 0;
        }
        if ($mine === 1 || $general === 1) {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                break;
            }
            $conditions[] = '(a.usuario_id = ? OR a.usuario_id IS NULL)';
            $params[] = $_SESSION['user_id'];
        }
        if (count($conditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY a.created_at DESC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        // Obtenemos el factor de inflación real del INEGI una sola vez para eficiencia
        $indiceInegi = obtenerDatoRealINEGI() ?? 100;
        $factorReferencia = $indiceInegi / 100;

        foreach ($rows as &$r) {
            // 1. Determinar Región
            if ($r['estado_id']) {
                $stEstado = $pdo->prepare("SELECT nombre FROM estados WHERE id=?");
                $stEstado->execute([$r['estado_id']]);
                $resEstado = $stEstado->fetch();
                $r['region'] = $resEstado['nombre'] ?? 'Nacional';
            } else {
                $r['region'] = 'Nacional';
            }
            unset($r['estado_id']);
            $r['usuario_nombre'] = $r['usuario_nombre'] ?? 'Usuario';

            // 2. Obtener PRECIO REAL (Prioridad Mercado Libre, si no, INEGI ajustado)
            $precioReal = obtenerPrecioRealML($r['producto_nombre']);
            
            if (!$precioReal) {
                // Si falla la API de ML, usamos el precio base de tu DB ajustado por la inflación real del INEGI
                $precioReal = $r['precio_base'] * $factorReferencia;
            }

            $r['precio_actual'] = round($precioReal, 2);
            
            // 3. Validar si se disparó la alerta (usando los tipos BAJA/SUBE o menor/mayor)
            $r['disparada'] = alertaDisparada($r['tipo'], $r['precio_actual'], (float)$r['precio_limite']);
            
            // Agregar mensaje de estado para el frontend
            if ($r['disparada']) {
                $r['status'] = 'DISPARADA';
                $r['mensaje'] = ($r['tipo'] === 'BAJA' || $r['tipo'] === 'menor') ? "¡Bajó de precio!" : "¡Subió de precio!";
            } else {
                $r['status'] = 'OK';
                $r['mensaje'] = "Precio estable";
            }
        }
        
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // ── Crear Alerta ────────────────────────────────────────────────────────
    case 'POST':
        // Verificar permisos: admin puede crear para cualquiera, usuario solo para sí mismo
        $isAdmin = isset($_SESSION['admin']);
        $isUser = isset($_SESSION['user_id']);
        
        if (!$isAdmin && !$isUser) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            break;
        }

        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['producto_id']) || empty($d['tipo']) || !isset($d['precio_limite'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Campos requeridos: producto_id, tipo, precio_limite']);
            break;
        }

        // Obtener información del producto
        $stmtProducto = $pdo->prepare("SELECT nombre, precio_base FROM productos WHERE id=?");
        $stmtProducto->execute([(int)$d['producto_id']]);
        $producto = $stmtProducto->fetch();
        
        if (!$producto) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            break;
        }

        // Determinar usuario destino
        $usuarioId = null;
        if ($isAdmin) {
            // Admin puede especificar usuario o dejar null para todos
            $usuarioId = !empty($d['usuario_id']) ? (int)$d['usuario_id'] : null;
            if ($usuarioId) {
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND rol = ?');
                $stmt->execute([$usuarioId, 'user']);
                $userExists = $stmt->fetch();
                if (!$userExists) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Usuario destino no válido']);
                    break;
                }
            }
        } elseif ($isUser) {
            // Usuario solo puede crear para sí mismo
            $usuarioId = (int)$_SESSION['user_id'];
        }
        $region = 'Nacional';
        if (!empty($d['region']) && $d['region'] !== 'Nacional') {
            $stmt = $pdo->prepare("SELECT id FROM estados WHERE nombre=?");
            $stmt->execute([$d['region']]);
            $estadorow = $stmt->fetch();
            if ($estadorow) {
                $estado_id = $estadorow['id'];
                $region = $d['region'];
            }
        }

        $tipo = strtoupper(trim($d['tipo']));
        $tipo = ($tipo === 'SUBE') ? 'SUBE' : 'BAJA';

        // Insertar la alerta
        $stmt = $pdo->prepare("INSERT INTO alertas (producto_id, tipo, precio_limite, estado_id, usuario_id) VALUES (?,?,?,?,?)");
        $stmt->execute([(int)$d['producto_id'], $tipo, (float)$d['precio_limite'], $estado_id, $usuarioId]);
        $alertaId = (int)$pdo->lastInsertId();

        // Crear notificación en el sistema
        $tipoAlerta = ($tipo === 'SUBE') ? 'ALZA DE PRECIO' : 'BAJA DE PRECIO';
        $mensaje = "Nueva alerta de {$tipoAlerta} creada para {$producto['nombre']} en {$region}. Límite: \$" . number_format($d['precio_limite'], 2);
        
        $stmtNotif = $pdo->prepare("INSERT INTO notificaciones (alerta_id, tipo, producto_nombre, region, precio_anterior, precio_actual, mensaje, leida) VALUES (?,?,?,?,?,?,?,?)");
        $stmtNotif->execute([
            $alertaId,
            $tipoAlerta,
            $producto['nombre'],
            $region,
            $producto['precio_base'],
            (float)$d['precio_limite'],
            "Alerta creada: " . $mensaje,
            0
        ]);

        // Si es para un usuario específico, enviar correo y crear notificación visible
        if ($usuarioId) {
            $stmtUser = $pdo->prepare('SELECT usuario, email FROM usuarios WHERE id = ?');
            $stmtUser->execute([$usuarioId]);
            $usuario = $stmtUser->fetch();
            
            if ($usuario && !empty($usuario['email'])) {
                $asunto = "CanastaMX: Alerta de {$tipoAlerta} creada";
                $cuerpo = "
                    <p>¡Hola <strong>{$usuario['usuario']}</strong>!</p>
                    <p>Se ha creado una nueva alerta de monitoreo de precios para ti:</p>
                    <h3>PRODUCTO: {$producto['nombre']}</h3>
                    <ul>
                        <li><strong>Tipo de alerta:</strong> {$tipoAlerta}</li>
                        <li><strong>Límite de precio:</strong> \$" . number_format($d['precio_limite'], 2) . "</li>
                        <li><strong>Región:</strong> {$region}</li>
                        <li><strong>Precio base:</strong> \$" . number_format($producto['precio_base'], 2) . "</li>
                    </ul>
                    <p>La alerta se disparará automáticamente cuando el precio " . 
                    ($tipo === 'SUBE' ? 'alcance o supere' : 'baje a') . 
                    " el límite que estableciste.</p>
                    <p>Revisa tu panel de usuario en CanastaMX para más detalles.</p>
                ";
                
                enviarEmail($usuario['email'], $asunto, $cuerpo, $pdo);
            }
        }

        echo json_encode(['success' => true, 'id' => $alertaId, 'mensaje' => 'Alerta creada con éxito para ' . ($usuarioId ? 'usuario específico' : 'todos los usuarios')]);
        break;

    // ── Eliminar Alerta ─────────────────────────────────────────────────────
    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            break;
        }
        $pdo->prepare("DELETE FROM alertas WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'mensaje' => 'Alerta eliminada']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

/**
 * Lógica de comparación de alertas
 */
function alertaDisparada(string $tipo, float $actual, float $limite): bool {
    // Soporta ambos formatos: los de tu código original (menor/mayor) y los nuevos (BAJA/SUBE)
    if ($tipo === 'menor' || $tipo === 'BAJA') {
        return $actual <= $limite;
    }
    if ($tipo === 'mayor' || $tipo === 'SUBE') {
        return $actual >= $limite;
    }
    return false;
}