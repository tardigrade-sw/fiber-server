<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Session;

use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;

/**
 * Manages the lifecycle of a session within the FiberServer request cycle.
 * Provides isolation to prevent leakage between concurrent fibers.
 */
class SessionManager {

    private static ?FiberSessionHandler $handler = null;

    public static function register(int $maxLifetime = 3600): void {
        if (self::$handler !== null) return;
        
        self::$handler = new FiberSessionHandler($maxLifetime);
        session_set_save_handler(self::$handler, true);

        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.cache_limiter', '');
    }

    /**
     * Prepares the session for a new request.
     * Extracts session ID and populates $_SESSION.
     */
    public static function startRequest(Request $request): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $sid = $request->cookies->get('PHPSESSID', '');
        
        if ($sid !== '') {
            session_id($sid);
        } else {
            // Generate a new session ID if none exists
            $sid = bin2hex(random_bytes(16));
            session_id($sid);
        }

        if (headers_sent()) {
            return;
        }

        // Start native session to populate $_SESSION
        // We set options globally in register() to avoid the error when headers are sent
        // but we still wrap it in a try-catch for extra safety.
        try {
            session_start();
        } catch (\Throwable $e) {
            // Log it but don't crash the server
            \fwrite(STDERR, "Could not start session: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Persists the session and clears global state to prevent leakage.
     */
    public static function endRequest(Response $response): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sid = (string)session_id();
            session_write_close();
            
            $_SESSION = [];
            
            $existingCookies = $response->getHeader('Set-Cookie', []);
            if (!\is_array($existingCookies)) {
                $existingCookies = [$existingCookies];
            }

            foreach ($existingCookies as $cookie) {
                if (\str_starts_with((string)$cookie, 'PHPSESSID=')) {
                    return; // Symfony already handled this
                }
            }

            $response->setHeader('Set-Cookie', \array_merge($existingCookies, ["PHPSESSID=$sid; Path=/; HttpOnly; SameSite=Lax"]));
        }
    }
}
