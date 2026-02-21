#!/bin/sh
set -e

# Installer les dépendances si vendor absent (dev avec volume monté)
if [ ! -d 'vendor/' ]; then
    echo "Installing composer dependencies..."
    composer install --prefer-dist --no-progress --no-interaction
fi

# Attendre que la base de données soit prête (init container l'a déjà vérifiée, check rapide)
if [ -n "$DATABASE_URL" ]; then
    echo "Checking database connection..."
    # Check rapide - la DB devrait déjà être prête via init container K8s
    ATTEMPTS=5
    until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1 || [ $ATTEMPTS -eq 0 ]; do
        sleep 1
        ATTEMPTS=$((ATTEMPTS - 1))
    done

    if [ $ATTEMPTS -eq 0 ]; then
        echo "Database not reachable"
        exit 1
    fi
    echo "Database is ready"

    # Exécuter les migrations si elles existent
    if [ "$SKIP_MIGRATIONS" != "true" ] && [ "$( find ./migrations -iname '*.php' -print -quit 2>/dev/null )" ]; then
        echo "Running migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction
    fi

    USER_COUNT=$(php bin/console dbal:run-sql "SELECT COUNT(*) FROM user_info" 2>/dev/null | grep -o '[0-9]*' | head -n1 || echo 0)

    # On ne lance les fixtures que si la base est vide (count == 0)
    if [ "$APP_ENV" != 'prod' ] && [ "$USER_COUNT" -eq 0 ]; then
        echo "Loading fixtures..."
        php bin/console doctrine:fixtures:load --no-interaction
    fi
fi

exec "$@"
