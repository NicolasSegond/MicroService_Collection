<?php

namespace App\Security;

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
use Psr\Log\LoggerInterface;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-Access-Token')
            || $request->headers->has('X-Userinfo')
            || $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $userInfoHeader = $request->headers->get('X-Userinfo');
        if ($userInfoHeader) {
            try {
                $userInfo = json_decode(base64_decode($userInfoHeader), true);

                if ($userInfo && isset($userInfo['sub'])) {
                    $this->logger->info('Authentication via X-Userinfo', [
                        'sub' => $userInfo['sub'],
                        'username' => $userInfo['preferred_username'] ?? 'unknown'
                    ]);

                    return new SelfValidatingPassport(
                        new UserBadge($userInfo['sub'], function ($userIdentifier) use ($userInfo) {
                            return $this->loadUserFromKeycloakData($userInfo);
                        })
                    );
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to decode X-Userinfo', ['error' => $e->getMessage()]);
            }
        }

        $accessToken = $request->headers->get('X-Access-Token');
        if (!$accessToken) {
            // Fallback: Authorization Bearer
            $authHeader = $request->headers->get('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $accessToken = substr($authHeader, 7);
            }
        }

        if (!$accessToken) {
            throw new CustomUserMessageAuthenticationException('No authentication token found');
        }

        try {
            $tokenParts = explode('.', $accessToken);
            if (count($tokenParts) !== 3) {
                throw new \Exception('Invalid JWT format');
            }

            $payload = json_decode(base64_decode($tokenParts[1]), true);

            if (!$payload || !isset($payload['sub'])) {
                throw new \Exception('Invalid JWT payload');
            }

            $this->logger->info('Authentication via JWT token', [
                'sub' => $payload['sub'],
                'username' => $payload['preferred_username'] ?? 'unknown'
            ]);

            return new SelfValidatingPassport(
                new UserBadge($payload['sub'], function ($userIdentifier) use ($payload) {
                    return $this->loadUserFromKeycloakData($payload);
                })
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to decode JWT', ['error' => $e->getMessage()]);
            throw new CustomUserMessageAuthenticationException('Invalid token');
        }
    }

    private function loadUserFromKeycloakData(array $tokenData): JwtUser
    {
        $roles = ['ROLE_USER']; // Rôle par défaut

        if (isset($tokenData['realm_access']['roles'])) {
            foreach ($tokenData['realm_access']['roles'] as $role) {
                $roles[] = 'ROLE_' . strtoupper(str_replace('-', '_', $role));
            }
        }

        if (isset($tokenData['resource_access'])) {
            foreach ($tokenData['resource_access'] as $client => $clientData) {
                if (isset($clientData['roles'])) {
                    foreach ($clientData['roles'] as $role) {
                        $roles[] = 'ROLE_' . strtoupper(str_replace('-', '_', $role));
                    }
                }
            }
        }

        if (isset($tokenData['groups'])) {
            foreach ($tokenData['groups'] as $group) {
                $groupName = trim($group, '/');
                $roles[] = 'ROLE_' . strtoupper(str_replace('-', '_', $groupName));
            }
        }

        $roles = array_unique($roles);

        return new JwtUser(
            id: $tokenData['sub'],
            username: $tokenData['preferred_username'] ?? $tokenData['sub'],
            email: $tokenData['email'] ?? null,
            firstName: $tokenData['given_name'] ?? null,
            lastName: $tokenData['family_name'] ?? null,
            roles: $roles,
            tokenData: $tokenData
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
            'headers' => [
                'X-Access-Token' => $request->headers->get('X-Access-Token') ? 'present' : 'missing',
                'X-Userinfo' => $request->headers->get('X-Userinfo') ? 'present' : 'missing',
                'Authorization' => $request->headers->get('Authorization') ? 'present' : 'missing',
            ]
        ]);

        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}
