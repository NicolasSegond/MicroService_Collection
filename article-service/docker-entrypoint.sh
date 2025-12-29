#!/bin/sh
set -e

# Installer les dépendances si vendor absent (dev avec volume monté)
if [ ! -d 'vendor/' ]; then
    echo "Installing composer dependencies..."
    composer install --prefer-dist --no-progress --no-interaction
fi

# Attendre que la base de données soit prête
if [ -n "$DATABASE_URL" ]; then
    echo "Waiting for database to be ready..."
    ATTEMPTS_LEFT_TO_REACH_DATABASE=60

    until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
        if [ $? -eq 255 ]; then
            ATTEMPTS_LEFT_TO_REACH_DATABASE=0
            break
        fi
        sleep 1
        ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
        echo "Waiting for database... $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
    done

    if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
        echo "Database is not reachable:"
        echo "$DATABASE_ERROR"
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
