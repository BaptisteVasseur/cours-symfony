<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['first' => 'Alice',   'last' => 'Martin',   'email' => 'alice@example.com',   'status' => 'active',    'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Voyageuse passionnée par la découverte de nouvelles cultures.'],
            ['first' => 'Bob',     'last' => 'Dupont',   'email' => 'bob@example.com',     'status' => 'active',    'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Hôte expérimenté avec 5 propriétés en France.'],
            ['first' => 'Clara',   'last' => 'Lefebvre', 'email' => 'clara@example.com',   'status' => 'active',    'lang' => 'en', 'currency' => 'USD', 'bio' => 'Digital nomad who loves cozy apartments.'],
            ['first' => 'David',   'last' => 'Bernard',  'email' => 'david@example.com',   'status' => 'suspended', 'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Amateur de randonnée et de séjours en montagne.'],
            ['first' => 'Emma',    'last' => 'Moreau',   'email' => 'emma@example.com',    'status' => 'active',    'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Passionnée de gastronomie locale et de voyages slow.'],
            ['first' => 'Fabrice', 'last' => 'Petit',    'email' => 'fabrice@example.com', 'status' => 'active',    'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Propriétaire d\'un gîte en Provence depuis 2018.'],
            ['first' => 'Grace',   'last' => 'Simon',    'email' => 'grace@example.com',   'status' => 'pending',   'lang' => 'en', 'currency' => 'GBP', 'bio' => 'Loves exploring hidden gems across Europe.'],
            ['first' => 'Hugo',    'last' => 'Laurent',  'email' => 'hugo@example.com',    'status' => 'active',    'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Nomade urbain adepte des city breaks.'],
            ['first' => 'Inès',   'last' => 'Garcia',   'email' => 'ines@example.com',    'status' => 'active',    'lang' => 'es', 'currency' => 'EUR', 'bio' => 'Aventurière qui préfère les logements atypiques.'],
            ['first' => 'Jules',   'last' => 'Thomas',   'email' => 'jules@example.com',   'status' => 'active',    'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Passionné de surf et de locations en bord de mer.'],
        ];

        $avatars = [
            'https://i.pravatar.cc/150?img=1',
            'https://i.pravatar.cc/150?img=2',
            'https://i.pravatar.cc/150?img=3',
            'https://i.pravatar.cc/150?img=4',
            'https://i.pravatar.cc/150?img=5',
            'https://i.pravatar.cc/150?img=6',
            'https://i.pravatar.cc/150?img=7',
            'https://i.pravatar.cc/150?img=8',
            'https://i.pravatar.cc/150?img=9',
            'https://i.pravatar.cc/150?img=10',
        ];

        foreach ($users as $i => $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setPasswordHash($this->hasher->hashPassword($user, 'password123'));
            $user->setStatus($data['status']);
            $user->setIsEmailVerified(true);
            $user->setPreferredLanguage($data['lang']);
            $user->setPreferredCurrency($data['currency']);
            $user->setPhone(sprintf('+336%08d', random_int(10000000, 99999999)));

            $profile = new UserProfile();
            $profile->setFirstName($data['first']);
            $profile->setLastName($data['last']);
            $profile->setBio($data['bio']);
            $profile->setAvatarUrl($avatars[$i]);
            $profile->setIdentityStatus($i % 3 === 0 ? 'verified' : 'pending');
            $profile->setBirthDate(new \DateTimeImmutable(sprintf('19%02d-%02d-%02d', random_int(70, 99), random_int(1, 12), random_int(1, 28))));

            $user->setProfile($profile);

            $manager->persist($user);
            $manager->persist($profile);

            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }
}
