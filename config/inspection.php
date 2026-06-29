<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseñas
    |--------------------------------------------------------------------------
    */

    'min_password_length' => (int) env('PASSWORD_MIN_LENGTH', 8),

    /*
    |--------------------------------------------------------------------------
    | Horario operativo del terminal
    |--------------------------------------------------------------------------
    |
    | Solo las franjas dentro de este rango cuentan para cumplimiento y
    | recordatorios. Horas en formato 24 h; end es exclusivo (22 = hasta 21:59).
    | Valores por defecto 0–24 = operación continua (comportamiento anterior).
    |
    */

    'operating_start_hour' => (int) env('INSPECTION_OPERATING_START_HOUR', 0),
    'operating_end_hour' => (int) env('INSPECTION_OPERATING_END_HOUR', 24),

    /*
    |--------------------------------------------------------------------------
    | Recordatorios de random (minutos transcurridos en la hora)
    |--------------------------------------------------------------------------
    */

    'reminder_warning_minute' => (int) env('INSPECTION_REMINDER_WARNING_MINUTE', 15),
    'reminder_urgent_minute' => (int) env('INSPECTION_REMINDER_URGENT_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Retención de notificaciones del panel
    |--------------------------------------------------------------------------
    */

    'notification_retention_days' => (int) env('INSPECTION_NOTIFICATION_RETENTION_DAYS', 30),

];
