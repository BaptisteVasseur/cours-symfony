<?php

declare(strict_types=1);

namespace App\Security;

final class Roles
{
    public const HOST = 'ROLE_HOST';
    public const ADMIN = 'ROLE_ADMIN';
    public const SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    public const ASSIGNABLE = [
        self::HOST => 'Hôte',
        self::ADMIN => 'Administrateur',
        self::SUPER_ADMIN => 'Super Administrateur',
    ];

    public const LABELS = [
        self::HOST => 'Hôte',
        self::ADMIN => 'Administrateur',
        self::SUPER_ADMIN => 'Super Administrateur',
    ];

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? $role;
    }

    public static function primaryLabel(array $roles): string
    {
        foreach ([self::SUPER_ADMIN, self::ADMIN, self::HOST] as $role) {
            if (in_array($role, $roles, true)) {
                return self::label($role);
            }
        }

        return 'Voyageur';
    }
}
