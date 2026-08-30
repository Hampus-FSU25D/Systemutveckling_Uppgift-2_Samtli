#!/bin/sh
set -eu

if [ "${MIGRATE_ON_START:-true}" = "true" ]; then
    attempts=0
    max_attempts="${MIGRATION_RETRIES:-30}"
    retry_delay="${MIGRATION_RETRY_DELAY:-2}"

    until php bin/migrate.php; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "Database migrations failed after ${attempts} attempts." >&2
            exit 1
        fi

        echo "Database migration attempt ${attempts} failed. Retrying in ${retry_delay}s..." >&2
        sleep "$retry_delay"
    done
fi

exec docker-php-entrypoint "$@"
