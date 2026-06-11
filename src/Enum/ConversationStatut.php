<?php

namespace App\Enum;

enum ConversationStatut: string
{
    case ACTIVE = 'active';
    case ARCHIVEE = 'archivee';
    case BLOQUEE = 'bloquee';
}
