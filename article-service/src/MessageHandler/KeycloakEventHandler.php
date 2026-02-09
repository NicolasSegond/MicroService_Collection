<?php

namespace App\MessageHandler;

use App\Entity\UserInfo;
use App\Repository\UserInfoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class KeycloakEventHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserInfoRepository $userInfoRepository,
        private LoggerInterface $logger
    ) {}

    public function handle(array $event): void
    {
        $type = $event['type'] ?? null;
        $userId = $event['userId'] ?? null;

        if (!$type || !$userId) {
            $this->logger->warning('Invalid Keycloak event: missing type or userId', $event);
            return;
        }

        $this->logger->info("Processing Keycloak event: {$type} for user {$userId}");

        match ($type) {
            'REGISTER' => $this->handleRegister($event),
            'UPDATE_PROFILE' => $this->handleUpdateProfile($event),
            'DELETE_ACCOUNT' => $this->handleDeleteAccount($event),
            default => $this->logger->debug("Ignoring event type: {$type}")
        };
    }

    private function handleRegister(array $event): void
    {
        $userId = $event['userId'];
        $details = $event['details'] ?? [];

        $email = $details['email'] ?? $details['username'] ?? '';
        $firstName = $details['first_name'] ?? $details['firstName'] ?? null;
        $lastName = $details['last_name'] ?? $details['lastName'] ?? null;
        $avatarUrl = $details['avatarUrl'] ?? $details['avatar_url'] ?? null;

        $existingUser = $this->userInfoRepository->find($userId);
        if ($existingUser) {
            $this->logger->warning("User {$userId} already exists, ignoring REGISTER event");
            return;
        }

        $userInfo = new UserInfo($userId, $email);
        $userInfo->setFirstName($firstName);
        $userInfo->setLastName($lastName);
        $userInfo->setAvatarUrl($avatarUrl);

        $this->entityManager->persist($userInfo);
        $this->entityManager->flush();

        $this->logger->info("Created UserInfo for user {$userId}");
    }

    private function handleUpdateProfile(array $event): void
    {
        $userId = $event['userId'];
        $details = $event['details'] ?? [];

        $userInfo = $this->userInfoRepository->find($userId);
        if (!$userInfo) {
            $this->logger->error("User {$userId} not found for UPDATE_PROFILE, event ignored");
            return;
        }

        $email = $details['updated_email'] ?? $details['email'] ?? null;
        $firstName = $details['updated_first_name'] ?? $details['firstName'] ?? null;
        $lastName = $details['updated_last_name'] ?? $details['lastName'] ?? null;
        $avatarUrl = $details['updated_avatarUrl'] ?? $details['avatarUrl'] ?? null;

        $this->updateUserInfo($userInfo, $email, $firstName, $lastName, $avatarUrl);
    }

    private function handleDeleteAccount(array $event): void
    {
        $userId = $event['userId'];

        $userInfo = $this->userInfoRepository->find($userId);
        if (!$userInfo) {
            $this->logger->warning("User {$userId} not found for DELETE_ACCOUNT");
            return;
        }

        $this->entityManager->remove($userInfo);
        $this->entityManager->flush();

        $this->logger->info("Deleted UserInfo for user {$userId}");
    }

    private function updateUserInfo(UserInfo $userInfo, ?string $email, ?string $firstName, ?string $lastName, ?string $avatarUrl = null): void
    {
        if ($email) {
            $userInfo->setEmail($email);
        }
        if ($firstName !== null) {
            $userInfo->setFirstName($firstName);
        }
        if ($lastName !== null) {
            $userInfo->setLastName($lastName);
        }
        if ($avatarUrl !== null) {
            $userInfo->setAvatarUrl($avatarUrl);
        }

        $this->entityManager->flush();
        $this->logger->info("Updated UserInfo for user {$userInfo->getId()}");
    }
}
