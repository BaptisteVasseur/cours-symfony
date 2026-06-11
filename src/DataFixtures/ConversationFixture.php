<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ConversationFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $reservation = $this->getReference(FixtureReferences::RESERVATION_CONFIRMED, Reservation::class);
        $guest = $this->getReference(FixtureReferences::USER_GUEST_1, User::class);
        $host = $this->getReference(FixtureReferences::USER_HOST_2, User::class);

        $conversation = new Conversation();
        $conversation->setReservation($reservation);
        $manager->persist($conversation);
        $this->addReference(FixtureReferences::CONVERSATION_1, $conversation);

        foreach ([$guest, $host] as $participant) {
            $conversationParticipant = new ConversationParticipant();
            $conversationParticipant->setConversation($conversation);
            $conversationParticipant->setUser($participant);
            $manager->persist($conversationParticipant);
        }

        $messages = [
            [$guest, 'text', 'Bonjour, est-il possible d\'arriver à 18h plutôt qu\'à 15h ?'],
            [$host, 'text', 'Bonjour Sophie ! Oui, pas de souci pour un check-in à 18h.'],
            [$guest, 'text', 'Parfait, merci beaucoup !'],
            [$host, 'text', 'Je vous envoie les instructions d\'accès la veille de votre arrivée.'],
        ];

        foreach ($messages as $index => [$sender, $type, $content]) {
            $message = new Message();
            $message->setConversation($conversation);
            $message->setSender($sender);
            $message->setMessageType($type);
            $message->setContent($content);
            $message->setIsFlagged(false);
            $message->setCreatedAt(new \DateTimeImmutable(sprintf('-%d hours', 4 - $index)));
            $manager->persist($message);
        }

        $completed = $this->getReference(FixtureReferences::RESERVATION_COMPLETED, Reservation::class);
        $guest2 = $this->getReference(FixtureReferences::USER_GUEST_2, User::class);
        $host1 = $this->getReference(FixtureReferences::USER_HOST_1, User::class);

        $conversation2 = new Conversation();
        $conversation2->setReservation($completed);
        $manager->persist($conversation2);

        foreach ([$guest2, $host1] as $participant) {
            $conversationParticipant = new ConversationParticipant();
            $conversationParticipant->setConversation($conversation2);
            $conversationParticipant->setUser($participant);
            $manager->persist($conversationParticipant);
        }

        $message = new Message();
        $message->setConversation($conversation2);
        $message->setSender($guest2);
        $message->setMessageType('text');
        $message->setContent('Merci pour ce super séjour, tout était parfait !');
        $message->setIsFlagged(false);
        $manager->persist($message);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ReservationFixture::class, UserFixture::class];
    }
}
