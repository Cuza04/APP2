#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
    echo "Error: no se encontró .env en ${ROOT}" >&2
    exit 1
fi

BACKUP_DIR="${BACKUP_DIR:-${ROOT}/storage/backups}"
mkdir -p "$BACKUP_DIR"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

# shellcheck disable=SC1091
source <(grep -E '^(DB_CONNECTION|DB_DATABASE|DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD)=' .env | sed 's/^/export /')

DB_CONNECTION="${DB_CONNECTION:-sqlite}"

case "$DB_CONNECTION" in
    sqlite)
        DB_FILE="${DB_DATABASE:-${ROOT}/database/database.sqlite}"

        if [[ "$DB_FILE" != /* ]]; then
            DB_FILE="${ROOT}/${DB_FILE}"
        fi

        if [[ ! -f "$DB_FILE" ]]; then
            echo "Error: base SQLite no encontrada: ${DB_FILE}" >&2
            exit 1
        fi

        DEST="${BACKUP_DIR}/sqlite_${TIMESTAMP}.sqlite"
        cp "$DB_FILE" "$DEST"
        ;;

    mysql)
        DEST="${BACKUP_DIR}/mysql_${TIMESTAMP}.sql.gz"
        mysqldump \
            -h "${DB_HOST:-127.0.0.1}" \
            -P "${DB_PORT:-3306}" \
            -u "${DB_USERNAME:-root}" \
            ${DB_PASSWORD:+-p"$DB_PASSWORD"} \
            "${DB_DATABASE}" | gzip > "$DEST"
        ;;

    pgsql)
        DEST="${BACKUP_DIR}/pgsql_${TIMESTAMP}.sql.gz"
        PGPASSWORD="${DB_PASSWORD:-}" pg_dump \
            -h "${DB_HOST:-127.0.0.1}" \
            -p "${DB_PORT:-5432}" \
            -U "${DB_USERNAME:-postgres}" \
            "${DB_DATABASE}" | gzip > "$DEST"
        ;;

    *)
        echo "Error: DB_CONNECTION no soportada para backup: ${DB_CONNECTION}" >&2
        exit 1
        ;;
esac

echo "Backup creado: ${DEST}"

# Retención opcional: eliminar backups de más de 30 días
find "$BACKUP_DIR" -type f -mtime +30 -delete 2>/dev/null || true
