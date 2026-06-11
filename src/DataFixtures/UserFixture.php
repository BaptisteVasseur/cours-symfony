<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\OauthAccount;
use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Entity\UserDocument;
use App\Entity\UserProfile;
use App\Security\Roles;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                FixtureReferences::USER_SUPER_ADMIN,
                'admin@staynest.fr',
                'Mathieu',
                'Arnaud',
                'active',
                true,
                [Roles::SUPER_ADMIN, Roles::ADMIN],
                'verified',
                true,
            ],
            [
                FixtureReferences::USER_ADMIN,
                'moderation@staynest.fr',
                'Camille',
                'Rousseau',
                'active',
                true,
                [Roles::ADMIN],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_HOST_1,
                'thomas.leroy@email.com',
                'Thomas',
                'Leroy',
                'active',
                true,
                [Roles::HOST],
                'verified',
                true,
            ],
            [
                FixtureReferences::USER_HOST_2,
                'yasmine.benali@email.com',
                'Yasmine',
                'Benali',
                'active',
                true,
                [Roles::HOST],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_HOST_3,
                'nicolas.fontaine@email.com',
                'Nicolas',
                'Fontaine',
                'pending',
                false,
                [Roles::HOST],
                'pending',
                false,
            ],
            [
                FixtureReferences::USER_GUEST_1,
                'lea.moreau@email.com',
                'Léa',
                'Moreau',
                'active',
                true,
                [],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_GUEST_2,
                'romain.girard@email.com',
                'Romain',
                'Girard',
                'active',
                true,
                [],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_GUEST_3,
                'ines.perrot@email.com',
                'Inès',
                'Perrot',
                'suspended',
                true,
                [],
                'rejected',
                false,
            ],
        ];

        foreach ($users as [$reference, $email, $firstName, $lastName, $status, $verified, $roles, $identityStatus, $withOauth]) {
            $user = $this->createUser($email, $status, $verified, $roles);
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setFirstName($firstName);
            $profile->setLastName($lastName);
            $profile->setBirthDate(new \DateTimeImmutable(sprintf('-%d years', random_int(25, 55))));
            $avatarSeeds = [
                'admin@staynest.fr'           => 'https://randomuser.me/api/portraits/men/32.jpg',
                'moderation@staynest.fr'      => 'https://randomuser.me/api/portraits/women/44.jpg',
                'thomas.leroy@email.com'      => 'https://randomuser.me/api/portraits/men/52.jpg',
                'yasmine.benali@email.com'    => 'https://randomuser.me/api/portraits/women/68.jpg',
                'nicolas.fontaine@email.com'  => 'https://randomuser.me/api/portraits/men/71.jpg',
                'lea.moreau@email.com'        => 'https://randomuser.me/api/portraits/women/22.jpg',
                'romain.girard@email.com'     => 'https://randomuser.me/api/portraits/men/18.jpg',
                'ines.perrot@email.com'       => 'https://randomuser.me/api/portraits/women/55.jpg',
            ];
            $profile->setAvatarUrl($avatarSeeds[$email] ?? sprintf('https://i.pravatar.cc/150?u=%s', urlencode($email)));
            $profile->setBio(sprintf('Profil de %s %s sur la plateforme.', $firstName, $lastName));
            $profile->setIdentityStatus($identityStatus);
            $manager->persist($profile);
            $user->setProfile($profile);

            if ($withOauth) {
                $oauth = new OauthAccount();
                $oauth->setUser($user);
                $oauth->setProvider('google');
                $oauth->setProviderUserId('google_' . md5($email));
                $oauth->setAccessToken('access_token_demo');
                $oauth->setRefreshToken('refresh_token_demo');
                $manager->persist($oauth);
            }

            if ($identityStatus !== 'verified') {
                $document = new UserDocument();
                $document->setUser($user);
                $document->setType('identity_card');
                $document->setFileUrl('https://storage.example.com/documents/' . md5($email) . '.pdf');
                $document->setVerificationStatus($identityStatus === 'pending' ? 'pending' : 'rejected');
                $manager->persist($document);
            }

            $paymentMethod = new PaymentMethod();
            $paymentMethod->setUser($user);
            $paymentMethod->setProvider('stripe');
            $paymentMethod->setProviderPaymentMethodId('pm_demo_' . md5($email));
            $paymentMethod->setBrand('visa');
            $paymentMethod->setLast4((string) random_int(1000, 9999));
            $paymentMethod->setExpirationMonth(random_int(1, 12));
            $paymentMethod->setExpirationYear((int) date('Y') + random_int(1, 5));
            $manager->persist($paymentMethod);

            $this->addReference($reference, $user);
        }

        $guestNames = [
            ['Emma', 'Petit'], ['Hugo', 'Simon'], ['Chloé', 'Michel'], ['Nathan', 'Laurent'],
            ['Jade', 'Garcia'], ['Théo', 'David'], ['Manon', 'Bertrand'], ['Louis', 'Robert'],
            ['Zoé', 'Lecomte'], ['Axel', 'Morel'], ['Alice', 'Fournier'], ['Maxime', 'Blanc'],
            ['Lucie', 'Guerin'], ['Tom', 'Chevalier'], ['Inès', 'Faure'], ['Baptiste', 'Mathieu'],
            ['Clara', 'Renard'], ['Antoine', 'Clement'], ['Elisa', 'Gauthier'], ['Kevin', 'Noel'],
        ];
        for ($i = 1; $i <= 20; $i++) {
            $user = $this->createUser(sprintf('guest%d@example.com', $i), 'active', $i % 3 !== 0, []);
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            [$fn, $ln] = $guestNames[$i - 1];
            $profile->setFirstName($fn);
            $profile->setLastName($ln);
            $profile->setAvatarUrl(sprintf('https://i.pravatar.cc/150?img=%d', $i));
            $profile->setIdentityStatus('verified');
            $manager->persist($profile);
            $user->setProfile($profile);
        }

        $hostNames = [
            ['Olivier', 'Mercier'], ['Sabine', 'Dupuis'], ['Franck', 'Bonnet'], ['Aurelie', 'Henry'],
            ['Julien', 'Martin'], ['Nathalie', 'Perrin'], ['Yann', 'Muller'], ['Delphine', 'Leclerc'],
            ['Serge', 'Vasseur'], ['Isabelle', 'Collin'],
        ];
        for ($i = 1; $i <= 10; $i++) {
            $user = $this->createUser(sprintf('host%d@example.com', $i), 'active', true, [Roles::HOST]);
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            [$fn, $ln] = $hostNames[$i - 1];
            $profile->setFirstName($fn);
            $profile->setLastName($ln);
            $profile->setAvatarUrl(sprintf('https://i.pravatar.cc/150?img=%d', $i + 20));
            $profile->setIdentityStatus('verified');
            $manager->persist($profile);
            $user->setProfile($profile);
        }

        $manager->flush();
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $status, bool $verified, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));
        $user->setPhone('+336' . random_int(10000000, 99999999));
        $user->setStatus($status);
        $user->setIsEmailVerified($verified);
        $user->setPreferredLanguage('fr');
        $user->setPreferredCurrency('EUR');
        $user->setAssignedRoles($roles);

        return $user;
    }
}
