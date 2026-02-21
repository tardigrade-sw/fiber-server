<?php
declare(strict_types=1);

namespace Tg\FiberServer\Handler;

use Ds\PriorityQueue;
use Ds\Vector;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Routing\RoutePatternNode;
use Tg\FiberServer\Routing\RouteTrie;

class HandlerPool {


    private RouteTrie $routeCollection;
    private Vector $privilegedPatternHandlers;
    private array $handlerList;

    public function __construct()
    {
        $this->routeCollection = new RouteTrie();
        $this->privilegedPatternHandlers = new Vector();
    }

    public function addHandler(HandlerInterface $handler): void {
        $this->handlerList[$handler->getRoutePrefix()] = [
            'priority' => $handler->getPriority(),
            'path' => $handler->getRoutePrefix(),
            'pattern' => $handler->getRoutePattern(),
            'class' => $handler::class
        ];
        $this->routeCollection->insert($handler);
    }

    public function addPrivilegedHandler(HandlerInterface $handler): void {
        $this->privilegedPatternHandlers->push(
            new RoutePatternNode($handler->getRoutePattern(), $handler)
        );
    }

    public function getHandlers(Request $request) : PriorityQueue {

        $path = $request->getPath();
        foreach($this->privilegedPatternHandlers as $handler) {
            $queue = $handler->match($path);
            if($queue) return $queue;
        } 

        return $this->routeCollection->match($path);
    }

    public function getHandlerlist(): array {
        return \array_map(
            fn($h) => \sprintf(
                "%s (%d) – [%s] : %s",
                $h['path'],
                $h['priority'],
                $h['pattern'],
                $h['class']
            ),
            $this->handlerList
        );
    }
}