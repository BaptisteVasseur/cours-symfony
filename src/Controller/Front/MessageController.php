<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use App\Service\MailService;
use App\Service\NotificationService;
use App\Service\RealtimePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\ConversationVoter;
use App\Security\Voter\ReservationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        MailService $mailService,
        NotificationService $notificationService,
        RealtimePublisher $realtimePublisher,
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
            $entityManager->flush();

            foreach ($conversation->getParticipants() as $participant) {
                $participantUser = $participant->getUser();
                if ($participantUser instanceof User) {
                    $realtimePublisher->publishToUser($participantUser, 'message.created', [
                        'messageId' => $message->getId()?->toRfc4122(),
                        'conversationId' => $conversation->getId()?->toRfc4122(),
                        'senderId' => $user->getId()?->toRfc4122(),
                        'senderName' => $this->displayName($user),
                        'content' => $message->getContent(),
                        'createdAt' => $message->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                        'createdAtLabel' => $message->getCreatedAt()?->format('d/m H:i'),
                    ]);
                }
            }

            $recipient = null;
            foreach ($conversation->getParticipants() as $participant) {
                if ($participant->getUser()?->getId() !== $user->getId()) {
                    $recipient = $participant->getUser();
                    break;
                }
            }

            if ($recipient !== null) {
                $senderName = $user->getProfile() && $user->getProfile()->getFirstName()
                    ? $user->getProfile()->getFirstName()
                    : $user->getEmail();
                $title = sprintf('Nouveau message de %s', $senderName);
                $linkUrl = $this->generateUrl('app_messages_show', ['id' => $conversation->getId()]);
                $notificationService->notify($recipient, $title, $content, $linkUrl);

                $mailService->sendNewMessageEmail($message, $recipient);
            }

            return $this->redirectToRoute('app_messages_show', ['id' => $conversation->getId()]);
        }

        return $this->render('front/message/show.html.twig', [
            'conversation' => $conversation,
            'form' => $form,
        ], new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK
        ));
    }

    private function displayName(User $user): string
    {
        $profile = $user->getProfile();
        $name = trim(sprintf('%s %s', $profile?->getFirstName() ?? '', $profile?->getLastName() ?? ''));

        return $name !== '' ? $name : (string) $user->getEmail();
    }
}
