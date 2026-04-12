<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class RateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $apiGlobalLimiter,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $limiter = $this->apiGlobalLimiter->create($request->getClientIp());
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            $response = new JsonResponse(
                ['error' => 'Trop de requêtes. Réessayez plus tard.'],
                429
            );
            $response->headers->set('Retry-After', $retryAfter->getTimestamp() - time());
            $response->headers->set('X-RateLimit-Limit', $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', $limit->getRemainingTokens());

            $event->setResponse($response);
        }
    }
}
