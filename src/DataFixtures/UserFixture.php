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
                'admin@airbnb-clone.fr',
                'Alexandre',
                'Dupuis',
                'active',
                true,
                [Roles::SUPER_ADMIN, Roles::ADMIN],
                'verified',
                true,
            ],
            [
                FixtureReferences::USER_ADMIN,
                'moderation@airbnb-clone.fr',
                'Claire',
                'Martin',
                'active',
                true,
                [Roles::ADMIN],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_HOST_1,
                'jeanmarc.dupont@email.com',
                'Jean-Marc',
                'Dupont',
                'active',
                true,
                [Roles::HOST],
                'verified',
                true,
            ],
            [
                FixtureReferences::USER_HOST_2,
                'elena.k@email.com',
                'Elena',
                'Kowalski',
                'active',
                true,
                [Roles::HOST],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_HOST_3,
                'pierre.lambert@email.com',
                'Pierre',
                'Lambert',
                'pending',
                false,
                [Roles::HOST],
                'pending',
                false,
            ],
            [
                FixtureReferences::USER_GUEST_1,
                'sophie.chen@email.com',
                'Sophie',
                'Chen',
                'active',
                true,
                [],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_GUEST_2,
                'lucas.bernard@email.com',
                'Lucas',
                'Bernard',
                'active',
                true,
                [],
                'verified',
                false,
            ],
            [
                FixtureReferences::USER_GUEST_3,
                'marie.dubois@email.com',
                'Marie',
                'Dubois',
                'suspended',
                true,
                [],
                'rejected',
                false,
            ],
        ];

        foreach ($users as [$reference, $email, $firstName, $lastName, $status, $verified, $roles, $identityStatus, $withOauth]) {
            $user = $this->createUser($email, $status, $verified, $roles);
            if (in_array(Roles::HOST, $roles, true)) {
                $user->regenerateCalendarToken();
            }
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setFirstName($firstName);
            $profile->setLastName($lastName);
            $profile->setBirthDate(new \DateTimeImmutable(sprintf('-%d years', random_int(25, 55))));
            $profile->setAvatarUrl(sprintf('https://i.pravatar.cc/150?u=%s', urlencode($email)));
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

        for ($i = 1; $i <= 20; $i++) {
            $user = $this->createUser(sprintf('guest%d@example.com', $i), 'active', $i % 3 !== 0, []);
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setFirstName('Voyageur');
            $profile->setLastName((string) $i);
            $profile->setIdentityStatus('verified');
            $manager->persist($profile);
            $user->setProfile($profile);
        }

        for ($i = 1; $i <= 10; $i++) {
            $user = $this->createUser(sprintf('host%d@example.com', $i), 'active', true, [Roles::HOST]);
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setFirstName('Hôte');
            $profile->setLastName((string) $i);
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
