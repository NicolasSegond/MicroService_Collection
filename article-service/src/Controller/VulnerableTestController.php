<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * ⚠️ VULNERABLE CONTROLLER - FOR SECURITY TESTING ONLY
 * This controller contains intentional security vulnerabilities
 * to test ZAP security scanning. DO NOT USE IN PRODUCTION!
 */
class VulnerableTestController extends AbstractController
{
    /**
     * Vulnerability 1: XSS - Reflects user input without sanitization
     */
    #[Route('/api/test/xss', name: 'test_xss', methods: ['GET'])]
    public function xssVulnerable(Request $request): Response
    {
        $name = $request->query->get('name', 'Guest');

        // VULNERABLE: User input directly in response without escaping
        $html = "<html><body><h1>Hello, $name!</h1></body></html>";

        return new Response($html, 200, [
            'Content-Type' => 'text/html',
            // Missing security headers intentionally
        ]);
    }

    /**
     * Vulnerability 2: Information Disclosure - Exposes sensitive info
     */
    #[Route('/api/test/debug', name: 'test_debug', methods: ['GET'])]
    public function debugInfo(Request $request): Response
    {
        // VULNERABLE: Exposing server information
        $debug = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'database_url' => $_ENV['DATABASE_URL'] ?? 'not set',
            'app_secret' => substr($_ENV['APP_SECRET'] ?? 'none', 0, 10) . '...',
            'environment' => $_ENV['APP_ENV'] ?? 'unknown',
        ];

        return $this->json($debug, 200, [
            // VULNERABLE: Missing security headers
            'X-Debug-Token' => 'exposed-token-12345',
        ]);
    }

    /**
     * Vulnerability 3: SQL Injection simulation (returns query for demo)
     */
    #[Route('/api/test/search', name: 'test_search', methods: ['GET'])]
    public function searchVulnerable(Request $request): Response
    {
        $query = $request->query->get('q', '');

        // VULNERABLE: Simulating SQL injection vulnerability
        // In real scenario this would be: "SELECT * FROM users WHERE name = '$query'"
        $simulatedQuery = "SELECT * FROM articles WHERE title LIKE '%$query%'";

        return $this->json([
            'message' => 'Search results',
            'query_executed' => $simulatedQuery, // VULNERABLE: Exposing query
            'results' => [],
            'user_input' => $query, // VULNERABLE: Reflecting input
        ], 200, [
            // Missing Content-Security-Policy
        ]);
    }

    /**
     * Vulnerability 4: Open Redirect
     */
    #[Route('/api/test/redirect', name: 'test_redirect', methods: ['GET'])]
    public function openRedirect(Request $request): Response
    {
        $url = $request->query->get('url', '/');

        // VULNERABLE: Open redirect - no validation of target URL
        return $this->redirect($url);
    }

    /**
     * Vulnerability 5: Missing security headers
     */
    #[Route('/api/test/insecure', name: 'test_insecure', methods: ['GET'])]
    public function insecureHeaders(): Response
    {
        return new Response(
            json_encode(['status' => 'ok', 'message' => 'This endpoint has no security headers']),
            200,
            [
                'Content-Type' => 'application/json',
                // Intentionally missing:
                // - X-Content-Type-Options
                // - X-Frame-Options
                // - X-XSS-Protection
                // - Content-Security-Policy
                // - Strict-Transport-Security
            ]
        );
    }

    /**
     * Vulnerability 6: Sensitive data in error messages
     */
    #[Route('/api/test/error', name: 'test_error', methods: ['GET'])]
    public function sensitiveError(Request $request): Response
    {
        $id = $request->query->get('id');

        if (!$id) {
            // VULNERABLE: Detailed error message with stack trace info
            return $this->json([
                'error' => true,
                'message' => 'Missing required parameter: id',
                'debug' => [
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                ],
                'suggestion' => 'Try: /api/test/error?id=1',
            ], 400);
        }

        return $this->json(['id' => $id, 'status' => 'found']);
    }
}

