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
            $headers->put($name, implode(', ', $values));
        }

        return new Response(
            (string)$symfonyResponse->getContent(),
            $symfonyResponse->getStatusCode(),
            $headers
        );
    }
}
