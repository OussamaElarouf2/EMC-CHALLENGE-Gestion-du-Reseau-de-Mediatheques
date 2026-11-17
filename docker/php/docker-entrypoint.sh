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
    echo "Database is reachable!"

    # 1. Apply database migrations automatically (Level 5)
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing

    # 2. Check if fixtures need to be loaded automatically
    BRANCH_COUNT=$(php -r "try { \$pdo = new PDO('mysql:host=db;port=3306;dbname=mediatheque', 'app_user', 'app_password'); \$stmt = \$pdo->query('SELECT COUNT(*) FROM branch'); echo \$stmt ? \$stmt->fetchColumn() : 0; } catch (\Exception \$e) { echo 0; }" 2>/dev/null || echo 0)

    if [ "$BRANCH_COUNT" = "0" ] || [ -z "$BRANCH_COUNT" ]; then
        echo "Database is empty. Automatically loading realistic demo fixtures (branches, plans, works, copies, members, loans, reservations)..."
        php bin/console doctrine:fixtures:load --no-interaction
        echo "Fixtures loaded successfully!"
    else
        echo "Database already initialized ($BRANCH_COUNT branches found). Skipping fixtures loading."
    fi

    # 3. Cache warmup & permissions
    echo "Warming up application cache..."
    php bin/console cache:warmup --no-interaction || true
    chown -R www-data:www-data /var/www/html/var /var/www/html/public 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
