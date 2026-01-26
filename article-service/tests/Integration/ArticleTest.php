<?php

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface; // [Ajout]
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
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $testId = uniqid('test_');

        $published = new Article();
        $published->setTitle($testId . '_published');
        $published->setPrice(100);
        $published->setMainPhotoUrl('/img/pub.jpg');
        $published->setOwnerId('user1');
        $published->setStatus('PUBLISHED');
        $entityManager->persist($published);

        $draft = new Article();
        $draft->setTitle($testId . '_draft');
        $draft->setPrice(50);
        $draft->setMainPhotoUrl('/img/draft.jpg');
        $draft->setOwnerId('user1');
        $draft->setStatus('DRAFT');
        $entityManager->persist($draft);

        $entityManager->flush();

        // Filter by test ID to isolate test data
        $response = $client->request('GET', '/api/articles?title=' . $testId);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $titles = array_column($data['member'] ?? [], 'title');

        $this->assertContains($published->getTitle(), $titles, 'Les articles PUBLISHED doivent être visibles.');
        $this->assertNotContains($draft->getTitle(), $titles, 'Les articles DRAFT ne doivent PAS être visibles sur l\'endpoint public.');
    }

    public function testAdminCollectionIncludesDrafts(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        $testId = uniqid('test_');

        $draft = new Article();
        $draft->setTitle($testId . '_draft');
        $draft->setPrice(50);
        $draft->setMainPhotoUrl('/img/admin-draft.jpg');
        $draft->setOwnerId('admin');
        $draft->setStatus('DRAFT');
        $entityManager->persist($draft);
        $entityManager->flush();

        // Filter by test ID to isolate test data
        $response = $client->request('GET', '/api/admin/articles?title=' . $testId);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $titles = array_column($data['member'] ?? [], 'title');

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
