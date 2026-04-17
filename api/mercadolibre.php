<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/**
 * Obtiene el precio promedio de un producto en Mercado Libre México (Supermercado)
 */
function obtenerPrecioRealML($busqueda) {
    // Limpiamos el término de búsqueda
    $query = urlencode($busqueda);
    // Buscamos en el site de México (MLM) y en la categoría de Despensa (MLM1403)
    $url = "https://api.mercadolibre.com/sites/MLM/search?q=$query&category=MLM1403&limit=5";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;

    $data = json_decode($response, true);
    if (!isset($data['results']) || count($data['results']) === 0) return null;

    $suma = 0;
    $count = 0;
    foreach ($data['results'] as $item) {
        $suma += $item['price'];
        $count++;
    }
    return $suma / $count; // Retornamos el promedio de los primeros 5 resultados
}