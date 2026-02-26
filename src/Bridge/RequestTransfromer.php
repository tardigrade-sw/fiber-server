<?php
declare(strict_types=1);

namespace Tg\FiberServer\Bridge;

use Tg\FiberServer\Component\HttpCore\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class RequestTransfromer {
    public static function transform(Request $request): SymfonyRequest  {
        $server = $request->server->toArray();
        foreach ($request->headers->toArray() as $name => $value) {
            $name = str_replace('-', '_', strtoupper($name));
            if (!in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = 'HTTP_' . $name;
            }
            $server[$name] = $value;
        }

        $symfonyRequest = new SymfonyRequest(
            query: $request->query->toArray(),
            request: $request->params->toArray(),
            attributes: $request->attributes->toArray(),
            cookies: $request->cookies->toArray(),
            files: $request->files->toArray(),
            server: $server,
            content: $request->getContent(),
        );

        $symfonyRequest->setRequestFormat('html');
        $symfonyRequest->headers->set('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $symfonyRequest->attributes->set('_format', 'html');
        $symfonyRequest->server->set('SERVER_SOFTWARE', 'FiberServer/1.0');
        $symfonyRequest->server->set('GATEWAY_INTERFACE', 'CGI/1.1');
        $symfonyRequest->server->set('REMOTE_ADDR', $request->server->get('REMOTE_ADDR', '127.0.0.1'));
        $symfonyRequest->server->set('SERVER_NAME', 'localhost');
        $symfonyRequest->server->set('SERVER_PORT', '8088');
        $symfonyRequest->server->set('SERVER_PROTOCOL', 'HTTP/1.1');
        $symfonyRequest->server->set('REQUEST_SCHEME', 'http');
        $symfonyRequest->server->set('SYMFONY_ERROR_RENDERER_SAPI', 'php-fpm');
        $symfonyRequest->server->set('VAR_DUMPER_FORMAT', 'html');
        $symfonyRequest->server->set('HTTPS', 'off');
        $symfonyRequest->server->set('REMOTE_PORT', $request->server->get('REMOTE_PORT', '0'));
        return $symfonyRequest;
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