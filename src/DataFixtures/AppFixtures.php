<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\User;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test@test.fr');
        $user->setPhone('0909090909');
        $user->setPasswordHash('motdepasse');

        $manager->persist($user);

        $user2 = new User();
        $user2->setEmail('test2@test.fr');
        $user2->setPhone('0909320909');
        $user2->setPasswordHash('motdepasse');

        $manager->persist($user2);

        $manager->flush();
    }
}
