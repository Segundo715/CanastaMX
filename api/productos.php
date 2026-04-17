<?php
// CanastaMX - Servicio de Productos
// GET ?action=listar|detalle|categorias  &id=  &categoria=  &q=

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/conexion.php';

$pdo    = getDB();
$action = $_GET['action'] ?? 'listar';

switch ($action) {
    case 'listar':
        $cat  = $_GET['categoria'] ?? null;
        $q    = $_GET['q']        ?? null;
        $sql  = "SELECT id,nombre,unidad,categoria,emoji,precio_base FROM productos WHERE 1=1";
        $args = [];
        if ($cat) { $sql .= " AND categoria=?";    $args[] = $cat; }
        if ($q)   { $sql .= " AND nombre LIKE ?";  $args[] = "%$q%"; }
        $sql .= " ORDER BY categoria, nombre";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $day  = (int)date('z');
        foreach ($rows as &$r) {
            $r['precio_actual']     = precioHoy((int)$r['id'], (float)$r['precio_base'], $day);
            $r['variacion_semanal'] = variacionSemanal((int)$r['id'], (float)$r['precio_base'], $day);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    case 'categorias':
        $cats = $pdo->query("SELECT DISTINCT categoria FROM productos ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $cats]);
        break;

    case 'detalle':
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) { echo json_encode(['success' => false, 'error' => 'No encontrado']); break; }
        $day = (int)date('z');
        $r['precio_actual']     = precioHoy($id, (float)$r['precio_base'], $day);
        $r['variacion_semanal'] = variacionSemanal($id, (float)$r['precio_base'], $day);
        echo json_encode(['success' => true, 'data' => $r]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}

function precioHoy(int $id, float $base, int $day): float {
    $noise = sin($id * 7.391 + $day * 0.137) * 0.08;
    return round($base * (1 + $noise), 2);
}

function variacionSemanal(int $id, float $base, int $day): float {
    $hoy    = sin($id * 7.391 + $day       * 0.137) * 0.08;
    $semana = sin($id * 7.391 + ($day - 7) * 0.137) * 0.08;
    return round(($hoy - $semana) * 100, 2);
}