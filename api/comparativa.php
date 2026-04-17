<?php
// api/comparativa.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/inegi.php';        
require_once __DIR__ . '/mercadolibre.php'; 
require_once __DIR__ . '/mercadolibre_oauth.php';

$pdo = getDB();
$pid = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : null;

$indiceActual = obtenerDatoRealINEGI() ?? 100; 
$factorInflacion = $indiceActual / 100; 

// Diccionario con Emojis por Estado
$estadosMap = [
    'Aguascalientes' => ['m' => 0.97, 'e' => '🏺'], 'Baja California' => ['m' => 1.08, 'e' => '🍷'],
    'Baja California Sur' => ['m' => 1.12, 'e' => '🌵'], 'Campeche' => ['m' => 0.96, 'e' => '🏰'],
    'Chiapas' => ['m' => 0.88, 'e' => '☕'], 'Chihuahua' => ['m' => 1.04, 'e' => '🐕'],
    'Ciudad de México' => ['m' => 1.02, 'e' => '🗼'], 'Coahuila' => ['m' => 1.03, 'e' => '🦖'],
    'Colima' => ['m' => 0.99, 'e' => '🌋'], 'Durango' => ['m' => 0.98, 'e' => '🦂'],
    'Estado de México' => ['m' => 0.99, 'e' => '🦅'], 'Guanajuato' => ['m' => 0.96, 'e' => '🎭'],
    'Guerrero' => ['m' => 0.90, 'e' => '🌴'], 'Hidalgo' => ['m' => 0.94, 'e' => '⛰️'],
    'Jalisco' => ['m' => 0.99, 'e' => '🐎'], 'Michoacán' => ['m' => 0.93, 'e' => '🦋'],
    'Morelos' => ['m' => 0.97, 'e' => '⛲'], 'Nayarit' => ['m' => 1.00, 'e' => '🛶'],
    'Nuevo León' => ['m' => 1.06, 'e' => '⛰️'], 'Oaxaca' => ['m' => 0.91, 'e' => '🗿'],
    'Puebla' => ['m' => 0.97, 'e' => '⛪'], 'Querétaro' => ['m' => 1.00, 'e' => '🌉'],
    'Quintana Roo' => ['m' => 1.14, 'e' => '🏖️'], 'San Luis Potosí' => ['m' => 0.96, 'e' => '🏹'],
    'Sinaloa' => ['m' => 1.01, 'e' => '🍅'], 'Sonora' => ['m' => 1.05, 'e' => '🏜️'],
    'Tabasco' => ['m' => 0.95, 'e' => '🐊'], 'Tamaulipas' => ['m' => 1.03, 'e' => '🦀'],
    'Tlaxcala' => ['m' => 0.93, 'e' => '🌽'], 'Veracruz' => ['m' => 0.94, 'e' => '🚢'],
    'Yucatán' => ['m' => 1.00, 'e' => '🗿'], 'Zacatecas' => ['m' => 0.95, 'e' => '⛏️']
];

$tiendasTarget = ['Walmart', 'Bodega Aurrera', 'Soriana', 'Chedraui', 'La Comer', 'MercadoLibre'];
$tiendasColores = [
    'Walmart' => '#f59e0b',
    'Bodega Aurrera' => '#a855f7',
    'Soriana' => '#10b981',
    'Chedraui' => '#ef4444',
    'La Comer' => '#14b8a6',
    'MercadoLibre' => '#f97316'
];

if ($pid) {
    $stmt = $pdo->prepare("SELECT nombre, precio_base FROM productos WHERE id=?");
    $stmt->execute([$pid]);
    $prod = $stmt->fetch();

    if (!$prod) {
        echo json_encode(['success' => false, 'error' => 'No encontrado']); exit;
    }

    $precioRealML = obtenerPrecioRealMLAuth($prod['nombre']) ?? obtenerPrecioRealML($prod['nombre']);
    $tablaCompleta = [];
    $sumaNacional = 0;

    foreach ($estadosMap as $nombreEstado => $info) {
        $multEstado = $info['m'];
        $preciosTiendas = [];
        $preciosSoloNumeros = [];

        foreach ($tiendasTarget as $tienda) {
            if ($tienda === 'MercadoLibre' && $precioRealML) {
                $precio = round($precioRealML * ($multEstado / 1.02), 2); 
            } else {
                $noise = (rand(-200, 200) / 10000); 
                $precio = round($prod['precio_base'] * $factorInflacion * $multEstado * (1 + $noise), 2);
            }
            $preciosTiendas[$tienda] = $precio;
            $preciosSoloNumeros[] = $precio;
        }

        $promedioEstado = array_sum($preciosSoloNumeros) / count($tiendasTarget);
        $sumaNacional += $promedioEstado;

        $tablaCompleta[] = [
            'estado' => $nombreEstado,
            'emoji' => $info['e'],
            'precios' => $preciosTiendas,
            'min' => min($preciosSoloNumeros),
            'max' => max($preciosSoloNumeros),
            'promedio' => round($promedioEstado, 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'promedio_nacional' => round($sumaNacional / 32, 2),
        'tiendas_keys' => $tiendasTarget,
        'tiendas_colors' => $tiendasColores,
        'data' => $tablaCompleta
    ]);
}