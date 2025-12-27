<?php

namespace App\Command;

use App\Entity\UserInfo;
use App\Repository\UserInfoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:sync-keycloak-users',
    description: 'Sync existing Keycloak users to local UserInfo table'
)]
class SyncKeycloakUsersCommand extends Command
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private UserInfoRepository $userInfoRepository,
        private string $keycloakUrl,
        private string $keycloakRealm,
        private string $keycloakAdminUser,
        private string $keycloakAdminPassword
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing Keycloak users');

        // Get admin token
        $token = $this->getAdminToken();
        if (!$token) {
            $io->error('Failed to get admin token');
            return Command::FAILURE;
        }

        // Fetch users from Keycloak
        $users = $this->fetchUsers($token);
        $io->info(sprintf('Found %d users in Keycloak', count($users)));

        $created = 0;
        $updated = 0;

        foreach ($users as $user) {
            $userId = $user['id'];
            $email = $user['email'] ?? $user['username'];

            $existingUser = $this->userInfoRepository->find($userId);

            if ($existingUser) {
                $existingUser->setEmail($email);
                $existingUser->setFirstName($user['firstName'] ?? null);
                $existingUser->setLastName($user['lastName'] ?? null);
                $existingUser->setAvatarUrl($user['attributes']['avatarUrl'][0] ?? null);
                $updated++;
            } else {
                $userInfo = new UserInfo($userId, $email);
                $userInfo->setFirstName($user['firstName'] ?? null);
                $userInfo->setLastName($user['lastName'] ?? null);
                $userInfo->setAvatarUrl($user['attributes']['avatarUrl'][0] ?? null);
                $this->entityManager->persist($userInfo);
                $created++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created: %d, Updated: %d', $created, $updated));
        return Command::SUCCESS;
    }

    private function getAdminToken(): ?string
    {
        $response = $this->httpClient->request('POST', "{$this->keycloakUrl}/realms/master/protocol/openid-connect/token", [
            'body' => [
                'grant_type' => 'password',
                'client_id' => 'admin-cli',
                'username' => $this->keycloakAdminUser,
                'password' => $this->keycloakAdminPassword,
            ],
        ]);

        $data = $response->toArray(false);
        return $data['access_token'] ?? null;
    }

    private function fetchUsers(string $token): array
    {
        $response = $this->httpClient->request('GET', "{$this->keycloakUrl}/admin/realms/{$this->keycloakRealm}/users", [
            'headers' => ['Authorization' => "Bearer {$token}"],
            'query' => ['max' => 1000],
        ]);

        return $response->toArray();
    }
}
