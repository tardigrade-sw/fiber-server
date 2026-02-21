<?php
declare(strict_types=1);

namespace Tg\FiberServer\Routing;

use Countable;
use Ds\PriorityQueue;
use Ds\Vector;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<RoutePatternNode>
 */
class RoutePatternNodeCollection implements IteratorAggregate, Countable {
    private Vector $internal;

    public function __construct() {
        $this->internal = new Vector();
    }

    public function push(RoutePatternNode ...$nodes): void {
        $this->internal->push(...$nodes);
    }

    public function count(): int {
        return $this->internal->count();
    }

    public function getIterator(): Traversable {
        return $this->internal->getIterator();
    }

    public function isEmpty(): bool {
        return $this->internal->isEmpty();
    }

    public function hasPattern(string $pattern): bool {
        foreach($this->internal as $node) {
            if($node->isPattern($pattern)) return true;
        }

        return false;
    }

    public function findPattern(string $pattern): ?RoutePatternNode {
        foreach($this->internal as $node) {
            if($node->isPattern($pattern)) return $node;
        }

        return null;
    }

    public function matchAll(string $sequence) : PriorityQueue {
        $mergeQueue = new PriorityQueue();
        foreach($this->internal as $node) {
            $queue = $node->match($sequence);
            if($queue) {
                $mergeQueue->allocate($queue->capacity());

                while(!$queue->isEmpty()){
                    $handler = $queue->pop();
                    $mergeQueue->push(
                        $handler,
                        $handler::getPriority()
                    );
                }
            }
        }

        return $mergeQueue;
    }
}