<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;

#[AsController]
class HelloWorldController
{
    public function __construct(private Security $security) {}

    public function __invoke(): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_USER')) {
            throw new AccessDeniedException('Vous devez être connecté avec le rôle USER.');
        }

        return new JsonResponse(['message' => 'hello-world']);
    }
}
