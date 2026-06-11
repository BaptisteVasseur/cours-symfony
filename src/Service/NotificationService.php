<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly NotificationRepository $notificationRepository,
        private readonly string $mailerFrom = 'no-reply@airbnb-clone.local',
    ) {}

    /**
     * Creates an in-app Notification entity for a user (channel='app').
     * Does NOT flush — caller is responsible for flush.
     */
    public function createInApp(User $user, string $type, string $title, string $content): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setChannel('app');
        $notification->setIsRead(false);
        $this->em->persist($notification);

        return $notification;
    }

    /**
     * Renders a Twig email template and sends it via Mailer.
     *
     * @param array<string, mixed> $context
     */
    public function sendEmail(User $recipient, string $subject, string $template, array $context = []): void
    {
        if ($recipient->getEmail() === null) {
            return;
        }

        $htmlBody = $this->twig->render($template, $context);

        $email = (new Email())
            ->from(new Address($this->mailerFrom, 'Airbnb Clone'))
            ->to(new Address($recipient->getEmail()))
            ->subject($subject)
            ->html($htmlBody);

        $this->mailer->send($email);
    }

    /**
     * Marks all unread in-app notifications for a user as read.
     */
    public function markAllRead(User $user): void
    {
        foreach ($this->notificationRepository->findUnreadForUser($user, 100) as $notification) {
            $notification->setIsRead(true);
        }
        $this->em->flush();
    }
}
