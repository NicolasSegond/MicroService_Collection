<?php

namespace App\Security;

class JwtUserFactory
{
    public function createFromTokenPayload(array $payload): JwtUser
    {
        $roles = $this->extractRoles($payload);

        return new JwtUser(
            id: $payload['sub'],
            username: $payload['preferred_username'] ?? $payload['sub'],
            email: $payload['email'] ?? null,
            firstName: $payload['given_name'] ?? null,
            lastName: $payload['family_name'] ?? null,
            roles: array_unique($roles),
            tokenData: $payload
        );
    }

    private const IGNORED_ROLES = [
        'offline_access',
        'uma_authorization',
        'default-roles-collector_realms',
    ];

    private function extractRoles(array $payload): array
    {
        $roles = [];

        if (isset($payload['realm_access']['roles']) && is_array($payload['realm_access']['roles'])) {
            foreach ($payload['realm_access']['roles'] as $role) {
                if (!in_array($role, self::IGNORED_ROLES, true)) {
                    $roles[] = $this->normalizeRole($role);
                }
            }
        }

        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
    }

    private function normalizeRole(string $role): string
    {
        if (str_starts_with($role, 'ROLE_')) {
            return strtoupper(str_replace('-', '_', $role));
        }

        return 'ROLE_' . strtoupper(str_replace('-', '_', $role));
    }
}
