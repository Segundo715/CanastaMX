<?php
/**
 * Mercado Libre Auth + Precio Real para CanastaMX
 */

function obtenerAccessTokenMercadoLibre(): ?string {
    static $token = null;
    if ($token !== null) {
        return $token;
    }

    $client_id = '6738750922968936';
    $client_secret = 'YSvnh6nOaOOaazNAZ3QLZeweaV2fswp7';
    $code = 'https://www.google.com/?code=TG-69e25a55933660000144b5c0-1572895185';
    $redirect_uri = 'https://www.google.com';
    $url = 'https://api.mercadolibre.com/oauth/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'authorization_code',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'redirect_uri' => $redirect_uri
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['access_token'])) {
        $token = $data['access_token'];
        return $token;
    }

    return null;
}

function obtenerPrecioRealMLAuth(string $busqueda): ?float {
    $token = obtenerAccessTokenMercadoLibre();
    $query = urlencode($busqueda);
    $url = "https://api.mercadolibre.com/sites/MLM/search?q=$query&category=MLM1403&limit=5";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['results']) || count($data['results']) === 0) {
        return null;
    }

    $suma = 0;
    $count = 0;
    foreach ($data['results'] as $item) {
        if (isset($item['price'])) {
            $suma += $item['price'];
            $count++;
        }
    }

    return $count > 0 ? $suma / $count : null;
}
