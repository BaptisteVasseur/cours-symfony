<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class BookingVoter extends Voter
{
    public const VIEW = 'BOOKING_VIEW';
    public const CANCEL = 'BOOKING_CANCEL';
    public const MODERATE = 'BOOKING_MODERATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CANCEL, self::MODERATE], true)
            && $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $booking = $subject;
        $isGuest = $booking->getGuest() === $user;
        $isHost = $booking->getListing()->getHost() === $user;

        return match ($attribute) {
            self::VIEW, self::CANCEL => $isGuest || $isHost,
            self::MODERATE => $isHost,
            default => false,
        };
    }
}
