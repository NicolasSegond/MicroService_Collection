#!/bin/bash

# Couleurs
GREEN='\033[32m'
YELLOW='\033[33m'
CYAN='\033[36m'
RED='\033[31m'
RESET='\033[0m'

echo -e "${CYAN}=== Chargement des données de test ===${RESET}"

# Vérifier que le pod article-db est prêt
echo -e "${YELLOW}Vérification de la base de données...${RESET}"
kubectl wait --for=condition=ready pod -l app=article-db -n marketplace --timeout=120s

if [ $? -ne 0 ]; then
    echo -e "${RED}Erreur: La base de données n'est pas prête${RESET}"
    exit 1
fi

echo -e "${GREEN}Base de données prête !${RESET}"

# Insérer les utilisateurs de test
echo -e "${YELLOW}Création des utilisateurs...${RESET}"
kubectl exec -n marketplace article-db-0 -- psql -U article_admin -d article_db -c "
INSERT INTO user_info (id, email, first_name, last_name, avatar_url, created_at, updated_at)
VALUES
  ('admin-user-001', 'admin@example.com', 'Admin', 'User', 'https://api.dicebear.com/7.x/avataaars/svg?seed=admin', NOW(), NOW()),
  ('test-user-001', 'test@example.com', 'Test', 'User', 'https://api.dicebear.com/7.x/avataaars/svg?seed=test', NOW(), NOW())
ON CONFLICT (id) DO UPDATE SET
  avatar_url = EXCLUDED.avatar_url,
  updated_at = NOW();
"

# Insérer les articles de test (fixtures)
echo -e "${YELLOW}Création des articles...${RESET}"
kubectl exec -n marketplace article-db-0 -- psql -U article_admin -d article_db -c "
-- Supprimer les anciens articles de test
DELETE FROM article WHERE title LIKE '%Dracaufeu%' OR title LIKE '%Jordan%' OR title LIKE '%Rolex%' OR title LIKE '%Nintendo%' OR title LIKE '%Magic%' OR title LIKE '%Nike Air%' OR title LIKE '%Patek%' OR title LIKE '%Pikachu%' OR title LIKE '%Leica%' OR title LIKE '%Yeezy%' OR title LIKE '%Omega%' OR title LIKE '%Game Boy%';

-- Insérer les articles de collection
INSERT INTO article (title, description, price, shipping_cost, main_photo_url, owner_id, status, created_at) VALUES
('Dracaufeu 1ère Édition (Shadowless)', 'Le Graal des collectionneurs Pokémon. Carte du set de base. Une pièce d''histoire.', 250000.00, 15.00, '/uploads/dracaufeu.png', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '45 days'),
('Air Jordan 1 \"Chicago\" (1985)', 'La paire mythique portée par MJ. Cuir premium, coloris OG White/Varsity Red-Black.', 15000.00, 25.00, '/uploads/jordan.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '30 days'),
('Rolex Daytona Cosmograph', 'Chronographe de légende. Cadran Panda, lunette céramique noire. État exceptionnel.', 35000.00, 20.00, '/uploads/rolex.webp', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '60 days'),
('Collection Rétrogaming Nintendo NES', 'Lot console NES + manettes + Mario Bros. Le tout en boîte d''origine parfaitement conservée.', 4500.00, 18.00, '/uploads/nintendo_nes.webp', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '15 days'),
('Carte Magic Black Lotus (Alpha)', 'La carte la plus célèbre de Magic: The Gathering. Édition Alpha. État Near Mint.', 60000.00, 10.00, '/uploads/black_lotus.jpg', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '75 days'),
('Nike Air Mag \"Marty McFly\"', 'La chaussure du futur. Laçage automatique. Modèle authentique de 2016 avec chargeur.', 35000.00, 25.00, '/uploads/airmag.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '20 days'),
('Patek Philippe Nautilus', 'Modèle 5711 en acier. Cadran bleu dégradé. L''élégance sportive ultime.', 120000.00, 20.00, '/uploads/patek.avif', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '90 days'),
('Pikachu Illustrator (Promo)', 'La carte la plus rare. Illustration par Atsuko Nishida. Un trésor absolu.', 500000.00, 15.00, '/uploads/pikachu.png', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '10 days'),
('Leica M6 - Édition Titane', 'Appareil photo télémétrique argentique. Une mécanique de précision allemande.', 8000.00, 22.00, '/uploads/leica_m6.avif', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '50 days'),
('Yeezy Red October', 'La dernière collaboration de Kanye West avec Nike. Neuve, jamais portée (DS).', 12000.00, 20.00, '/uploads/yeezy.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '5 days'),
('Omega Speedmaster Moonwatch', 'La montre qui a marché sur la Lune. Mouvement manuel, cadran noir.', 6500.00, 18.00, '/uploads/patek.avif', 'test-user-001', 'PUBLISHED', NOW() - INTERVAL '25 days'),
('Game Boy Color Pikachu Edition', 'Console portable Nintendo en édition limitée Pikachu. Parfait état.', 350.00, 12.00, '/uploads/nintendo_nes.webp', 'admin-user-001', 'PUBLISHED', NOW() - INTERVAL '8 days');
"

# Compter les articles
ARTICLE_COUNT=$(kubectl exec -n marketplace article-db-0 -- psql -U article_admin -d article_db -t -c "SELECT COUNT(*) FROM article;")

echo ""
echo -e "${GREEN}========================================${RESET}"
echo -e "${GREEN}   Données chargées avec succès !${RESET}"
echo -e "${GREEN}========================================${RESET}"
echo ""
echo -e "  ${CYAN}Utilisateurs:${RESET} 2 (admin, testuser)"
echo -e "  ${CYAN}Articles:${RESET}    $ARTICLE_COUNT"
echo ""
echo -e "  ${CYAN}Identifiants de test:${RESET}"
echo -e "    - testuser / test123"
echo -e "    - admin / admin123"
echo ""
