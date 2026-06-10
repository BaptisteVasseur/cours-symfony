<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class NotificationFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $users = [
            FixtureReferences::USER_GUEST_1,
            FixtureReferences::USER_GUEST_2,
            FixtureReferences::USER_HOST_1,
            FixtureReferences::USER_HOST_2,
            FixtureReferences::USER_ADMIN,
        ];

        $templates = [
            ['reservation_confirmed', 'Réservation confirmée', 'Votre séjour est confirmé. Bon voyage !', 'email', false],
            ['new_message', 'Nouveau message', 'Vous avez reçu un message de votre hôte.', 'push', false],
            ['payment_received', 'Paiement reçu', 'Votre paiement de 840,00 € a été traité avec succès.', 'email', true],
            ['listing_pending', 'Annonce en attente', 'Votre annonce est en cours de vérification par notre équipe.', 'email', false],
            ['review_received', 'Nouvel avis', 'Un voyageur a laissé un avis sur votre logement.', 'push', true],
            ['document_rejected', 'Document refusé', 'Votre pièce d\'identité n\'a pas pu être validée.', 'email', false],
        ];

        foreach ($users as $userRef) {
            $user = $this->getReference($userRef, User::class);

            foreach ($templates as $index => [$type, $title, $content, $channel, $isRead]) {
                $notification = new Notification();
                $notification->setUser($user);
                $notification->setType($type);
                $notification->setTitle($title);
                $notification->setContent($content);
                $notification->setChannel($channel);
                $notification->setIsRead($isRead);
                $notification->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', $index + 1)));
                $manager->persist($notification);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixture::class];
    }
}
