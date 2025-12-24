<?php

namespace App\DataFixtures;

use App\Entity\Article;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ArticleFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $usersIds = [
            UserInfoFixtures::USER_ADMIN_ID,
            UserInfoFixtures::USER_TEST_ID
        ];

        for ($i = 0; $i < 20; $i++) {
            $article = new Article();
            $article->setTitle($faker->sentence(4));
            $article->setDescription($faker->paragraph());
            $article->setPrice($faker->randomFloat(2, 10, 500));
            $article->setMainPhotoUrl('https://picsum.photos/seed/' . $i . '/300/200');

            // Assignation aléatoire d'un propriétaire
            $article->setOwnerId($usersIds[array_rand($usersIds)]);

            // Statut et Dates
            $article->setStatus('PUBLISHED');
            $article->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months')));

            $manager->persist($article);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserInfoFixtures::class];
    }
}
