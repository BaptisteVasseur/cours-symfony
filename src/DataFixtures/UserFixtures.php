<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    private const USERS = [
        [
            'email'     => 'host@airbnb.com',
            'password'  => 'password123',
            'status'    => 'active',
            'profile'   => ['firstName' => 'Marie',  'lastName' => 'Dupont'],
            'ref'       => 'user_host',
        ],
        [
            'email'     => 'guest1@airbnb.com',
            'password'  => 'password123',
            'status'    => 'active',
            'profile'   => ['firstName' => 'Thomas', 'lastName' => 'Martin'],
            'ref'       => 'user_guest_1',
        ],
        [
            'email'     => 'guest2@airbnb.com',
            'password'  => 'password123',
            'status'    => 'active',
            'profile'   => ['firstName' => 'Sophie', 'lastName' => 'Bernard'],
            'ref'       => 'user_guest_2',
        ],
        [
            'email'     => 'admin@airbnb.com',
            'password'  => 'admin1234',
            'status'    => 'active',
            'profile'   => ['firstName' => 'Admin',  'lastName' => 'Airbnb'],
            'ref'       => 'user_admin',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setStatus($data['status']);
            $user->setIsEmailVerified(true);
            $user->setPreferredLanguage('fr');
            $user->setPreferredCurrency('EUR');

            // setPasswordHash() et non setPassword()
            $user->setPasswordHash(
                $this->hasher->hashPassword($user, $data['password'])
            );

            // UserProfile lié en OneToOne
            $profile = new UserProfile();
            $profile->setFirstName($data['profile']['firstName']);
            $profile->setLastName($data['profile']['lastName']);
            $user->setProfile($profile);

            $manager->persist($user);
            $this->addReference($data['ref'], $user);
        }

        $manager->flush();
    }
}