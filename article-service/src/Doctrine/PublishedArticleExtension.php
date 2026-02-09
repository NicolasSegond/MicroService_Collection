<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Entity\Article;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class PublishedArticleExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security
    ) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWherePublished($queryBuilder, $resourceClass, $operation);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhereForItem($queryBuilder, $resourceClass, $operation);
    }

    private function addWherePublished(QueryBuilder $queryBuilder, string $resourceClass, ?Operation $operation): void
    {
        if ($resourceClass !== Article::class) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if ($operation && str_contains($operation->getUriTemplate() ?? '', '/admin/')) {
            $queryBuilder
                ->andWhere(sprintf('%s.status = :draft', $rootAlias))
                ->setParameter('draft', 'DRAFT');
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.status = :published', $rootAlias))
            ->setParameter('published', 'PUBLISHED');
    }

    private function addWhereForItem(QueryBuilder $queryBuilder, string $resourceClass, ?Operation $operation): void
    {
        if ($resourceClass !== Article::class) {
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($operation instanceof Patch || $operation instanceof Put || $operation instanceof Delete) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $user = $this->security->getUser();

        if ($user) {
            $queryBuilder
                ->andWhere(sprintf('%s.status = :published OR %s.ownerId = :currentUserId', $rootAlias, $rootAlias))
                ->setParameter('published', 'PUBLISHED')
                ->setParameter('currentUserId', $user->getUserIdentifier());
        } else {
            $queryBuilder
                ->andWhere(sprintf('%s.status = :published', $rootAlias))
                ->setParameter('published', 'PUBLISHED');
        }
    }
}
