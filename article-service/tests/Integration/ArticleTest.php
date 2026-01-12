<?php

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Article;

class ArticleTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testGetCollection(): void
    {
        // Teste la récupération publique des articles
        static::createClient()->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/api/contexts/Article',
            '@id' => '/api/articles',
            '@type' => 'Collection', // <--- C'est ici que ça change (avant: hydra:Collection)
        ]);

        $this->assertMatchesResourceCollectionJsonSchema(Article::class);
    }

    public function testCreateArticleAsAuthenticatedUser(): void
    {
        $client = static::createClient();

        // Cet ID existe dans tes UserInfoFixtures (test-user-001)
        $userId = 'test-user-001';
        $token = $this->createMockJwt($userId);

        // Teste la création avec authentification
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

        // Vérifie que le processeur a bien lié l'article au user du token
        $this->assertJsonContains([
            '@type' => 'Article',
            'title' => 'Article Test Intégration',
            'ownerId' => $userId,
            'status' => 'PUBLISHED'
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
