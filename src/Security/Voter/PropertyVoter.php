<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

final class PropertyVoter extends Voter
{
    public const EDIT = 'PROPERTY_EDIT';
    public const DELETE = 'PROPERTY_DELETE';
    public const MANAGE_AVAILABILITY = 'PROPERTY_MANAGE_AVAILABILITY';
    public const MANAGE_ICAL = 'PROPERTY_MANAGE_ICAL';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::MANAGE_AVAILABILITY, self::MANAGE_ICAL], true)
            && $subject instanceof Property;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Property $property */
        $property = $subject;

        $isOwner = $property->getHost() === $user;
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        return match ($attribute) {
            self::EDIT, self::DELETE => $isOwner || $isAdmin,
            self::MANAGE_AVAILABILITY, self::MANAGE_ICAL => $isOwner,
            default => false,
        };
    }
}
