<?php

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Article;
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

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testCreateArticleAsAuthenticatedUser(): void
    {
        $client = static::createClient();

        $userId = 'test-user-001';
        $token = $this->createMockJwt($userId);

        $client->request('POST', '/api/articles', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/ld+json'
            ],
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

    private function createMockJwt(string $userId, string $email = 'test@example.com'): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => $userId,
            'email' => $email,
            'preferred_username' => 'Test User'
        ]));
        $signature = 'fake_signature';

        return sprintf('%s.%s.%s', $header, $payload, $signature);
    }
}
