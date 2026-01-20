#!/bin/bash
# Script unifié pour initialiser Kubernetes

GREEN='\033[32m'
YELLOW='\033[33m'
CYAN='\033[36m'
RED='\033[31m'
RESET='\033[0m'

echo -e "${CYAN}=== Initialisation Kubernetes ===${RESET}"

# 1. Attendre les pods
echo -e "${YELLOW}[1/3] Attente des pods...${RESET}"
kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=300s
kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=180s
kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=180s
echo -e "${GREEN}Pods prêts !${RESET}"

# 2. Migrations
echo -e "${YELLOW}[2/3] Migrations...${RESET}"
kubectl exec -n marketplace deploy/article-service -c article-service -- php bin/console doctrine:migrations:migrate --no-interaction 2>/dev/null
kubectl exec -n marketplace deploy/article-service -c article-service -- php bin/console doctrine:schema:update --force 2>/dev/null
echo -e "${GREEN}Base de données prête !${RESET}"

# 3. Données de test
echo -e "${YELLOW}[3/3] Chargement des données...${RESET}"
kubectl exec -n marketplace article-db-0 -- psql -U article_admin -d article_db -c "
INSERT INTO user_info (id, email, first_name, last_name, avatar_url, created_at, updated_at) VALUES
  ('admin-user-001', 'admin@example.com', 'Admin', 'User', 'https://api.dicebear.com/7.x/avataaars/svg?seed=admin', NOW(), NOW()),
  ('test-user-001', 'test@example.com', 'Test', 'User', 'https://api.dicebear.com/7.x/avataaars/svg?seed=test', NOW(), NOW())
ON CONFLICT (id) DO UPDATE SET avatar_url = EXCLUDED.avatar_url, updated_at = NOW();

DELETE FROM article WHERE id > 0;

INSERT INTO article (title, description, price, shipping_cost, main_photo_url, owner_id, status, created_at) VALUES
('Dracaufeu 1ère Édition', 'Le Graal des collectionneurs Pokémon.', 250000, 15, '/uploads/dracaufeu.png', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '45 days'),
('Air Jordan 1 Chicago (1985)', 'La paire mythique portée par MJ.', 15000, 25, '/uploads/jordan.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '30 days'),
('Rolex Daytona Cosmograph', 'Chronographe de légende.', 35000, 20, '/uploads/rolex.webp', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '60 days'),
('Nintendo NES Collection', 'Console + manettes + jeux en boîte.', 4500, 18, '/uploads/nintendo_nes.webp', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '15 days'),
('Black Lotus Alpha', 'La carte Magic la plus rare.', 60000, 10, '/uploads/black_lotus.jpg', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '75 days'),
('Nike Air Mag', 'La chaussure du futur.', 35000, 25, '/uploads/airmag.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '20 days'),
('Patek Philippe Nautilus', 'L élégance sportive ultime.', 120000, 20, '/uploads/patek.avif', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '90 days'),
('Pikachu Illustrator', 'La carte Pokémon la plus rare.', 500000, 15, '/uploads/pikachu.png', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '10 days'),
('Leica M6 Titane', 'Appareil argentique de légende.', 8000, 22, '/uploads/leica_m6.avif', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '50 days'),
('Yeezy Red October', 'Dernière collab Kanye x Nike.', 12000, 20, '/uploads/yeezy.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '5 days'),
('Omega Speedmaster', 'La montre lunaire.', 6500, 18, '/uploads/patek.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '25 days'),
('Game Boy Pikachu', 'Édition limitée Pokémon.', 350, 12, '/uploads/nintendo_nes.webp', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '8 days');
" 2>/dev/null

echo ""
echo -e "${GREEN}=== Initialisation terminée ===${RESET}"
echo -e "Frontend: ${CYAN}http://localhost:3000${RESET}"
echo -e "Login: ${CYAN}testuser / test123${RESET}"
