<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Reservation>
 */
final class BookingVoter extends Voter
{
    public const CANCEL = 'BOOKING_CANCEL';
    public const VIEW   = 'BOOKING_VIEW';
    public const MANAGE = 'BOOKING_MANAGE'; // confirm / reject (host side)

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CANCEL, self::VIEW, self::MANAGE], true)
            && $subject instanceof Reservation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
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

        return match ($attribute) {
            self::VIEW   => $this->canView($reservation, $user),
            self::CANCEL => $this->canCancel($reservation, $user),
            self::MANAGE => $this->canManage($reservation, $user),
            default      => false,
        };
    }

    private function canView(Reservation $reservation, User $user): bool
    {
        return $reservation->getGuest() === $user
            || ($reservation->getProperty()?->getHost() === $user);
    }

    private function canCancel(Reservation $reservation, User $user): bool
    {
        return $reservation->getGuest() === $user
            || ($reservation->getProperty()?->getHost() === $user);
    }

    private function canManage(Reservation $reservation, User $user): bool
    {
        return $reservation->getProperty()?->getHost() === $user;
    }
}
