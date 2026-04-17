<?php
// Limpiar cualquier salida previa para evitar errores JSON
ob_clean(); 
header('Content-Type: application/json');

require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/inegi.php';

try {
    $db = getDB();
    $indiceActual = obtenerDatoRealINEGI() ?? 100;
    $factorInflacion = $indiceActual / 100;

    $estadoFactores = [
        'Aguascalientes' => 0.97, 'Baja California' => 1.08, 'Baja California Sur' => 1.12,
        'Campeche' => 0.96, 'Chiapas' => 0.88, 'Chihuahua' => 1.04,
        'Ciudad de México' => 1.02, 'Coahuila' => 1.03, 'Colima' => 0.99,
        'Durango' => 0.98, 'Estado de México' => 0.99, 'Guanajuato' => 0.96,
        'Guerrero' => 0.90, 'Hidalgo' => 0.94, 'Jalisco' => 1.00,
        'Michoacán' => 0.93, 'Morelos' => 0.97, 'Nayarit' => 1.00,
        'Nuevo León' => 1.06, 'Oaxaca' => 0.91, 'Puebla' => 0.97,
        'Querétaro' => 1.00, 'Quintana Roo' => 1.14, 'San Luis Potosí' => 0.96,
        'Sinaloa' => 1.01, 'Sonora' => 1.05, 'Tabasco' => 0.95,
        'Tamaulipas' => 1.03, 'Tlaxcala' => 0.93, 'Veracruz' => 0.94,
        'Yucatán' => 1.00, 'Zacatecas' => 0.95
    ];

    $productos = $db->query('SELECT precio_base FROM productos')->fetchAll(PDO::FETCH_COLUMN);
    $estados = $db->query('SELECT nombre FROM estados ORDER BY nombre ASC')->fetchAll(PDO::FETCH_COLUMN);
    $resultado = [];

    foreach ($estados as $estado) {
        $factorEstado = $estadoFactores[$estado] ?? 1.00;
        $costoActual = 0.0;
        $costoPrevio = 0.0;

        foreach ($productos as $precioBase) {
            $costoActual += $precioBase * $factorEstado * $factorInflacion;
            $costoPrevio += $precioBase * $factorEstado * max(0.95, $factorInflacion - 0.015);
        }

        $costoActual = round($costoActual, 2);
        $costoPrevio = round($costoPrevio, 2);
        $tendencia = $costoPrevio > 0 ? round((($costoActual - $costoPrevio) / $costoPrevio) * 100, 2) : 0.0;

        $resultado[] = [
            'nombre' => $estado,
            'costo_total' => $costoActual,
            'tendencia' => $tendencia
        ];
    }

    $promedioNacional = 0;
    if (count($resultado) > 0) {
        $promedioNacional = array_sum(array_column($resultado, 'costo_total')) / count($resultado);
    }

    echo json_encode([
        'success' => true,
        'promedio_nacional' => round($promedioNacional, 2),
        'estados' => $resultado
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}