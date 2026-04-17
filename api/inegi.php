<?php
// CanastaMX - Servicio de Interfaz con INEGI API v2.0
header('Content-Type: application/json; charset=utf-8');

/**
 * Obtiene el último valor de un indicador (Ej. Inflación de alimentos)
 * Se usa para calcular precios "reales" actuales.
 */
function obtenerDatoRealINEGI() {
    $archivoCache = __DIR__ . '/cache_inegi_actual.txt';
    $tiempoCache = 86400; // 24 horas

    if (file_exists($archivoCache) && (time() - filemtime($archivoCache) < $tiempoCache)) {
        return (float)file_get_contents($archivoCache);
    }

    // Indicador 628194: INPC Alimentos, bebidas y tabaco
    $valor = obtenerDatoHistoricoINEGI("628194", true); 
    
    if ($valor) {
        file_put_contents($archivoCache, $valor);
        return $valor;
    }

    return 100.0; // Valor base por defecto
}

/**
 * Obtiene datos de la API del INEGI (BISE 2.0)
 * @param string $serieId ID del indicador
 * @param bool $soloUltimo Si es true devuelve solo el valor mas reciente, si es false el array completo
 */
function obtenerDatoHistoricoINEGI($serieId, $soloUltimo = false) {
    $token = "f2b126a3-4328-cf16-842f-17a98c137371";
    
    // URL para API de Indicadores v2.0 (BISE)
    // 0700 significa nivel Nacional
    $url = "https://www.inegi.org.mx/app/api/indicadores/desarrolladores/jsonxml/INDICATOR/{$serieId}/es/0700/false/BISE/2.0/{$token}?type=json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CanastaMX-Monitor/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($response, true);

    // Estructura de respuesta de INEGI v2.0
    if (isset($data['Series'][0]['OBSERVATIONS'])) {
        $observaciones = $data['Series'][0]['OBSERVATIONS'];
        
        if ($soloUltimo) {
            $ultimo = end($observaciones);
            return (float)$ultimo['VALUE'];
        }

        // Formatear para historial.php (Fecha y Valor)
        return array_map(function($obs) {
            return [
                'fecha' => $obs['TIME_PERIOD'], // Ej: 2024/01
                'valor' => (float)$obs['VALUE']
            ];
        }, $observaciones);
    }

    return null;
}

// Si se llama directamente por GET para pruebas: api/inegi.php?test=1
if (isset($_GET['test'])) {
    echo json_encode([
        'ultimo_real' => obtenerDatoRealINEGI(),
        'ejemplo_serie' => obtenerDatoHistoricoINEGI("628194", true)
    ]);
}