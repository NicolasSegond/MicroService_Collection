<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use App\Entity\UserInfo;
use App\Repository\UserInfoRepository;
use App\State\ArticleWithOwnerProvider;
use PHPUnit\Framework\TestCase;

class ArticleWithOwnerProviderTest extends TestCase
{
    private $userInfoRepository;

    protected function setUp(): void
    {
        $this->userInfoRepository = $this->createMock(UserInfoRepository::class);
    }

    public function testProvideCollectionEnrichesArticlesWithOwners(): void
    {
        $collectionProvider = $this->createMock(ProviderInterface::class);
        $itemProvider = $this->createStub(ProviderInterface::class);

        $provider = new ArticleWithOwnerProvider(
            $collectionProvider,
            $itemProvider,
            $this->userInfoRepository
        );

        $article1 = new Article();
        $article1->setOwnerId('user-123');
        $article2 = new Article();
        $article2->setOwnerId('user-456');
        $articles = [$article1, $article2];

        $user1 = new UserInfo('user-123', 'test1@example.com');
        $user1->setFirstName('John');

        $user2 = new UserInfo('user-456', 'test2@example.com');
        $user2->setFirstName('Jane');

        $operation = new GetCollection();

        $collectionProvider->expects($this->once())
            ->method('provide')
            ->willReturn($articles);

        $this->userInfoRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => ['user-123', 'user-456']])
            ->willReturn([$user1, $user2]);

        $result = $provider->provide($operation);

        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]->getOwner()['firstName']);
    }

    public function testProvideItemEnrichesSingleArticle(): void
    {
        $collectionProvider = $this->createStub(ProviderInterface::class);
        $itemProvider = $this->createMock(ProviderInterface::class);

        $provider = new ArticleWithOwnerProvider(
            $collectionProvider,
            $itemProvider,
            $this->userInfoRepository
        );

        $article = new Article();
        $article->setOwnerId('user-123');

        $user = new UserInfo('user-123', 'alice@example.com');
        $user->setFirstName('Alice');

        $operation = $this->createStub(Operation::class);

        $itemProvider->expects($this->once())
            ->method('provide')
            ->willReturn($article);

        $this->userInfoRepository->expects($this->once())
            ->method('find')
            ->with('user-123')
            ->willReturn($user);

        $result = $provider->provide($operation, [], []);

        $this->assertInstanceOf(Article::class, $result);
        $this->assertEquals('Alice', $result->getOwner()['firstName']);
    }
}
