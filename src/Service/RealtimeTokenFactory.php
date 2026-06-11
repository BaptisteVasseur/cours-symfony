<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RealtimeTokenFactory
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function create(User $user, int $ttlSeconds = 300): string
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \LogicException('Utilisateur invalide.');
        }

        $payload = [
            'uid' => $userId->toRfc4122(),
            'exp' => time() + $ttlSeconds,
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));

        return $encodedPayload.'.'.$signature;
    }

    public function userIdFromToken(string $token): ?string
    {
        if ($this->secret === '' || !str_contains($token, '.')) {
            return null;
        }

        [$encodedPayload, $signature] = explode('.', $token, 2);
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        try {
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['uid'], $payload['exp']) || !is_string($payload['uid']) || !is_int($payload['exp'])) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null;
        }

        return $payload['uid'];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
