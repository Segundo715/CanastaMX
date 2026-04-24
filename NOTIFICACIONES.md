# Sistema de Notificaciones de Alertas - CanastaMX

## Resumen de Cambios

Se ha implementado un **sistema completo de notificaciones** para CanastaMX que permite a los usuarios recibir alertas cuando se crean nuevas alertas de precios o cuando éstas se disparan.

## Características Nuevas

### 1. **Notificaciones por Correo Electrónico**
- Cuando se crea una alerta para un usuario específico, recibe un correo de confirmación
- Cuando una alerta se dispara (el precio alcanza el límite), recibe notificación por correo
- Los correos tienen formato HTML profesional con branding de CanastaMX

### 2. **Panel de Notificaciones en Usuario**
- Nueva sección "🔔 Notificaciones" en el panel del usuario
- Muestra todas las notificaciones de alertas (creadas y disparadas)
- Indicador visual de notificaciones no leídas (borde rojo)
- Botón para marcar una notificación como leída
- Botón para marcar todas las notificaciones como leídas

### 3. **API Ampliada**
- Endpoint `/api/notificaciones.php` ahora soporta usuarios (además de admins)
- Usuarios pueden listar, marcar como leídas y eliminar sus notificaciones

## Archivos Modificados

### 1. **scripts/email_sender.php**
- Mejorado formato de correos HTML
- Función `enviarEmail()` con soporte para registro en BD
- Función `enviarAlertaPorEmail()` para notificaciones de alertas
- Automáticamente registra intentos en tabla `email_log`

### 2. **api/alertas.php**
- Importa `email_sender.php`
- En POST (crear alerta):
  - Obtiene información del producto
  - Crea notificación inicial en BD
  - Envía correo si es para usuario específico

### 3. **api/notificaciones.php**
- Ahora soporta acceso de usuarios regulares (no solo admins)
- GET `/api/notificaciones.php?action=listar` - lista notificaciones del usuario
- GET `/api/notificaciones.php?action=contar` - cuenta notificaciones sin leer
- PUT - marca como leída
- DELETE - elimina notificación

### 4. **scripts/check_alertas.php**
- Actualizada llamada a `enviarAlertaPorEmail()` con parámetro `usuario_id`
- Eliminada función duplicada (ahora en email_sender.php)

### 5. **user_index.php**
- Agregada sección de notificaciones con:
  - Lista de notificaciones con detalles
  - Botón para marcar como leída
  - Botón para marcar todas como leídas
  - Diseño responsivo

### 6. **assets/user.js**
- `loadNotificaciones()` - Carga notificaciones del usuario al iniciar
- `marcarComoLeida()` - Marca una notificación individual como leída
- `marcarTodasComoLeidas()` - Marca todas las notificaciones como leídas
- Integración automática con API

## Flujo de Funcionamiento

### Cuando un Usuario Crea una Alerta:
```
1. Usuario crea alerta en admin o panel
2. POST /api/alertas.php
3. Sistema:
   - Valida datos
   - Obtiene información del producto
   - Inserta alerta en BD (tabla alertas)
   - Crea notificación inicial (tabla notificaciones)
   - Si es para usuario específico:
     - Obtiene email del usuario
     - Envía correo HTML de confirmación
     - Registra intento en email_log
4. Usuario recibe:
   - Confirmación en pantalla
   - Correo de creación de alerta (si aplicable)
```

### Cuando se Dispara una Alerta:
```
1. Script check_alertas.php se ejecuta (cron job)
2. Detecta que precio alcanzó el límite
3. Sistema:
   - Inserta notificación en BD
   - Marca alerta como disparada/inactiva
   - Obtiene usuarios afectados
   - Envía correo a cada usuario
   - Registra intentos en email_log
4. Usuario ve:
   - Nueva notificación en panel
   - Correo de alerta
```

## Configuración Requerida

### Para Envío de Correos:
1. **PHP mail()** debe estar configurado o
2. **Servidor SMTP** configurado en php.ini

En XAMPP local, probablemente necesitarás:
- Configurar Sendmail o usar MailHog para desarrollo

### Base de Datos:
Las tablas necesarias son automáticamente creadas:
- `usuarios` - ya existe, tiene campo `email`
- `alertas` - actualizada con campo `usuario_id`
- `notificaciones` - guarda eventos de alertas
- `email_log` - registro de envíos

### En el Registro de Usuarios:
- El campo de email es **obligatorio** para recibir notificaciones
- Se valida que sea un email válido

## Cómo Usar

### Para Admins - Crear Alerta para Usuario Específico:
```
POST /api/alertas.php
{
  "producto_id": 1,
  "tipo": "SUBE",
  "precio_limite": 100.00,
  "region": "Nacional",
  "usuario_id": 5
}
```

### Para Usuarios - Ver Notificaciones:
1. Inicia sesión en tu panel
2. Desplázate a la sección "🔔 Notificaciones"
3. Verás todas tus notificaciones recientes
4. Marca como leída/elimina según necesites

### Para Verificar Correos (Desarrollo):
```
SELECT * FROM email_log WHERE estado IN ('enviado', 'fallido');
SELECT * FROM notificaciones ORDER BY created_at DESC LIMIT 10;
```

## Pruebas Recomendadas

1. **Crear alerta para usuario específico**
   - Verificar que se envíe correo
   - Verificar que aparezca notificación en panel

2. **Ejecutar check_alertas.php**
   - Verificar que se creen notificaciones
   - Verificar que se envíen correos

3. **Marcar notificaciones como leídas**
   - Verificar cambio visual en panel
   - Verificar actualización en BD

## Solución de Problemas

### No se reciben correos:
- Verificar que usuario tenga email en BD
- Verificar configuración SMTP/Sendmail en php.ini
- Revisar `email_log` para errores

### Notificaciones no aparecen:
- Limpiar caché del navegador (F5)
- Verificar que alertas tengan `usuario_id` o sean globales
- Revisar consola del navegador (F12) para errores JS

### Error al crear alerta:
- Verificar que usuario_id existe en tabla usuarios
- Verificar que producto_id existe
- Revisar logs de PHP

## Próximas Mejoras Posibles

- [ ] Notificaciones Push/Desktop
- [ ] Preferencias de notificación por usuario
- [ ] SMS como canal adicional
- [ ] Webhooks para integraciones
- [ ] Resumen diario/semanal de alertas
