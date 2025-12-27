<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\Entity\Article;
use App\Entity\UserInfo;
use App\Repository\UserInfoRepository;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;

class ArticleWithOwnerProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $collectionProvider,
        private ItemProvider $itemProvider,
        private UserInfoRepository $userInfoRepository
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $articles = $this->collectionProvider->provide($operation, $uriVariables, $context);
            return $this->enrichArticles($articles);
        }

        $article = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($article instanceof Article) {
            $this->enrichArticle($article);
        }

        return $article;
    }

    private function enrichArticles(iterable $articles): iterable
    {
        $ownerIds = [];
        $articleList = [];

        foreach ($articles as $article) {
            $articleList[] = $article;
            if ($article instanceof Article && $article->getOwnerId()) {
                $ownerIds[] = $article->getOwnerId();
            }
        }

        if (!empty($ownerIds)) {
            $users = $this->userInfoRepository->findBy(['id' => array_unique($ownerIds)]);
            $usersMap = [];
            foreach ($users as $user) {
                $usersMap[$user->getId()] = $this->formatUserInfo($user);
            }

            foreach ($articleList as $article) {
                if ($article instanceof Article) {
                    $ownerId = $article->getOwnerId();
                    if ($ownerId && isset($usersMap[$ownerId])) {
                        $article->setOwner($usersMap[$ownerId]);
                    }
                }
            }
        }

        if ($articles instanceof PaginatorInterface) {
            return new TraversablePaginator(
                new \ArrayIterator($articleList),
                $articles->getCurrentPage(),
                $articles->getItemsPerPage(),
                $articles->getTotalItems()
            );
        }

        return $articleList;
    }

    private function enrichArticle(Article $article): void
    {
        $ownerId = $article->getOwnerId();
        if (!$ownerId) {
            return;
        }

        $userInfo = $this->userInfoRepository->find($ownerId);
        if ($userInfo) {
            $article->setOwner($this->formatUserInfo($userInfo));
        }
    }

    private function formatUserInfo(UserInfo $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'avatarUrl' => $user->getAvatarUrl(),
        ];
    }
}
