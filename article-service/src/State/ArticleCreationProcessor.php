<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class ArticleCreationProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security           $security,
        private RateLimiterFactory $apiCreateArticleLimiter,
        private RequestStack       $requestStack,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $limiter = $this->apiCreateArticleLimiter->create($request->getClientIp());
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(
                    $limit->getRetryAfter()->getTimestamp() - time(),
                    'Trop de créations d\'articles. Réessayez plus tard.'
                );
            }
        }

        if ($data instanceof Article && $this->security->getUser()) {
            $data->setOwnerId($this->security->getUser()->getUserIdentifier());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
