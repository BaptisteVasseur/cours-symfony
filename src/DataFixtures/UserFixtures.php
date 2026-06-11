<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Security\Roles;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de test — mot de passe : password123
 *
 * | Email                     | Rôle(s)              | Statut    |
 * |---------------------------|----------------------|-----------|
 * | admin@test.local          | SUPER_ADMIN + ADMIN  | active    |
 * | moderateur@test.local     | ADMIN                | active    |
 * | alice@example.com         | HOST                 | active    |
 * | bob@example.com           | HOST                 | active    |
 * | clara@example.com         | USER (voyageur)      | active    |
 * | david@example.com         | USER (voyageur)      | suspended |
 * | emma@example.com          | USER (voyageur)      | active    |
 * | fabrice@example.com       | HOST                 | active    |
 * | grace@example.com         | USER (voyageur)      | pending   |
 * | hugo@example.com          | USER (voyageur)      | active    |
 * | ines@example.com          | USER (voyageur)      | active    |
 * | jules@example.com         | HOST                 | active    |
 */
class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // Admins dédiés
        $this->createAndPersist($manager, 'admin', [
            'email'          => 'admin@test.local',
            'first'          => 'Admin',
            'last'           => 'Super',
            'status'         => 'active',
            'roles'          => [Roles::SUPER_ADMIN, Roles::ADMIN],
            'identityStatus' => 'verified',
            'lang'           => 'fr',
            'currency'       => 'EUR',
            'bio'            => 'Compte super-administrateur.',
            'avatar'         => 'https://i.pravatar.cc/150?img=20',
        ]);

        $this->createAndPersist($manager, 'moderateur', [
            'email'          => 'moderateur@test.local',
            'first'          => 'Modérateur',
            'last'           => 'Plateforme',
            'status'         => 'active',
            'roles'          => [Roles::ADMIN],
            'identityStatus' => 'verified',
            'lang'           => 'fr',
            'currency'       => 'EUR',
            'bio'            => 'Compte modération.',
            'avatar'         => 'https://i.pravatar.cc/150?img=21',
        ]);

        // Utilisateurs numérotés (user_0 … user_9) — référencés par AppFixtures
        // user_0, user_5, user_9 sont hôtes (AppFixtures leur assigne des propriétés)
        $users = [
            0  => ['first' => 'Alice',   'last' => 'Martin',   'email' => 'alice@example.com',   'status' => 'active',    'roles' => [Roles::HOST], 'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Hôte avec plusieurs logements en France.',             'avatar' => 'https://i.pravatar.cc/150?img=1'],
            1  => ['first' => 'Bob',     'last' => 'Dupont',   'email' => 'bob@example.com',     'status' => 'active',    'roles' => [Roles::HOST], 'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Hôte expérimenté avec 5 propriétés en France.',       'avatar' => 'https://i.pravatar.cc/150?img=2'],
            2  => ['first' => 'Clara',   'last' => 'Lefebvre', 'email' => 'clara@example.com',   'status' => 'active',    'roles' => [],            'lang' => 'en', 'currency' => 'USD', 'bio' => 'Digital nomad who loves cozy apartments.',            'avatar' => 'https://i.pravatar.cc/150?img=3'],
            3  => ['first' => 'David',   'last' => 'Bernard',  'email' => 'david@example.com',   'status' => 'suspended', 'roles' => [],            'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Amateur de randonnée et de séjours en montagne.',     'avatar' => 'https://i.pravatar.cc/150?img=4'],
            4  => ['first' => 'Emma',    'last' => 'Moreau',   'email' => 'emma@example.com',    'status' => 'active',    'roles' => [],            'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Passionnée de gastronomie locale et de voyages slow.', 'avatar' => 'https://i.pravatar.cc/150?img=5'],
            5  => ['first' => 'Fabrice', 'last' => 'Petit',    'email' => 'fabrice@example.com', 'status' => 'active',    'roles' => [Roles::HOST], 'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Propriétaire d\'un gîte en Provence depuis 2018.',    'avatar' => 'https://i.pravatar.cc/150?img=6'],
            6  => ['first' => 'Grace',   'last' => 'Simon',    'email' => 'grace@example.com',   'status' => 'pending',   'roles' => [],            'lang' => 'en', 'currency' => 'GBP', 'bio' => 'Loves exploring hidden gems across Europe.',          'avatar' => 'https://i.pravatar.cc/150?img=7'],
            7  => ['first' => 'Hugo',    'last' => 'Laurent',  'email' => 'hugo@example.com',    'status' => 'active',    'roles' => [],            'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Nomade urbain adepte des city breaks.',               'avatar' => 'https://i.pravatar.cc/150?img=8'],
            8  => ['first' => 'Inès',   'last' => 'Garcia',   'email' => 'ines@example.com',    'status' => 'active',    'roles' => [],            'lang' => 'es', 'currency' => 'EUR', 'bio' => 'Aventurière qui préfère les logements atypiques.',    'avatar' => 'https://i.pravatar.cc/150?img=9'],
            9  => ['first' => 'Jules',   'last' => 'Thomas',   'email' => 'jules@example.com',   'status' => 'active',    'roles' => [Roles::HOST], 'lang' => 'fr', 'currency' => 'EUR', 'bio' => 'Passionné de surf et de locations en bord de mer.',  'avatar' => 'https://i.pravatar.cc/150?img=10'],
        ];

        foreach ($users as $i => $data) {
            $this->createAndPersist($manager, 'user_' . $i, $data);
        }

        $manager->flush();
    }

    /** @param array{email: string, first: string, last: string, status: string, roles: list<string>, lang: string, currency: string, bio: string, avatar: string} $data */
    private function createAndPersist(ObjectManager $manager, string $reference, array $data): User
    {
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPasswordHash($this->hasher->hashPassword($user, 'password123'));
        $user->setStatus($data['status']);
        $user->setIsEmailVerified($data['status'] === 'active');
        $user->setPreferredLanguage($data['lang']);
        $user->setPreferredCurrency($data['currency']);
        $user->setPhone(sprintf('+336%08d', random_int(10000000, 99999999)));
        $user->setAssignedRoles($data['roles']);

        $profile = new UserProfile();
        $profile->setFirstName($data['first']);
        $profile->setLastName($data['last']);
        $profile->setBio($data['bio']);
        $profile->setAvatarUrl($data['avatar']);
        $profile->setIdentityStatus($data['roles'] !== [] ? 'verified' : ($data['status'] === 'active' ? 'verified' : 'pending'));
        $profile->setBirthDate(new \DateTimeImmutable(sprintf('19%02d-%02d-%02d', random_int(70, 99), random_int(1, 12), random_int(1, 28))));

        $user->setProfile($profile);

        $manager->persist($user);
        $manager->persist($profile);

        $this->addReference($reference, $user);

        return $user;
    }
}
