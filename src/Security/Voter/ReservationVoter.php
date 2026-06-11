<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ReservationVoter extends Voter
{
    public const VIEW = 'RESERVATION_VIEW';
    public const MANAGE = 'RESERVATION_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true)
            && $subject instanceof Reservation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Reservation $reservation */
        $reservation = $subject;

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $isGuest = $reservation->getGuest()?->getId() === $user->getId();
        $isHost = $reservation->getProperty()?->getHost()?->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $isGuest || $isHost,
            self::MANAGE => $isHost,
            default => false,
        };
    }
}
