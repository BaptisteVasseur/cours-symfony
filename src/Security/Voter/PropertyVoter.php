<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PropertyVoter extends Voter
{
    public const VIEW = 'PROPERTY_VIEW';
    public const EDIT = 'PROPERTY_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Property;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Property $property */
        $property = $subject;

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $isOwner = $property->getHost()?->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => $property->getStatus() === 'published' || $isOwner,
            self::EDIT => $isOwner,
            default => false,
        };
    }
}
