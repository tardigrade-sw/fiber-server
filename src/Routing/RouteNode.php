<?php
declare(strict_types=1);

namespace Tg\FiberServer\Routing;

use Ds\Map;
use Ds\PriorityQueue;
use Ds\Vector;
use Tg\FiberServer\Handler\HandlerInterface;

class RouteNode {
    private Map $staticChildren;
    private RoutePatternNodeCollection $dynamicChildren;

    private PriorityQueue $leafNodes;

    public function __construct()
    {
        $this->staticChildren = new Map();
        $this->dynamicChildren = new RoutePatternNodeCollection();
        $this->leafNodes = new PriorityQueue();
    }

    public function insert(
        string $routePrefix, 
        ?string $routePattern,
        HandlerInterface $handler
    ) {
        $segments = array_values(array_filter(explode("/", $routePrefix)));

        $this->insertRecursive($segments, $routePattern, $handler);
    }

    protected function insertRecursive(array $segments, ?string $routePattern, HandlerInterface $handler): void {
        if(empty($segments)){
            if($routePattern){
                $node = $this->dynamicChildren->findPattern($routePattern);
                if($node !== null){
                    $node->push($handler);
                }else {
                    $this->dynamicChildren->push(
                        new RoutePatternNode($routePattern, $handler)
                    );
                }
            } else {
                $this->leafNodes->push($handler, $handler::getPriority());
            }
            return;
        }

        $first = array_shift($segments);

        $node = $this->staticChildren->get($first, null);

        if(!$node){
            $node = new RouteNode();
            $this->staticChildren->put($first, $node);
        }

        $node->insertRecursive($segments, $routePattern, $handler);
    }

    public function match(string $sequence): PriorityQueue {
        $segments = array_values(array_filter(explode("/", $sequence)));
        
        return $this->matchRecursive($segments);
    }

    protected function matchRecursive(array $segments): PriorityQueue {
        if(empty($segments)) {
            return clone $this->leafNodes;
        }

        $first = array_shift($segments);

        $hit = $this->staticChildren->get($first, null);
        if(!$hit) {
            $queue = $this->dynamicChildren->matchAll($first);


            return $queue->isEmpty() ? clone $this->leafNodes : $queue;
        } else {
            return $hit->matchRecursive($segments);
        }
    }
}