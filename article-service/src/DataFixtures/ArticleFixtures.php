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
            'image_url' => '/uploads/dracaufeu.png'
        ],
        [
            'title' => 'Air Jordan 1 "Chicago" (1985)',
            'desc' => 'La paire mythique portée par MJ. Cuir premium, coloris OG White/Varsity Red-Black.',
            'base_price' => 15000,
            'image_url' => '/uploads/jordan.avif'
        ],
        [
            'title' => 'Rolex Daytona Cosmograph',
            'desc' => 'Chronographe de légende. Cadran Panda, lunette céramique noire. État exceptionnel.',
            'base_price' => 35000,
            'image_url' => '/uploads/rolex.webp'
        ],
        [
            'title' => 'Collection Rétrogaming Nintendo NES',
            'desc' => 'Lot console NES + manettes + Mario Bros. Le tout en boîte d\'origine parfaitement conservée.',
            'base_price' => 4500,
            'image_url' => '/uploads/nintendo_nes.webp'
        ],
        [
            'title' => 'Carte Magic Black Lotus (Alpha)',
            'desc' => 'La carte la plus célèbre de Magic: The Gathering. Édition Alpha. État Near Mint.',
            'base_price' => 60000,
            'image_url' => '/uploads/black_lotus.jpg'
        ],
        [
            'title' => 'Nike Air Mag "Marty McFly"',
            'desc' => 'La chaussure du futur. Laçage automatique. Modèle authentique de 2016 avec chargeur.',
            'base_price' => 35000,
            'image_url' => '/uploads/airmag.avif'
        ],
        [
            'title' => 'Patek Philippe Nautilus',
            'desc' => 'Modèle 5711 en acier. Cadran bleu dégradé. L\'élégance sportive ultime.',
            'base_price' => 120000,
            'image_url' => '/uploads/patek.avif'
        ],
        [
            'title' => 'Pikachu Illustrator (Promo)',
            'desc' => 'La carte la plus rare. Illustration par Atsuko Nishida. Un trésor absolu.',
            'base_price' => 500000,
            'image_url' => '/uploads/pikachu.png'
        ],
        [
            'title' => 'Leica M6 - Édition Titane',
            'desc' => 'Appareil photo télémétrique argentique. Une mécanique de précision allemande.',
            'base_price' => 8000,
            'image_url' => '/uploads/leica_m6.avif'
        ],
        [
            'title' => 'Yeezy Red October',
            'desc' => 'La dernière collaboration de Kanye West avec Nike. Neuve, jamais portée (DS).',
            'base_price' => 12000,
            'image_url' => '/uploads/yeezy.avif'
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
            $variation = $faker->randomFloat(2, 0.95, 1.05);
            $article->setPrice(round($itemModel['base_price'] * $variation, 2));
            $article->setShippingCost($faker->randomFloat(2, 5, 25));
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
