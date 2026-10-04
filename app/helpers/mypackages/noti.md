composer require alejo/laravel-notificaciones --no-interaction 2>&1

use Notificaciones;

// Prioridades disponibles: 'baja', 'media', 'alto'
Notificaciones::crear('Nuevo pedido recibido', 'alto');
Notificaciones::crear('Tu reporte está listo', 'media');
Notificaciones::crear('Sesión iniciada desde nuevo dispositivo', 'baja');
