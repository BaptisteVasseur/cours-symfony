<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ConversationVoter extends Voter
{
    public const PARTICIPATE = 'CONVERSATION_PARTICIPATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::PARTICIPATE && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var Conversation $conversation */
        $conversation = $subject;

        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
