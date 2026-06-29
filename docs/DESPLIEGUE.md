# Despliegue con Docker

Guía para instalar la app en el **PC o servidor** del terminal usando Docker Compose.

## Requisitos

- Docker Engine 24+ y Docker Compose v2
- Puerto libre (por defecto **8080**)

## Configuración

1. Copia el entorno de producción:

```bash
cp .env.example .env
```

2. Ajusta `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080
APP_TIMEZONE=America/Costa_Rica
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=app2
DB_USERNAME=app
DB_PASSWORD=elige_una_contraseña_segura

# Con HTTPS delante del contenedor:
# SESSION_SECURE_COOKIE=true
```

3. Construye e inicia:

```bash
docker compose up -d --build
```

4. Primera vez — migraciones y datos iniciales:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

5. Abre en el navegador del PC de monitoreo:

```
http://localhost:8080/admin
```

## Qué incluye el contenedor

| Proceso | Función |
|---------|---------|
| **nginx** | Servidor web |
| **php-fpm** | Laravel + Filament |
| **scheduler** | `schedule:run` cada minuto (recordatorios, cierre de horas, informes, backups) |

La base de datos MySQL corre en un contenedor aparte (`db`). Los datos persisten en el volumen `db_data`.

## Backups

- Automático a las **02:00** vía scheduler → `storage/backups/`
- Manual: `docker compose exec app bash scripts/backup-database.sh`

## Actualizar versión

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

## Sin Docker (servidor Linux tradicional)

Sigue [`OPERACION.md`](OPERACION.md): nginx + PHP 8.3 + MySQL y cron con `schedule:run`.
