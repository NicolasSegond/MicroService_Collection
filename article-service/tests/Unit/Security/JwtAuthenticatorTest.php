<?php

namespace App\Tests\Unit\Security;

use App\Security\JwtAuthenticator;
use App\Security\JwtUserFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JwtAuthenticatorTest extends TestCase
{
    private LoggerInterface $logger;
    private JwtUserFactory $userFactory;
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->userFactory = $this->createStub(JwtUserFactory::class);
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->cache = $this->createStub(CacheInterface::class);
    }

    private function createAuthenticator(): JwtAuthenticator
    {
        return new JwtAuthenticator(
            $this->logger,
            $this->userFactory,
            $this->httpClient,
            $this->cache
        );
    }

    public function testSupportsReturnsTrueWithBearerToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer some-token');

        $authenticator = $this->createAuthenticator();

        $this->assertTrue($authenticator->supports($request));
    }

    public function testSupportsReturnsFalseWithoutAuthorizationHeader(): void
    {
        $request = new Request();

        $authenticator = $this->createAuthenticator();

        $this->assertFalse($authenticator->supports($request));
    }

    public function testSupportsReturnsFalseWithNonBearerToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Basic user:pass');

        $authenticator = $this->createAuthenticator();

        $this->assertFalse($authenticator->supports($request));
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        $authenticator = $this->createAuthenticator();

        $this->assertNull($authenticator->onAuthenticationSuccess($request, $token, 'api'));
    }

    public function testOnAuthenticationFailureReturns401(): void
    {
        $request = new Request();
        $exception = new CustomUserMessageAuthenticationException('Token expired');

        $authenticator = $this->createAuthenticator();
        $response = $authenticator->onAuthenticationFailure($request, $exception);

        $this->assertEquals(401, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Authentication failed', $content['error']);
        $this->assertEquals('Token expired', $content['message']);
    }

    public function testAuthenticateThrowsExceptionWhenJwksFetchFails(): void
    {
        $this->cache->method('get')->willReturnCallback(function ($key, $callback) {
            return $callback($this->createStub(\Symfony\Contracts\Cache\ItemInterface::class));
        });

        $this->httpClient->method('request')->willThrowException(new \Exception('Connection refused'));

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer fake.jwt.token');

        $authenticator = $this->createAuthenticator();

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('authentication service unavailable');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsExceptionWithInvalidToken(): void
    {
        $jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'n' => 'test',
                'e' => 'AQAB'
            ]]
        ];

        $this->cache->method('get')->willReturn(json_encode($jwks));

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer invalid.jwt.token');

        $authenticator = $this->createAuthenticator();

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $authenticator->authenticate($request);
    }
}
