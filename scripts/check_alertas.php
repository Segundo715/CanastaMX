<?php
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../api/mercadolibre.php';
require_once __DIR__ . '/../api/inegi.php';
require_once __DIR__ . '/email_sender.php';

$pdo = getDB();

$stmt = $pdo->query("SELECT a.id, a.producto_id, a.tipo, a.precio_limite, a.estado_id, a.usuario_id,
                           p.nombre AS producto_nombre, p.precio_base,
                           u.usuario AS usuario_nombre,
                           e.nombre AS estado_nombre
                    FROM alertas a
                    JOIN productos p ON a.producto_id = p.id
                    LEFT JOIN usuarios u ON a.usuario_id = u.id
                    LEFT JOIN estados e ON a.estado_id = e.id
                    WHERE a.activa = 1");
$alertas = $stmt->fetchAll();

$indiceInegi = obtenerDatoRealINEGI() ?? 100;
$factorReferencia = $indiceInegi / 100;

if (!$alertas) {
    echo "No hay alertas activas.\n";
    exit;
}

$results = [];

foreach ($alertas as $alerta) {
    $precioReal = obtenerPrecioRealML($alerta['producto_nombre']);
    if (!$precioReal) {
        $precioReal = $alerta['precio_base'] * $factorReferencia;
    }
    $precioReal = round($precioReal, 2);

    $disparada = alertaDisparada($alerta['tipo'], $precioReal, (float)$alerta['precio_limite']);
    $mensaje = $disparada ? 'DISPARADA' : 'ESTABLE';
    $region = $alerta['estado_nombre'] ?? 'Nacional';
    $usuario = $alerta['usuario_nombre'] ?? 'Usuario general';

    $results[] = sprintf(
        "Alerta #%d | %s | Usuario: %s | Región: %s | Tipo: %s | Límite: %s | Actual: %s | %s",
        $alerta['id'], $alerta['producto_nombre'], $usuario, $region,
        $alerta['tipo'], number_format($alerta['precio_limite'], 2), number_format($precioReal, 2), $mensaje
    );

    if ($disparada) {
        $pdo->prepare('UPDATE alertas SET activa = 0 WHERE id = ?')->execute([$alerta['id']]);
        $results[] = "  -> Alerta marcada como disparada y desactivada.";
        
        $tipoAlerta = strtoupper($alerta['tipo']) === 'SUBE' ? 'ALZA DE PRECIO' : 'BAJA DE PRECIO';
        $notifMensaje = "El precio de {$alerta['producto_nombre']} en {$region} " . 
                        (strtoupper($alerta['tipo']) === 'SUBE' ? 'SUBIÓ' : 'BAJÓ') . 
                        " a \${$precioReal} (límite: \$" . number_format($alerta['precio_limite'], 2) . ")";
        
        $stmtNotif = $pdo->prepare("INSERT INTO notificaciones (alerta_id, tipo, producto_nombre, region, precio_anterior, precio_actual, mensaje) VALUES (?,?,?,?,?,?,?)");
        $stmtNotif->execute([
            $alerta['id'],
            $tipoAlerta,
            $alerta['producto_nombre'],
            $region,
            $alerta['precio_limite'],
            $precioReal,
            $notifMensaje
        ]);
        $results[] = "  -> Notificación guardada para admin.";
        
        // Enviar notificaciones por email a los usuarios
        enviarAlertaPorEmail($pdo, $alerta, $tipoAlerta, $region, $precioReal);
    }
}

echo implode("\n", $results) . "\n";

function alertaDisparada(string $tipo, float $actual, float $limite): bool {
    if (strtoupper($tipo) === 'BAJA') {
        return $actual <= $limite;
    }
    if (strtoupper($tipo) === 'SUBE') {
        return $actual >= $limite;
    }
    if ($tipo === 'menor') {
        return $actual <= $limite;
    }
    if ($tipo === 'mayor') {
        return $actual >= $limite;
    }
    return false;
}

function enviarAlertaPorEmail(PDO $pdo, array $alerta, string $tipoAlerta, string $region, float $precioReal) {
    global $results;
    
    // Obtener usuarios: si la alerta tiene usuario_id, solo ese; si no, todos
    if ($alerta['usuario_id']) {
        $stmt = $pdo->prepare('SELECT id, usuario, email FROM usuarios WHERE id = ? AND rol = ?');
        $stmt->execute([$alerta['usuario_id'], 'user']);
        $usuarios = $stmt->fetchAll();
    } else {
        // Alerta general para todos los usuarios
        $stmt = $pdo->query('SELECT id, usuario, email FROM usuarios WHERE rol = \'user\'');
        $usuarios = $stmt->fetchAll();
    }
    
    if (!$usuarios) {
        $results[] = '  -> No hay usuarios para notificar.';
        return;
    }
    
    foreach ($usuarios as $usuario) {
        if (!$usuario['email']) {
            continue;
        }
        
        $asunto = '[⚠️ ALERTA CanastaMX] ' . $tipoAlerta . ': ' . $alerta['producto_nombre'] . ' en ' . $region;
        $cuerpo = 'Hola ' . $usuario['usuario'] . ',' . "\n\n" .
                  'Se activó una alerta de monitoreo de precios:' . "\n\n" .
                  'Producto: ' . $alerta['producto_nombre'] . "\n" .
                  'Región: ' . $region . "\n" .
                  'Tipo: ' . $tipoAlerta . "\n" .
                  'Precio límite: $' . number_format($alerta['precio_limite'], 2) . "\n" .
                  'Precio actual: $' . number_format($precioReal, 2) . "\n\n" .
                  'Ingresa a CanastaMX para revisar los detalles.' . "\n\n" .
                  'Saludos,' . "\n" .
                  'Equipo CanastaMX';
        
        $exito = enviarEmail($usuario['email'], $asunto, $cuerpo);
        
        // Registrar intento en email_log
        $stmtEmailLog = $pdo->prepare('INSERT INTO email_log (alerta_id, destinatario, asunto, estado, mensaje_error) VALUES (?,?,?,?,?)');
        $stmtEmailLog->execute([
            $alerta['id'],
            $usuario['email'],
            $asunto,
            $exito ? 'enviado' : 'fallido',
            $exito ? null : 'Error al enviar (revisar configuración SMTP)'
        ]);
        
        $status = $exito ? 'enviado' : 'fallido';
        $results[] = '  -> Email ' . $status . ' a ' . $usuario['email'];
    }
}
