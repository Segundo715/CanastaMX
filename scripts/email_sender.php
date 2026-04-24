<?php
/**
 * Email Sender Utility for CanastaMX
 * Envía notificaciones por email cuando se activan alertas
 */

function enviarEmail(string $destinatario, string $asunto, string $cuerpo, ?PDO $pdo = null): bool {
    // Configuración de email
    $remitente = 'noreply@canastamx.local';
    
    $headers = "From: CanastaMX <{$remitente}>\r\n";
    $headers .= "Reply-To: {$remitente}\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "X-Mailer: CanastaMX/1.0\r\n";
    
    // Formato HTML mejorado
    $cuerpoHtml = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: #fff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            .alert-box { background: #fff; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 3px; }
            strong { color: #007bff; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🛒 CanastaMX</h1>
                <p>Notificación de Alerta de Precios</p>
            </div>
            <div class='content'>
                <div class='alert-box'>
                    {$cuerpo}
                </div>
            </div>
            <div class='footer'>
                <p>Este es un correo automático de CanastaMX. No responda a este mensaje.</p>
                <p>&copy; 2026 CanastaMX - Sistema de Monitoreo de Precios</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Intenta enviar con la función mail() de PHP
    $resultado = @mail($destinatario, $asunto, $cuerpoHtml, $headers);
    
    // Si se proporciona PDO, registra el intento en la base de datos
    if ($pdo) {
        $estado = $resultado ? 'enviado' : 'fallido';
        $stmt = $pdo->prepare("INSERT INTO email_log (destinatario, asunto, estado) VALUES (?, ?, ?)");
        $stmt->execute([$destinatario, $asunto, $estado]);
    }
    
    return $resultado;
}

function enviarAlertaPorEmail(PDO $pdo, array $alerta, string $tipoAlerta, string $region, float $precioReal, ?int $usuario_id = null): void {
    try {
        // Obtener emails de usuarios
        if ($usuario_id) {
            $stmt = $pdo->prepare('SELECT id, usuario, email FROM usuarios WHERE id = ? AND rol = ? AND email IS NOT NULL');
            $stmt->execute([$usuario_id, 'user']);
            $usuarios = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare('SELECT id, usuario, email FROM usuarios WHERE rol = ? AND email IS NOT NULL');
            $stmt->execute(['user']);
            $usuarios = $stmt->fetchAll();
        }
        
        if (empty($usuarios)) {
            return;
        }
        
        foreach ($usuarios as $usuario) {
            $asunto = "Alerta CanastaMX: {$alerta['producto_nombre']}";
            
            $cambio = strtoupper($alerta['tipo']) === 'SUBE' ? 'SUBIÓ' : 'BAJÓ';
            $emoji = strtoupper($alerta['tipo']) === 'SUBE' ? '📈' : '📉';
            
            $cuerpo = "
                <p>¡Hola <strong>{$usuario['usuario']}</strong>!</p>
                <p>Tu alerta de precios se ha activado:</p>
                <h3>{$emoji} {$alerta['producto_nombre']}</h3>
                <p><strong>El precio {$cambio}</strong> en {$region}</p>
                <ul>
                    <li><strong>Precio actual:</strong> \${$precioReal}</li>
                    <li><strong>Límite establecido:</strong> \${$alerta['precio_limite']}</li>
                    <li><strong>Tipo de alerta:</strong> {$tipoAlerta}</li>
                </ul>
                <p>Revisa tu panel de usuario en CanastaMX para más detalles.</p>
            ";
            
            enviarEmail($usuario['email'], $asunto, $cuerpo, $pdo);
        }
    } catch (Exception $e) {
        error_log("Error enviando alertas por email: " . $e->getMessage());
    }
}
