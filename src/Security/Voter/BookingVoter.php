<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BookingVoter extends Voter
{
    public const BOOK = 'BOOK_PROPERTY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::BOOK && $subject instanceof Property;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Property $property */
        $property = $subject;

        if ($property->getHost() === $user) {
            return false;
        }

        return true;
    }
}
