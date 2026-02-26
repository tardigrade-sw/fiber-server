<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Parsers;

use Closure;
use RuntimeException;
use Tg\FiberServer\Component\HttpCore\Params;
use Tg\FiberServer\Component\HttpCore\Request;

class Http1Parser {
    private const STATE_HEADERS = 0;
    private const STATE_BODY = 1;
    private const STATE_CHUNKED = 2;
    private const STATE_COMPLETE = 3;

    private int $state = self::STATE_HEADERS;
    private string $buffer = '';
    private int $contentLength = 0;
    private string $clientIp = '';

    private static ?Closure $hydrator = null;

    public function __construct()
    {
        self::$hydrator ??= Closure::bind(function(Request $request, array $data) {
            foreach($data as $prop => $value) {
                $request->$prop = $value;
            }
        }, null, Request::class);
    }

    public function setClientIp(string $ip): void {
        $this->clientIp = $ip;
    }

    public function parse(string $chunk, Request $request): bool {
        $this->buffer .= $chunk;

        if ($this->state === self::STATE_HEADERS) {
            if (($pos = strpos($this->buffer, "\r\n\r\n")) !== false) {
                $headerBlock = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos + 4);
                $this->hydrateHeaders($headerBlock, $request);
                $this->determineNextState($request);
            }
        }

        if ($this->state === self::STATE_BODY) {
            if (strlen($this->buffer) >= $this->contentLength) {
                (self::$hydrator)($request, [
                    'body' => substr($this->buffer, 0, $this->contentLength)
                ]);

                $this->hydrateBody($request);
                $this->buffer = substr($this->buffer, $this->contentLength);
                $this->state = self::STATE_COMPLETE;
            }
        }

        if ($this->state === self::STATE_CHUNKED) {

            throw new RuntimeException("Unimplemented chunked requests");
            // @todo: Implement chunked decoding logic here
            // Look for hex length\r\n...0\r\n\r\n
        }

        return $this->state === self::STATE_COMPLETE;
    }

    private function hydrateHeaders(string $headerBlock, Request $request): void {
        $lines = explode("\r\n", $headerBlock);
        $firstLine = array_shift($lines);
        [$method, $uri, $version] = explode(' ', $firstLine, 3);

        (self::$hydrator)($request, [
            'method' => $method,
            'uri' => $uri,
            'version' => $version
        ]);

        $this->buildUri($request, $uri);

        $request->server->put('REQUEST_METHOD', $method);
        $request->server->put('REQUEST_URI', $uri);
        $request->server->put('SERVER_PROTOCOL', $version);
        $request->server->put('REMOTE_ADDR', $this->clientIp);

        foreach ($lines as $line) {
            if (empty($line)) continue;
            [$key, $value] = explode(':', $line, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            $request->headers->put($key, $value);

            $serverKey = 'HTTP_' . \str_replace('-', '_', \strtoupper($key));
            
            if (\in_array($serverKey, ['HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH'])) {
                $serverKey = \substr($serverKey, 5);
            }
            
            $request->server->put($serverKey, $value);
        }

        $this->buildQuery($request);
        $request->server->put('QUERY_STRING', (string)$request->attributes->get('query', ''));
        $this->parseCookies($request);
    }

    private function hydrateBody(Request $request): void {
        $this->buildPostParams($request);
        $this->parseMultipart($request);
    }

    private function determineNextState(Request $request): void {
        if ($request->headers->has('transfer-encoding') && $request->headers->get('transfer-encoding') === 'chunked') {
            $this->state = self::STATE_CHUNKED;
        } elseif ($request->headers->has('content-length')) {
            $this->contentLength = (int)$request->headers->get('content-length');
            $this->state = ($this->contentLength === 0) ? self::STATE_COMPLETE : self::STATE_BODY;
        } else {
            $this->state = self::STATE_COMPLETE;
        }
    }

    public function reset(): void {
        $this->state = self::STATE_HEADERS;
        $this->contentLength = 0;
    }

    public function getBuffer(): string {
        return $this->buffer;
    }

    public function buildUri(Request $request, string $uri): void {
        $components = \parse_url($uri);

        foreach($components as $key => $value) {
            $request->attributes->put($key, $value);
        }
    }

    public function buildQuery(Request $request): void {
        $query = $request->attributes->get('query', null);
        if($query) {
            \parse_str((string)$query,  $params);
            foreach($params ?? [] as $key => $value) {
                $request->query->put($key, $value);
            }
        }
    }

    private function parseCookies(Request $request): void {
        $cookieHeader = $request->headers->get('Cookie', '');

        if (empty($cookieHeader)) {
            return;
        }

        $pairs = \explode('; ', $cookieHeader);

        foreach ($pairs as $pair) {
            if (\str_contains($pair, '=')) {
                [$key, $value] = \explode('=', $pair, 2);
                $request->cookies->put(\trim($key), \urldecode(\trim($value)));
            }
        }
    }


    private function parseMultipart(Request $request): void {
        $contentType = $request->getContentType();
        $body = $request->getContent();
    
        if (!str_contains($contentType, 'multipart/form-data')) {
            return;
        }

        if (!preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            return;
        }

        $boundary = $matches[1];
        $parts = explode("--$boundary", $body);

        foreach ($parts as $part) {
            $part = ltrim($part);
            if (empty($part) || str_starts_with($part, "--")) continue;

            $pos = strpos($part, "\r\n\r\n");
            if ($pos === false) continue;

            $headersStr = substr($part, 0, $pos);
            $content = substr($part, $pos + 4);
            $content = substr($content, 0, -2); // Remove trailing \r\n

            if (preg_match('/name="(?<name>.*?)"; filename="(?<filename>.*?)"/', $headersStr, $match)) {
                $request->files->put($match['name'], [
                    'name' => $match['filename'],
                    'content' => $content,
                    'tmp_path' => $this->saveToTemp($content)
                ]);
            } elseif (preg_match('/name="(?<name>.*?)"/', $headersStr, $match)) {
                $request->params->put($match['name'], $content);
            }
        }
    }
    
    private function saveToTemp(string $content): string {
        $file = \tempnam(\sys_get_temp_dir(), "fiber_files_");

        \file_put_contents($file, $content);

        return $file;
    }

    private function buildPostParams(Request $request): void {
        $contentType = $request->getContentType();
        $content = $request->getContent();

        if (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            \parse_str($content, $params);
            $request->params->replace($params ?? []);
        } elseif ($request->isJson()) {
            $params = \json_decode($content, true);
            if (\is_array($params)) {
                $request->params->replace($params);
            }
        }
    }
}