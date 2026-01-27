<?php

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Article;
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

    public function testAdminCollectionIncludesDrafts(): void
    {
        $draft = new Article();
        $draft->setTitle('Admin Visible Draft ' . uniqid());
        $draft->setPrice(50);
        $draft->setMainPhotoUrl('/img/admin-draft.jpg');
        $draft->setOwnerId('admin');
        $draft->setStatus('DRAFT');
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        $client = static::createClient();

        // Chercher l'article DRAFT par son titre sur l'endpoint admin
        $response = $client->request('GET', '/api/admin/articles?title=' . urlencode($draft->getTitle()));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $titles = array_column($data['member'] ?? $data['hydra:member'] ?? [], 'title');

        $this->assertContains($draft->getTitle(), $titles, 'Les brouillons DOIVENT être visibles sur l\'endpoint admin.');
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
}
