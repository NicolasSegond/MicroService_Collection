<?php

    namespace App\Controller;

    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpKernel\Attribute\AsController;

    #[AsController]
    class HelloWorldController
    {
        public function __invoke(): JsonResponse
        {
            return new JsonResponse(['message' => 'hello-world']);
        }
    }