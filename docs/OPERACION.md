# Guía operativa — Control de inspecciones en terminal

Aplicación web para el **PC de escritorio** del puesto de monitoreo: sorteo horario de inspecciones en carriles de entrada. Diseñada para uso con teclado y ratón en un equipo fijo (Chrome o Edge en Windows/Linux). No está pensada para móvil ni tablet.

## Acceso

- URL: `{APP_URL}/admin`
- Navegador recomendado: **Chrome** o **Edge**, ventana maximizada en el monitor del puesto
- Usuario por defecto (solo desarrollo): `monitoreo@example.com` / `password`
- **Cambiar la contraseña** en Perfil antes de producción.

## Flujo cada hora

1. Al inicio de la franja (ej. 14:00), pulsar **Generar random**.
2. Avisar por radio al oficial del carril que aparece en pantalla.
3. Tras la inspección física, registrar **placa**, **resultado** y comentarios si aplica.
4. Si el carril no sirve, usar **Regenerar carril** (motivo obligatorio, mín. 10 caracteres).

## Atajos de teclado (PC)

| Atajo | Acción |
|-------|--------|
| `Ctrl+G` | Generar random |
| `Ctrl+Shift+R` | Regenerar carril |
| `Ctrl+S` | Guardar inspección |

Los atajos aparecen también al pie de la pantalla **Inspecciones**.

## Recordatorios

- **15 min**: recordatorio visual
- **30 min**: alerta urgente + sonido en pantalla
- Notificaciones en campana (BD) vía scheduler

- **Cumplimiento** (`/admin/daily-compliance`): detalle hora a hora, incumplimientos y regeneraciones; export CSV.
- Cambiar contraseña en el menú de usuario → **Perfil** (esquina superior).

## Tareas automáticas (obligatorio en producción)

El servidor debe ejecutar el scheduler de Laravel **cada minuto**:

```bash
* * * * * cd /ruta/a/APP2 && php artisan schedule:run >> /dev/null 2>&1
```

Comandos programados:

| Comando | Cuándo |
|---------|--------|
| `inspections:send-reminders` | Minutos 0, 15, 30, 45 de cada hora |
| `inspections:close-missed-hours` | Minuto 1 de cada hora |
| `inspections:export-daily-report` | 23:55 diario → `storage/app/reports/` |
| `scripts/backup-database.sh` | 02:00 diario → `storage/backups/` |

Backup manual de base de datos:

```bash
bash scripts/backup-database.sh
```

Los backups se guardan en `storage/backups/` (retención 30 días).

También conviene un worker de colas si se usan jobs:

```bash
php artisan queue:work
```

## Despliegue en producción

Opción recomendada con Docker: ver [`docs/DESPLIEGUE.md`](DESPLIEGUE.md).

Instalación manual:

1. Copiar `.env.example` → `.env` y configurar:
   - `APP_DEBUG=false`
   - `APP_URL=https://...`
   - `APP_TIMEZONE=` zona del terminal
   - `DB_CONNECTION=mysql` (o `pgsql`)
   - `SESSION_LIFETIME=480` (turnos de 8 h en el mismo PC)
2. `composer install --no-dev`
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed --force` (solo primera vez)
6. `npm ci && npm run build`
7. Configurar HTTPS en nginx/Apache
8. **Backups diarios**: `bash scripts/backup-database.sh` (programar en cron)

## Mantenimiento

- **Carriles** (`/admin/inspection-lanes`): abrir, cerrar o poner en mantenimiento sin tocar la BD.
- **Historial de inspecciones**: consulta y export CSV para auditorías.
- **Historial de movimientos**: trazabilidad de random, regeneraciones e incumplimientos.

## Incidencias frecuentes

| Problema | Acción |
|----------|--------|
| No suena la alerta urgente | El navegador debe permitir audio; hacer clic en la página al inicio del turno |
| Recordatorios no llegan | Verificar que `schedule:run` está en cron |
| Hora incorrecta | Revisar `APP_TIMEZONE` en `.env` y reiniciar PHP |
| No hay carriles para sorteo | Revisar que hay carriles de **entrada** en estado **Abierto** |
