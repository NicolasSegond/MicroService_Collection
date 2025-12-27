<?php

namespace App\DataFixtures;

use App\Entity\Article;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ArticleFixtures extends Fixture implements DependentFixtureInterface
{
    private const COLLECTION_ITEMS = [
        [
            'title' => 'Dracaufeu 1ère Édition (Shadowless)',
            'desc' => 'Le Graal des collectionneurs Pokémon. Carte du set de base. Une pièce d\'histoire.',
            'base_price' => 250000,
            'image_url' => 'https://assets.pokemon.com/assets/cms2/img/pokedex/full/006.png'
        ],
        [
            'title' => 'Air Jordan 1 "Chicago" (1985)',
            'desc' => 'La paire mythique portée par MJ. Cuir premium, coloris OG White/Varsity Red-Black.',
            'base_price' => 15000,
            'image_url' => 'https://images.unsplash.com/photo-1552346154-21d32810aba3?q=80&w=1000&auto=format&fit=crop'
        ],
        [
            'title' => 'Rolex Daytona Cosmograph',
            'desc' => 'Chronographe de légende. Cadran Panda, lunette céramique noire. État exceptionnel.',
            'base_price' => 35000,
            'image_url' => 'https://images.unsplash.com/photo-1523170335258-f5ed11844a49?q=80&w=1000&auto=format&fit=crop'
        ],
        [
            'title' => 'Collection Rétrogaming Nintendo NES',
            'desc' => 'Lot console NES + manettes + Mario Bros. Le tout en boîte d\'origine parfaitement conservée.',
            'base_price' => 4500,
            'image_url' => 'https://images.unsplash.com/photo-1593118247619-e2d6f056869e?q=80&w=1000&auto=format&fit=crop'
        ],
        [
            'title' => 'Carte Magic Black Lotus (Alpha)',
            'desc' => 'La carte la plus célèbre de Magic: The Gathering. Édition Alpha. État Near Mint.',
            'base_price' => 60000,
            'image_url' => 'https://www.journaldugeek.com/app/uploads/2024/05/black-lotus-prix-record-1403x935.jpg'
        ],
        [
            'title' => 'Nike Air Mag "Marty McFly"',
            'desc' => 'La chaussure du futur. Laçage automatique. Modèle authentique de 2016 avec chargeur.',
            'base_price' => 35000,
            'image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?q=80&w=1000&auto=format&fit=crop'
        ],
        [
            'title' => 'Patek Philippe Nautilus',
            'desc' => 'Modèle 5711 en acier. Cadran bleu dégradé. L\'élégance sportive ultime.',
            'base_price' => 120000,
            'image_url' => 'https://www.theluxuryhut.com/_next/image/?url=https%3A%2F%2Fassets.theluxuryhut.com%2Fcms%2Fadmin%2Fupload%2F1675922477history-of-patek-philippe-nautilus.jpg&w=1080&q=75'
        ],
        [
            'title' => 'Pikachu Illustrator (Promo)',
            'desc' => 'La carte la plus rare. Illustration par Atsuko Nishida. Un trésor absolu.',
            'base_price' => 500000,
            'image_url' => 'https://assets.pokemon.com/assets/cms2/img/pokedex/full/025.png'
        ],
        [
            'title' => 'Leica M6 - Édition Titane',
            'desc' => 'Appareil photo télémétrique argentique. Une mécanique de précision allemande.',
            'base_price' => 8000,
            'image_url' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=1000&auto=format&fit=crop'
        ],
        [
            'title' => 'Yeezy Red October',
            'desc' => 'La dernière collaboration de Kanye West avec Nike. Neuve, jamais portée (DS).',
            'base_price' => 12000,
            'image_url' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?q=80&w=1000&auto=format&fit=crop'
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $usersIds = [
            UserInfoFixtures::USER_ADMIN_ID,
            UserInfoFixtures::USER_TEST_ID
        ];

        $items = self::COLLECTION_ITEMS;

        // On crée quelques articles
        for ($i = 0; $i < 15; $i++) {
            $itemModel = $items[$i % count($items)];

            $article = new Article();
            $article->setTitle($itemModel['title']);
            $article->setDescription($itemModel['desc']);

            // Variation de prix (+/- 5%)
            $variation = $faker->randomFloat(2, 0.95, 1.05);
            $article->setPrice(round($itemModel['base_price'] * $variation, 2));

            // URL Directe définie plus haut
            $article->setMainPhotoUrl($itemModel['image_url']);

            $article->setOwnerId($usersIds[array_rand($usersIds)]);
            $article->setStatus('PUBLISHED');
            $article->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-3 months')));

            $manager->persist($article);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserInfoFixtures::class];
    }
}
