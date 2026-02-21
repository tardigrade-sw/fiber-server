<?php
declare(strict_types=1);

namespace Tg\FiberServer\Middleware;

interface ServerCycleInterface {
    public function beforeListen(array &$contextOptions, string &$address): void;

    public function onConnection($socket): void;
}