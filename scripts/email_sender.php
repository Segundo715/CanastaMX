<?php
/**
 * Email Sender Utility for CanastaMX
 * Envía notificaciones por email cuando se activan alertas
 */

function enviarEmail(string $destinatario, string $asunto, string $cuerpo): bool {
    // Configuración de email
    $remitente = 'noreply@canastamx.local';
    $headers = "From: CanastaMX <{$remitente}>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: CanastaMX/1.0\r\n";
    
    // Intenta enviar con la función mail() de PHP (requiere Sendmail configurado)
    // En localhost, esto probablemente fallará, pero está registrado en email_log
    $resultado = @mail($destinatario, $asunto, $cuerpo, $headers);
    
    return $resultado;
}
