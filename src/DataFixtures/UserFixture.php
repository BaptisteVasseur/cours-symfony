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
            [FixtureReferences::USER_SUPER_ADMIN, 'superadmin@airbnb.dev', 'Super', 'Admin', 'Admin1234!', [Roles::SUPER_ADMIN, Roles::ADMIN], 'active', true, 'verified', true],
            [FixtureReferences::USER_ADMIN, 'admin@airbnb.dev', 'Admin', 'Airbnb', 'Admin1234!', [Roles::ADMIN], 'active', true, 'verified', false],
            [FixtureReferences::USER_HOST_1, 'host1@airbnb.dev', 'Camille', 'Durand', 'Host1234!', [Roles::HOST], 'active', true, 'verified', true],
            [FixtureReferences::USER_HOST_2, 'host2@airbnb.dev', 'Nicolas', 'Moreau', 'Host1234!', [Roles::HOST], 'active', true, 'verified', false],
            [FixtureReferences::USER_GUEST_1, 'user1@airbnb.dev', 'Sophie', 'Chen', 'User1234!', [], 'active', true, 'verified', false],
            [FixtureReferences::USER_GUEST_2, 'user2@airbnb.dev', 'Lucas', 'Bernard', 'User1234!', [], 'active', true, 'verified', false],
            [FixtureReferences::USER_GUEST_3, 'user3@airbnb.dev', 'Marie', 'Dubois', 'User1234!', [], 'suspended', true, 'rejected', false],
            [FixtureReferences::USER_GUEST_4, 'user4@airbnb.dev', 'Yanis', 'Leroy', 'User1234!', [], 'active', false, 'pending', false],
        ];

        foreach ($users as [$reference, $email, $firstName, $lastName, $password, $roles, $status, $verified, $identityStatus, $withOauth]) {
            $user = $this->createUser($email, $password, $status, $verified, $roles);
            $manager->persist($user);

            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setFirstName($firstName);
            $profile->setLastName($lastName);
            $profile->setBirthDate(new \DateTimeImmutable(sprintf('-%d years', random_int(25, 55))));
            $profile->setAvatarUrl(sprintf('https://i.pravatar.cc/150?u=%s', urlencode($email)));
            $profile->setBio(sprintf('Profil de %s %s sur Airbnb.', $firstName, $lastName));
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

        $manager->flush();
    }

    private function createUser(string $email, string $password, string $status, bool $verified, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $user->setPhone('+336' . random_int(10000000, 99999999));
        $user->setStatus($status);
        $user->setIsEmailVerified($verified);
        $user->setPreferredLanguage('fr');
        $user->setPreferredCurrency('EUR');
        $user->setAssignedRoles($roles);

        return $user;
    }
}
