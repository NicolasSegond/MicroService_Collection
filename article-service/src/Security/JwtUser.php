<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class JwtUser implements UserInterface
{
    public function __construct(
        private string $id,
        private string $username,
        private ?string $email = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
        private array $roles = ['ROLE_USER'],
        private array $tokenData = []
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function getTokenData(): array
    {
        return $this->tokenData;
    }

    public function eraseCredentials(): void
    {
        // Rien à effacer, on ne stocke pas de mot de passe
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'roles' => $this->roles,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->email = $data['email'] ?? null;
        $this->firstName = $data['firstName'] ?? null;
        $this->lastName = $data['lastName'] ?? null;
        $this->roles = $data['roles'];
    }
}
