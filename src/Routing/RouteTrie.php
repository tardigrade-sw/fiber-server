<?php
declare(strict_types=1);

namespace Tg\FiberServer\Routing;

use Ds\PriorityQueue;
use Tg\FiberServer\Handler\HandlerInterface;

class RouteTrie {
    private RouteNode $root;

    public function __construct()
    {
        $this->root = new RouteNode();
    }

    public function match(string $url): PriorityQueue {
        return $this->root->match(\ltrim($url, '/'));
    }

    public function insert(HandlerInterface $handler) {
        $this->root->insert(
            $handler->getRoutePrefix(), 
            $handler->getRoutePattern(), 
            $handler
        );
    }
}