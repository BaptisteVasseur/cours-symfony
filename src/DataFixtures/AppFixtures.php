<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserDocument;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test1@example.com');
        $user->setPhone('0123456789');
        $user->setPasswordHash('test');
        $manager->persist($user);

        $user2 = new User();
        $user2->setEmail('test2@example.com');
        $user2->setPhone('1123456789');
        $user2->setPasswordHash('test');

        $manager->persist($user2);


        $document = new UserDocument();
        $document->setFileUrl('https://example.com/document.pdf');
        $document->setType('piece_identite');
        $manager->flush();
    }
}
