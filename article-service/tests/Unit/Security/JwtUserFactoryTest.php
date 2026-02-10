<?php

namespace App\Tests\Unit\Security;

use App\Security\JwtUser;
use App\Security\JwtUserFactory;
use PHPUnit\Framework\TestCase;

class JwtUserFactoryTest extends TestCase
{
    private JwtUserFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new JwtUserFactory();
    }

    private function createFullPayload(array $overrides = []): array
    {
        return array_merge([
            'sub' => 'user-uuid-123',
            'preferred_username' => 'johndoe',
            'email' => 'john@example.com',
            'given_name' => 'John',
            'family_name' => 'Doe',
            'realm_access' => [
                'roles' => ['admin', 'editor'],
            ],
        ], $overrides);
    }

    public function testCreateFromFullPayload(): void
    {
        $payload = $this->createFullPayload();

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertInstanceOf(JwtUser::class, $user);
        $this->assertSame('user-uuid-123', $user->getId());
        $this->assertSame('johndoe', $user->getUsername());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertSame($payload, $user->getTokenData());
    }

    public function testCreateFromMinimalPayload(): void
    {
        $payload = ['sub' => 'user-uuid-456'];

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertSame('user-uuid-456', $user->getId());
        $this->assertSame('user-uuid-456', $user->getUsername());
        $this->assertNull($user->getEmail());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());
    }

    public function testPreferredUsernameFallsBackToSub(): void
    {
        $payload = $this->createFullPayload();
        unset($payload['preferred_username']);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertSame('user-uuid-123', $user->getUsername());
    }

    public function testRolesAreNormalizedWithPrefix(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => ['roles' => ['admin']],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testRolesAlreadyPrefixedAreUppercased(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => ['roles' => ['ROLE_manager']],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_MANAGER', $user->getRoles());
    }

    public function testRolesWithHyphensAreConvertedToUnderscores(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => ['roles' => ['super-admin']],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testPrefixedRolesWithHyphensAreNormalized(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => ['roles' => ['ROLE_content-editor']],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_CONTENT_EDITOR', $user->getRoles());
    }

    public function testIgnoredRolesAreFiltered(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => [
                'roles' => [
                    'offline_access',
                    'uma_authorization',
                    'default-roles-collector_realms',
                    'editor',
                ],
            ],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_EDITOR', $roles);
        $this->assertNotContains('ROLE_OFFLINE_ACCESS', $roles);
        $this->assertNotContains('ROLE_UMA_AUTHORIZATION', $roles);
        $this->assertNotContains('ROLE_DEFAULT_ROLES_COLLECTOR_REALMS', $roles);
    }

    public function testDefaultRoleUserWhenNoRealmAccess(): void
    {
        $payload = $this->createFullPayload();
        unset($payload['realm_access']);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testDefaultRoleUserWhenRealmAccessHasNoRoles(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => [],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testDefaultRoleUserWhenAllRolesAreIgnored(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => [
                'roles' => ['offline_access', 'uma_authorization'],
            ],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertCount(0, array_diff($roles, ['ROLE_USER']));
    }

    public function testDuplicateRolesAreDeduped(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => ['roles' => ['admin', 'admin', 'editor']],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $roles = $user->getRoles();
        $this->assertSame(
            count(array_unique($roles)),
            count($roles),
            'Roles should not contain duplicates'
        );
    }

    public function testRealmAccessRolesNotArrayIsIgnored(): void
    {
        $payload = $this->createFullPayload([
            'realm_access' => ['roles' => 'not-an-array'],
        ]);

        $user = $this->factory->createFromTokenPayload($payload);

        $this->assertContains('ROLE_USER', $user->getRoles());
    }
}
