<?php

namespace App\DataFixtures;

use App\Entity\UserInfo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserInfoFixtures extends Fixture
{
    // IDs provenant de ton realm-export.json
    public const USER_ADMIN_ID = 'admin-user-001';
    public const USER_TEST_ID = 'test-user-001';

    public function load(ObjectManager $manager): void
    {
        if (!$manager->find(UserInfo::class, self::USER_ADMIN_ID)) {
            $admin = new UserInfo(self::USER_ADMIN_ID, 'admin@example.com');
            $admin->setFirstName('Admin');
            $admin->setLastName('User');
            $admin->setAvatarUrl('https://api.dicebear.com/7.x/avataaars/svg?seed=admin');
            $manager->persist($admin);
        }

        if (!$manager->find(UserInfo::class, self::USER_TEST_ID)) {
            $user = new UserInfo(self::USER_TEST_ID, 'test@example.com');
            $user->setFirstName('Test');
            $user->setLastName('User');
            $user->setAvatarUrl('https://api.dicebear.com/7.x/avataaars/svg?seed=test');
            $manager->persist($user);
        }

        $manager->flush();
    }
}
