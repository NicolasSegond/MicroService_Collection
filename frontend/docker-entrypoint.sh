#!/bin/sh
set -e

# Installer les dépendances si node_modules absent (dev avec volume monté)
if [ ! -d 'node_modules/' ]; then
    echo "Installing npm dependencies..."
    npm install
fi

exec "$@"
