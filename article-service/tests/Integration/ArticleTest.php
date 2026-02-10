<?php

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Article;
use App\Entity\UserInfo;
use App\Security\JwtUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ArticleTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
    }

    private function createTestUser(string $id = 'test-user-001'): JwtUser
    {
        return new JwtUser(
            id: $id,
            username: 'testuser',
            email: 'test@example.com',
            roles: ['ROLE_USER']
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testGetCollection(): void
    {
        static::createClient()->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/api/contexts/Article',
            '@id' => '/api/articles',
            '@type' => 'Collection',
        ]);

        $this->assertMatchesResourceCollectionJsonSchema(Article::class);
    }

    public function testPublicCollectionExcludesDrafts(): void
    {
        $published = new Article();
        $published->setTitle('Public Content ' . uniqid());
        $published->setPrice(100);
        $published->setMainPhotoUrl('/img/pub.jpg');
        $published->setOwnerId('user1');
        $published->setStatus('PUBLISHED');
        $this->entityManager->persist($published);

        $draft = new Article();
        $draft->setTitle('Draft Secret ' . uniqid());
        $draft->setPrice(50);
        $draft->setMainPhotoUrl('/img/draft.jpg');
        $draft->setOwnerId('user1');
        $draft->setStatus('DRAFT');
        $this->entityManager->persist($draft);

        $this->entityManager->flush();

        $client = static::createClient();

        // Chercher l'article PUBLISHED par son titre (évite les problèmes de pagination)
        $response = $client->request('GET', '/api/articles?title=' . urlencode($published->getTitle()));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $titles = array_column($data['member'] ?? $data['hydra:member'] ?? [], 'title');
        $this->assertContains($published->getTitle(), $titles, 'Les articles PUBLISHED doivent être visibles.');

        // Chercher l'article DRAFT - il ne doit PAS apparaître
        $response = $client->request('GET', '/api/articles?title=' . urlencode($draft->getTitle()));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $titles = array_column($data['member'] ?? $data['hydra:member'] ?? [], 'title');
        $this->assertNotContains($draft->getTitle(), $titles, 'Les articles DRAFT ne doivent PAS être visibles sur l\'endpoint public.');
    }

    public function testAdminCollectionOnlyShowsDrafts(): void
    {
        $uniqueId = uniqid();

        $draft = new Article();
        $draft->setTitle('Admin Draft ' . $uniqueId);
        $draft->setPrice(50);
        $draft->setMainPhotoUrl('/img/admin-draft.jpg');
        $draft->setOwnerId('admin');
        $draft->setStatus('DRAFT');
        $this->entityManager->persist($draft);

        $published = new Article();
        $published->setTitle('Admin Published ' . $uniqueId);
        $published->setPrice(100);
        $published->setMainPhotoUrl('/img/admin-published.jpg');
        $published->setOwnerId('admin');
        $published->setStatus('PUBLISHED');
        $this->entityManager->persist($published);

        $this->entityManager->flush();

        $client = static::createClient();

        $response = $client->request('GET', '/api/admin/articles?title=' . urlencode('Admin Draft ' . $uniqueId));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $titles = array_column($data['member'] ?? $data['hydra:member'] ?? [], 'title');
        $this->assertContains($draft->getTitle(), $titles);

        $response = $client->request('GET', '/api/admin/articles?title=' . urlencode('Admin Published ' . $uniqueId));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $titles = array_column($data['member'] ?? $data['hydra:member'] ?? [], 'title');
        $this->assertNotContains($published->getTitle(), $titles);
    }

    public function testCreateArticleAsAuthenticatedUser(): void
    {
        $userId = 'test-user-001';
        $user = $this->createTestUser($userId);

        $client = static::createClient();
        $client->loginUser($user, 'api');

        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'Article Test Intégration',
                'description' => 'Description créée lors du test manuel',
                'price' => 49.99,
                'mainPhotoUrl' => '/uploads/test_manual.jpg'
            ]
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@type' => 'Article',
            'title' => 'Article Test Intégration',
            'ownerId' => $userId,
            'status' => 'DRAFT'
        ]);
    }

    public function testCreateArticleWithoutAuthenticationReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'Article Non Autorisé',
                'price' => 29.99,
                'mainPhotoUrl' => '/uploads/unauthorized.jpg'
            ]
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetSingleArticle(): void
    {
        $article = new Article();
        $article->setTitle('Article Single Test ' . uniqid());
        $article->setPrice(75.00);
        $article->setMainPhotoUrl('/img/single.jpg');
        $article->setOwnerId('user-single');
        $article->setStatus('PUBLISHED');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $response = $client->request('GET', '/api/articles/' . $article->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Article',
            'title' => $article->getTitle(),
            'price' => 75,
            'ownerId' => 'user-single'
        ]);
    }

    public function testGetArticleWithOwnerInfo(): void
    {
        $ownerId = 'owner-' . uniqid();
        $userInfo = new UserInfo($ownerId, 'owner@example.com');
        $userInfo->setFirstName('John');
        $userInfo->setLastName('Doe');
        $userInfo->setAvatarUrl('https://example.com/avatar.jpg');
        $this->entityManager->persist($userInfo);

        $article = new Article();
        $article->setTitle('Article With Owner ' . uniqid());
        $article->setPrice(100);
        $article->setMainPhotoUrl('/img/with-owner.jpg');
        $article->setOwnerId($ownerId);
        $article->setStatus('PUBLISHED');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->request('GET', '/api/articles/' . $article->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'ownerId' => $ownerId,
            'owner' => [
                'id' => $ownerId,
                'email' => 'owner@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'fullName' => 'John Doe',
                'avatarUrl' => 'https://example.com/avatar.jpg'
            ]
        ]);
    }

    public function testCollectionContainsOwnerInfo(): void
    {
        $userInfo = new UserInfo('owner-collection-' . uniqid(), 'collection@example.com');
        $userInfo->setFirstName('Jane');
        $userInfo->setLastName('Smith');
        $this->entityManager->persist($userInfo);

        $article = new Article();
        $article->setTitle('Article Collection Owner ' . uniqid());
        $article->setPrice(50.00);
        $article->setMainPhotoUrl('/img/collection-owner.jpg');
        $article->setOwnerId($userInfo->getId());
        $article->setStatus('PUBLISHED');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $response = $client->request('GET', '/api/articles?title=' . urlencode($article->getTitle()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $members = $data['member'] ?? $data['hydra:member'] ?? [];

        $this->assertNotEmpty($members);
        $foundArticle = $members[0];
        $this->assertEquals($userInfo->getId(), $foundArticle['ownerId']);
        $this->assertArrayHasKey('owner', $foundArticle);
        $this->assertEquals('Jane', $foundArticle['owner']['firstName']);
        $this->assertEquals('Smith', $foundArticle['owner']['lastName']);
    }

    public function testPatchArticleAsOwner(): void
    {
        $userId = 'patch-owner-' . uniqid();
        $user = $this->createTestUser($userId);

        $article = new Article();
        $article->setTitle('Article To Patch');
        $article->setPrice(100.00);
        $article->setMainPhotoUrl('/img/to-patch.jpg');
        $article->setOwnerId($userId);
        $article->setStatus('DRAFT');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->loginUser($user, 'api');

        $client->request('PATCH', '/api/articles/' . $article->getId(), [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json'
            ],
            'json' => [
                'title' => 'Article Patched',
                'price' => 150.00
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'title' => 'Article Patched',
            'price' => 150
        ]);
    }

    public function testGetDraftArticleAsNonOwnerReturns404(): void
    {
        $article = new Article();
        $article->setTitle('Draft Not Visible ' . uniqid());
        $article->setPrice(100);
        $article->setMainPhotoUrl('/img/draft-hidden.jpg');
        $article->setOwnerId('some-other-user');
        $article->setStatus('DRAFT');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->request('GET', '/api/articles/' . $article->getId());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetDraftArticleAsOtherUserReturns404(): void
    {
        $otherUser = $this->createTestUser('other-user-' . uniqid());

        $article = new Article();
        $article->setTitle('Someone Else Draft ' . uniqid());
        $article->setPrice(100);
        $article->setMainPhotoUrl('/img/other-draft.jpg');
        $article->setOwnerId('original-owner');
        $article->setStatus('DRAFT');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->loginUser($otherUser, 'api');
        $client->request('GET', '/api/articles/' . $article->getId());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetDraftArticleAsOwnerIsAccessible(): void
    {
        $userId = 'draft-owner-' . uniqid();
        $user = $this->createTestUser($userId);

        $article = new Article();
        $article->setTitle('My Draft ' . uniqid());
        $article->setPrice(100);
        $article->setMainPhotoUrl('/img/my-draft.jpg');
        $article->setOwnerId($userId);
        $article->setStatus('DRAFT');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->loginUser($user, 'api');
        $client->request('GET', '/api/articles/' . $article->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'status' => 'DRAFT',
            'ownerId' => $userId
        ]);
    }
}
