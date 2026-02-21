<?php
declare(strict_types=1);

namespace Tg\FiberServer\Handler;

use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;

interface HandlerInterface  {

    public static function hasOutput(): bool;

    public static function getPriority(): int;


    public function getRoutePrefix(): string;

    public function getRoutePattern(): ?string;

    public function __invoke(Request $request): Response;
}