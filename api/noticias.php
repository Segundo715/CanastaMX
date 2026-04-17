<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function obtenerNoticiasEconomia() {
    $apiKey = "37cc725d7daf49a7aa2b1cefe11d8a5b";
    $query = urlencode('precios "canasta básica" México OR inflación');
    $url = "https://newsapi.org/v2/everything?q=$query&language=es&sortBy=publishedAt&pageSize=5&apiKey=$apiKey";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CanastaMX-App/1.0');
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

echo obtenerNoticiasEconomia();