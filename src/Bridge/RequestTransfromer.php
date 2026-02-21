<?php
declare(strict_types=1);

namespace Tg\FiberServer\Bridge;

use Tg\FiberServer\Component\HttpCore\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class RequestTransfromer {
    public static function transform(Request $request): SymfonyRequest  {
        return new SymfonyRequest(
            query: $request->query->toArray(),
            request: $request->params->toArray(),
            attributes: $request->attributes->toArray(),
            cookies: $request->cookies->toArray(),
            files: $request->files->toArray(),
            server: $request->server->toArray(),
            content: $request->getContent(),
        );
    }

    public static function reverseTransform(SymfonyRequest $symfonyRequest): Request
    {
        $request = new Request();

        $request->query->replace($symfonyRequest->query->all());
        $request->params->replace($symfonyRequest->request->all());
        $request->attributes->replace($symfonyRequest->attributes->all());
        $request->cookies->replace($symfonyRequest->cookies->all());
        $request->files->replace($symfonyRequest->files->all());
        $request->server->replace($symfonyRequest->server->all());
        $request->setContent($symfonyRequest->getContent());

        return $request;
    }
}