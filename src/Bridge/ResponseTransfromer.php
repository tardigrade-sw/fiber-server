<?php
declare(strict_types=1);

namespace Tg\FiberServer\Bridge;

use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Component\HttpCore\BinaryFileResponse;
use Tg\FiberServer\Map\HttpHeadersMap;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse as SymfonyBinaryFileResponse;

class ResponseTransfromer {

    public static function transform(SymfonyResponse $symfonyResponse): Response {
        if ($symfonyResponse instanceof SymfonyBinaryFileResponse) {
            return new BinaryFileResponse($symfonyResponse->getFile()->getPathname());
        }

        $headers = new HttpHeadersMap();
        foreach ($symfonyResponse->headers->all() as $name => $values) {
            if (\in_array(\strtolower($name), ['connection', 'transfer-encoding', 'content-length', 'keep-alive'], true)) {
                continue;
            }
            if (\count($values) === 1) {
                $headers->put($name, $values[0]);
            } else {
                $headers->put($name, $values);
            }
        }

        $content = (string)$symfonyResponse->getContent();
        if (\str_starts_with($content, "\e[") || \str_contains($content, " \e[") || \str_contains($content, "\n\e[")) {
            $content = \preg_replace('/\e[[][A-Za-z0-9:;?]*[a-zA-Z]/', '', $content);
            if ($symfonyResponse->getStatusCode() >= 400 && !\str_starts_with(\trim($content), '<')) {
                // If it's an error dump but not HTML, at least make it readable in the browser
                $content = \sprintf(
                    "<html><body style='background:#111;color:#eee;font-family:monospace;padding:2em;'><h1>Dev Error</h1><pre style='background:#222;padding:1em;border-radius:4px;'>%s</pre></body></html>",
                    \htmlspecialchars($content)
                );
            }
        }

        return new Response(
            $content,
            $symfonyResponse->getStatusCode(),
            $headers
        );
    }
}
