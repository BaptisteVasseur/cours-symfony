<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class BookingVoter extends Voter
{
    public const VIEW = 'booking_view';
    public const MANAGE = 'booking_manage';
    public const CANCEL = 'booking_cancel';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE, self::CANCEL], true)
            && $subject instanceof Reservation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $reservation = $subject;
        if (!$reservation instanceof Reservation) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $isGuest = $reservation->getGuest()?->getId() === $user->getId();
        $isHost = $reservation->getHost()?->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $isGuest || $isHost,
            self::MANAGE => $isHost,
            self::CANCEL => $isGuest || $isHost,
            default => false,
        };
    }
}
