#!/bin/sh
set -e

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ]; then
    echo "Waiting for database to be ready..."
    ATTEMPTS_LEFT_TO_REACH_DATABASE=30
    until php -r "try { new PDO('mysql:host=db;port=3306;dbname=mediatheque', 'app_user', 'app_password'); exit(0); } catch (\Exception \$e) { exit(1); }" 2>/dev/null; do
        if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
            echo "Error: Database is not reachable."
            exit 1
        fi
        ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
        echo "Still waiting for database ($ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left)..."
        sleep 1
    done
    echo "Database is ready!"

    # Cache warmup
    php bin/console cache:warmup --no-interaction || true
fi

exec docker-php-entrypoint "$@"
