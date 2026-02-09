<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\UserInfo;
use App\MessageHandler\KeycloakEventHandler;
use App\Repository\UserInfoRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KeycloakEventHandlerTest extends TestCase
{
    public function testHandleWithMissingTypeLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid Keycloak event: missing type or userId', ['userId' => 'user-123']);

        $handler = $this->createHandler(logger: $logger);
        $handler->handle(['userId' => 'user-123']);
    }

    private function createHandler(
        ?EntityManagerInterface $entityManager = null,
        ?UserInfoRepository     $userInfoRepository = null,
        ?LoggerInterface        $logger = null
    ): KeycloakEventHandler
    {
        return new KeycloakEventHandler(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $userInfoRepository ?? $this->createStub(UserInfoRepository::class),
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    public function testHandleWithMissingUserIdLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid Keycloak event: missing type or userId', ['type' => 'REGISTER']);

        $handler = $this->createHandler(logger: $logger);
        $handler->handle(['type' => 'REGISTER']);
    }

    public function testHandleRegisterCreatesNewUser(): void
    {
        $userInfoRepository = $this->createMock(UserInfoRepository::class);
        $userInfoRepository
            ->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (UserInfo $userInfo) {
                return $userInfo->getId() === 'user-123'
                    && $userInfo->getEmail() === 'john@example.com'
                    && $userInfo->getFirstName() === 'John'
                    && $userInfo->getLastName() === 'Doe'
                    && $userInfo->getAvatarUrl() === 'https://example.com/avatar.jpg';
            }));
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $handler = $this->createHandler($entityManager, $userInfoRepository);
        $handler->handle([
            'type' => 'REGISTER',
            'userId' => 'user-123',
            'details' => [
                'email' => 'john@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'avatarUrl' => 'https://example.com/avatar.jpg'
            ]
        ]);
    }

    public function testHandleRegisterUsesUsernameWhenEmailMissing(): void
    {
        $userInfoRepository = $this->createStub(UserInfoRepository::class);
        $userInfoRepository->method('find')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (UserInfo $userInfo) {
                return $userInfo->getEmail() === 'johndoe';
            }));

        $handler = $this->createHandler($entityManager, $userInfoRepository);
        $handler->handle([
            'type' => 'REGISTER',
            'userId' => 'user-123',
            'details' => ['username' => 'johndoe']
        ]);
    }

    public function testHandleRegisterUsesSnakeCaseFields(): void
    {
        $userInfoRepository = $this->createStub(UserInfoRepository::class);
        $userInfoRepository->method('find')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (UserInfo $userInfo) {
                return $userInfo->getFirstName() === 'John'
                    && $userInfo->getLastName() === 'Doe'
                    && $userInfo->getAvatarUrl() === 'https://example.com/avatar.jpg';
            }));

        $handler = $this->createHandler($entityManager, $userInfoRepository);
        $handler->handle([
            'type' => 'REGISTER',
            'userId' => 'user-123',
            'details' => [
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'avatar_url' => 'https://example.com/avatar.jpg'
            ]
        ]);
    }

    public function testHandleRegisterIgnoresExistingUser(): void
    {
        $existingUser = new UserInfo('user-123', 'old@example.com');

        $userInfoRepository = $this->createMock(UserInfoRepository::class);
        $userInfoRepository
            ->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('persist');
        $entityManager
            ->expects($this->never())
            ->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('already exists, ignoring REGISTER event'));

        $handler = $this->createHandler($entityManager, $userInfoRepository, $logger);
        $handler->handle([
            'type' => 'REGISTER',
            'userId' => 'user-123',
            'details' => [
                'email' => 'new@example.com',
                'firstName' => 'Johnny'
            ]
        ]);

        // L'utilisateur existant n'est PAS modifié
        $this->assertEquals('old@example.com', $existingUser->getEmail());
    }

    public function testHandleUpdateProfileUpdatesExistingUser(): void
    {
        $existingUser = new UserInfo('user-123', 'old@example.com');
        $existingUser->setFirstName('John');
        $existingUser->setLastName('Doe');

        $userInfoRepository = $this->createMock(UserInfoRepository::class);
        $userInfoRepository
            ->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $handler = $this->createHandler($entityManager, $userInfoRepository);
        $handler->handle([
            'type' => 'UPDATE_PROFILE',
            'userId' => 'user-123',
            'details' => [
                'updated_email' => 'updated@example.com',
                'updated_first_name' => 'Johnny',
                'updated_last_name' => 'Updated'
            ]
        ]);

        $this->assertEquals('updated@example.com', $existingUser->getEmail());
        $this->assertEquals('Johnny', $existingUser->getFirstName());
        $this->assertEquals('Updated', $existingUser->getLastName());
    }

    public function testHandleUpdateProfileIgnoresIfUserNotFound(): void
    {
        $userInfoRepository = $this->createMock(UserInfoRepository::class);
        $userInfoRepository
            ->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('persist');
        $entityManager
            ->expects($this->never())
            ->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('not found for UPDATE_PROFILE, event ignored'));

        $handler = $this->createHandler($entityManager, $userInfoRepository, $logger);
        $handler->handle([
            'type' => 'UPDATE_PROFILE',
            'userId' => 'user-123',
            'details' => ['updated_email' => 'new@example.com']
        ]);
    }

    public function testHandleDeleteAccountRemovesUser(): void
    {
        $existingUser = new UserInfo('user-123', 'john@example.com');

        $userInfoRepository = $this->createMock(UserInfoRepository::class);
        $userInfoRepository
            ->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($existingUser);
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $handler = $this->createHandler($entityManager, $userInfoRepository);
        $handler->handle([
            'type' => 'DELETE_ACCOUNT',
            'userId' => 'user-123'
        ]);
    }

    public function testHandleDeleteAccountLogsWarningIfUserNotFound(): void
    {
        $userInfoRepository = $this->createMock(UserInfoRepository::class);
        $userInfoRepository
            ->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('remove');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('not found for DELETE_ACCOUNT'));

        $handler = $this->createHandler($entityManager, $userInfoRepository, $logger);
        $handler->handle([
            'type' => 'DELETE_ACCOUNT',
            'userId' => 'user-123'
        ]);
    }

    public function testHandleUnknownEventTypeLogsDebug(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Ignoring event type'));

        $handler = $this->createHandler(logger: $logger);
        $handler->handle([
            'type' => 'UNKNOWN_EVENT',
            'userId' => 'user-123'
        ]);
    }

    public function testUpdateUserInfoOnlyUpdatesProvidedFields(): void
    {
        $existingUser = new UserInfo('user-123', 'original@example.com');
        $existingUser->setFirstName('Original');
        $existingUser->setLastName('Name');
        $existingUser->setAvatarUrl('https://original.com/avatar.jpg');

        $userInfoRepository = $this->createStub(UserInfoRepository::class);
        $userInfoRepository->method('find')->willReturn($existingUser);

        $handler = $this->createHandler(userInfoRepository: $userInfoRepository);
        $handler->handle([
            'type' => 'UPDATE_PROFILE',
            'userId' => 'user-123',
            'details' => [
                'updated_first_name' => 'Updated'
            ]
        ]);

        $this->assertEquals('original@example.com', $existingUser->getEmail());
        $this->assertEquals('Updated', $existingUser->getFirstName());
        $this->assertEquals('Name', $existingUser->getLastName());
        $this->assertEquals('https://original.com/avatar.jpg', $existingUser->getAvatarUrl());
    }
}
