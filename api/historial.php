<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/inegi.php';
require_once __DIR__ . '/mercadolibre.php'; 

$pdo = getDB();

// Captura de parámetros
$pid = (int)($_GET['producto_id'] ?? 0);
$periodo = $_GET['periodo'] ?? '12m'; // Soporta: 7d, 1m, 12m, all

$stmt = $pdo->prepare("SELECT nombre, precio_base FROM productos WHERE id = ?");
$stmt->execute([$pid]);
$prod = $stmt->fetch();

if (!$prod) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    exit;
}

// 1. Obtener datos base reales
$indiceINEGI = obtenerDatoRealINEGI() ?? 100;
$precioML = obtenerPrecioRealML($prod['nombre']) ?? $prod['precio_base'];
$serieInegi = [];

// 2. Definir todas las fuentes (INEGI + 6 tiendas)
$fuentes = [
    'INEGI',
    'Walmart',
    'Soriana',
    'Chedraui',
    'Bodega Aurrera',
    'La Comer',
    'MercadoLibre'
];

// 3. Configurar la escala de tiempo según el periodo seleccionado
switch ($periodo) {
    case '7d':
        $pasos = 7; 
        $unidad = 'days'; 
        $formato = 'd M';
        break;
    case '1m':
        $pasos = 30; // 30 puntos para ver variabilidad diaria en un mes
        $unidad = 'days'; 
        $formato = 'd M';
        break;
    case 'all': // Histórico (ej. 24 meses / 2 años)
        $pasos = 24; 
        $unidad = 'months'; 
        $formato = 'M Y';
        break;
    default: // 12m (1 año por meses)
        $pasos = 12; 
        $unidad = 'months'; 
        $formato = 'M Y';
        break;
}

if (in_array($periodo, ['12m', 'all'], true)) {
    $serieInegi = obtenerDatoHistoricoINEGI("628194", false) ?? [];
    if (count($serieInegi) >= $pasos) {
        $serieInegi = array_slice($serieInegi, -$pasos);
    } else {
        $serieInegi = [];
    }
}

$datosFinales = [];

foreach ($fuentes as $f) {
    $serie = [];
    
    for ($i = $pasos - 1; $i >= 0; $i--) {
        $fecha = date($formato, strtotime("-$i $unidad"));
        
        // Simulación de variabilidad lógica para crear la gráfica
        $volatilidad = (rand(-250, 250) / 10000); // Variación de +/- 2.5%
        $tendencia = 1 - ($i * 0.003); // Ligera tendencia al alza hacia el presente
        
        if ($f === 'INEGI') {
            if (count($serieInegi) === $pasos) {
                $val = $serieInegi[$pasos - 1 - $i]['valor'];
                $fecha = $serieInegi[$pasos - 1 - $i]['fecha'];
            } else {
                // El INEGI suele ser más estable (el precio sugerido)
                $val = ($prod['precio_base'] * ($indiceINEGI / 100)) * $tendencia;
            }
        } elseif ($f === 'MercadoLibre') {
            // Mercado Libre usa su precio real de API como base
            $val = $precioML * $tendencia * (1 + $volatilidad);
        } else {
            // Ajustes por "personalidad" de la tienda
            $ajusteTienda = 1.0;
            if ($f === 'Chedraui')      $ajusteTienda = 0.96;
            if ($f === 'La Comer')      $ajusteTienda = 1.04;
            if ($f === 'Bodega Aurrera') $ajusteTienda = 0.94;
            
            $val = $prod['precio_base'] * $tendencia * $ajusteTienda * (1 + $volatilidad);
        }

        $serie[] = [
            'fecha' => $fecha,
            'precio' => round($val, 2)
        ];
    }
    $datosFinales[$f] = $serie;
}

// 4. Salida compatible con la función renderChart(data)
echo json_encode([
    'success' => true,
    'data' => $datosFinales
]);