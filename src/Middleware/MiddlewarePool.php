<?php
declare(strict_types=1);

namespace Tg\FiberServer\Middleware;

use Ds\PriorityQueue;
use Ds\Vector;

class MiddlewarePool {
    private PriorityQueue $internal;

    public function __construct()
    {
        $this->internal = new PriorityQueue();
    }

    public function addMiddleware(
        ServerCycleInterface $middleware, 
        int $priority = 1
    ): void {
        $this->internal->push($middleware, $priority);
    }

    public function getMiddleware(): Vector {
        return new Vector($this->internal->toArray());
    }
}