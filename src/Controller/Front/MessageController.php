<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\Reservation;
use App\Entity\Notification;  
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ConversationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

#[Route('/messages')]
#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    #[Route('', name: 'app_messages_index', methods: ['GET'])]
    public function index(ConversationRepository $conversationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/message/index.html.twig', [
            'conversations' => $conversationRepository->findForUser($user),
        ]);
    }

    #[Route('/{id}', name: 'app_messages_show', methods: ['GET', 'POST'])]
    #[IsGranted(ConversationVoter::PARTICIPATE, subject: 'conversation')]
    public function show(
        Request $request,
        Conversation $conversation,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $conversation = $conversationRepository->findOneForUser($conversation, $user);
        if ($conversation === null) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(MessageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $content = trim((string) $form->get('content')->getData());

            $message = new Message();
            $message->setConversation($conversation);
            $message->setSender($user);
            $message->setMessageType('text');
            $message->setContent($content);
            $message->setIsFlagged(false);
            $entityManager->persist($message);
            
            $recipient = $this->getOtherParticipant($conversation, $user);
            if ($recipient) {
                $notification = new Notification();
                $notification->setUser($recipient);
                $notification->setType('message_received');
                $notification->setTitle('Nouveau message');
                $notification->setContent(sprintf(
                    'Nouveau message de %s : "%s"',
                    $user->getProfile()?->getFirstName() ?? $user->getEmail(),
                    strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content
                ));
                $notification->setChannel('in_app');
                $notification->setIsRead(false);
                $entityManager->persist($notification);
            }
            
            $entityManager->flush();

            return $this->redirectToRoute('app_messages_show', ['id' => $conversation->getId()]);
        }

        return $this->render('front/message/show.html.twig', [
            'conversation' => $conversation,
            'form' => $form,
        ]);
    }

    #[Route('/start/{reservationId}', name: 'app_messages_conversation_start', methods: ['GET'])]
    public function startConversation(
        #[MapEntity(mapping: ['reservationId' => 'id'])] Reservation $reservation,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = $reservation->getProperty();
        $isGuest = $reservation->getGuest() === $user;
        $isHost = $property->getHost() === $user;

        if (!$isGuest && !$isHost) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à contacter cette personne.');
        }

        $conversation = $em->getRepository(Conversation::class)->findOneBy([
            'reservation' => $reservation,
        ]);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setReservation($reservation);
            $em->persist($conversation);
            
            $participant1 = new ConversationParticipant();
            $participant1->setConversation($conversation);
            $participant1->setUser($user);
            $em->persist($participant1);
            
            $otherUser = $isGuest ? $property->getHost() : $reservation->getGuest();
            $participant2 = new ConversationParticipant();
            $participant2->setConversation($conversation);
            $participant2->setUser($otherUser);
            $em->persist($participant2);
            
            $em->flush();
        }

        return $this->redirectToRoute('app_messages_show', ['id' => $conversation->getId()]);
    }

    private function getOtherParticipant(Conversation $conversation, User $currentUser): ?User
    {
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getUser() !== $currentUser) {
                return $participant->getUser();
            }
        }
        return null;
    }
}