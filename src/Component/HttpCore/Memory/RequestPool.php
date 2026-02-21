<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Memory;

use Ds\Deque;
use RuntimeException;
use Tg\FiberServer\Component\HttpCore\Request;

class RequestPool {

    private Deque $pool;
    private int $activeCount = 0;

    public function __construct(
        private readonly int $maxActiveCount
    )
    {
        $this->pool = new Deque();
    }

    public function get(): Request {
        if($this->activeCount >= $this->maxActiveCount) {
            throw new RuntimeException("Active request limit exceeded");
        }

        $this->activeCount++;

        return $this->pool->isEmpty() 
            ? new Request
            : $this->pool->pop();

    }

    public function release(Request $request): void {
        $request->reset();
        $this->pool->push($request);
        $this->activeCount--;
    }

    public function getActiveCount(): int {
        return $this->activeCount;
    }
}