<?php
declare(strict_types=1);

namespace Tg\FiberServer\Routing;

use Ds\PriorityQueue;
use Tg\FiberServer\Handler\HandlerInterface;

class RoutePatternNode {

    private string $pattern;
    private PriorityQueue $handlerQueue;



    public function __construct(string $pattern, ?HandlerInterface $handler)
    {
        $this->handlerQueue = new PriorityQueue();
        if($handler) {
            $this->handlerQueue->push($handler, $handler::getPriority());
        }
        $this->pattern = $pattern;
    }

    public function push(HandlerInterface $handler): void {
        $this->handlerQueue->push($handler, $handler::getPriority());
    }

    public function match(string $segment): ?PriorityQueue {
        if($this->doesMatch($segment)) return clone $this->handlerQueue;

        return null;
    }

    public function isPattern(string $pattern): bool {
        return $this->pattern === $pattern;
    }

    protected function doesMatch(string $segment): bool {
        return preg_match($this->pattern, $segment, $_) != false;
    }
}