<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class JwtAuthenticator extends AbstractAuthenticator
{
    private const JWKS_CACHE_KEY = 'keycloak_jwks';
    private const JWKS_CACHE_TTL = 3600;

    public function __construct(
        private LoggerInterface $logger,
        private JwtUserFactory $userFactory,
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private string $keycloakUrl = '',
        private string $keycloakRealm = ''
    ) {
        $this->keycloakUrl = getenv('KEYCLOAK_URL') ?: ($_ENV['KEYCLOAK_URL'] ?? '');
        $this->keycloakRealm = getenv('KEYCLOAK_REALM') ?: ($_ENV['KEYCLOAK_REALM'] ?? 'collector_realms');
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);
        $payload = $this->verifyAndDecodeToken($token);

        $this->logger->info('JWT Authentication', [
            'sub' => $payload['sub'],
            'username' => $payload['preferred_username'] ?? 'unknown',
            'email' => $payload['email'] ?? 'unknown',
        ]);

        return new SelfValidatingPassport(
            new UserBadge($payload['sub'], fn() => $this->userFactory->createFromTokenPayload($payload))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Authentication failed', [
            'message' => $exception->getMessage(),
        ]);

        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractToken(Request $request): string
    {
        $authHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Invalid Authorization header format');
        }

        return substr($authHeader, 7);
    }

    private function verifyAndDecodeToken(string $token): array
    {
        try {
            $keys = $this->getJwks();
            $this->logger->debug('JWT decode attempt', [
                'token_preview' => substr($token, 0, 50) . '...',
                'keys_count' => count($keys),
            ]);
            $decoded = JWT::decode($token, $keys);
            return json_decode(json_encode($decoded), true);
        } catch (\Exception $e) {
            $this->logger->error('JWT verification failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'token_preview' => substr($token, 0, 50) . '...',
            ]);
            throw new CustomUserMessageAuthenticationException('Invalid or expired token: ' . $e->getMessage());
        }
    }

    private function getJwks(): array
    {
        $jwksJson = $this->cache->get(self::JWKS_CACHE_KEY, function (ItemInterface $item): string {
            $item->expiresAfter(self::JWKS_CACHE_TTL);

            $jwksUrl = sprintf(
                '%s/realms/%s/protocol/openid-connect/certs',
                rtrim($this->keycloakUrl, '/'),
                $this->keycloakRealm
            );

            $this->logger->info('Fetching JWKS from Keycloak', ['url' => $jwksUrl]);

            try {
                $response = $this->httpClient->request('GET', $jwksUrl);
                return $response->getContent();
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch JWKS', ['error' => $e->getMessage()]);
                throw new CustomUserMessageAuthenticationException('Unable to verify token: authentication service unavailable');
            }
        });

        return JWK::parseKeySet(json_decode($jwksJson, true));
    }
}
