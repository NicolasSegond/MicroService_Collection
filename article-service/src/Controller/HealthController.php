<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        $checks = ['status' => 'ok'];

        try {
            $connection->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'ko';
            $checks['status'] = 'degraded';

            return new JsonResponse($checks, 503);
        }

        return new JsonResponse($checks);
    }
}
