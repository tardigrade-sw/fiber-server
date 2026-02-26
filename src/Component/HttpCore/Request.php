<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore;

use Ds\Map;
use RuntimeException;
use Tg\FiberServer\Map\BaseMap;

class Request {

    public Headers $headers;
    public Attributes $attributes;
    public Params $params;
    public Query $query;
    public Cookies $cookies;
    public Server $server;
    public BaseMap $files;
    public ?Session\Session $session = null;

    private string $body = '';
    private string $method = '';
    private string $uri = '';
    private string $version = '';

    public function __construct()
    {
        $this->headers = new Headers();
        $this->attributes = new Attributes();
        $this->params = new Params();
        $this->query = new Query();
        $this->cookies = new Cookies();
        $this->server = new Server();
        $this->files = new BaseMap();
    }

    public function reset(): void {
        $this->headers->clear();
        $this->attributes->clear();
        $this->params->clear();
        $this->query->clear();
        $this->cookies->clear();
        $this->server->clear();
        $this->files->clear();
        $this->body = '';
        $this->method = '';
        $this->uri = '';
        $this->version = '';
    }

    

    public function isJson(): bool {
        return \str_starts_with(
            $this->getContentType(),
            'application/json'
        );
    }

    public function getContentType(): string {
        return $this->headers->get('content-type', 'text/html');
    }

    public function toArray(): array {
        if(!$this->isJson()) throw new RuntimeException("Request body is not json");

        return \json_decode($this->body, true);
    }

    public function getPath(): string {
        return $this->attributes->get('path');
    }

    public function getContent(): string {
        return $this->body;
    }

    public function setContent(string $content): void {
        $this->body = $content;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getVersion(): string {
        return $this->version;
    }

    public function getUri(): string {
        return $this->uri;
    }
}