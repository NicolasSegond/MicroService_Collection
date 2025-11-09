<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JwtUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \LogicException('JwtUser is loaded from token in JwtAuthenticator');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof JwtUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return JwtUser::class === $class || is_subclass_of($class, JwtUser::class);
    }
}
