#!/bin/bash
# Script pour installer les dépendances de tous les services

echo "🚀 Installation des dépendances pour tous les services..."

echo ""
echo "📦 [article-service] composer install..."
docker-compose exec -T article-service composer install

echo ""
echo "📦 [user-service] composer install..."
docker-compose exec -T user-service composer install

echo ""
echo "📦 [frontend] npm install..."
docker-compose exec -T frontend npm install

echo ""
echo "✅ Installation terminée !"

