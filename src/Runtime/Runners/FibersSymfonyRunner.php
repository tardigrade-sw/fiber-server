<?php
declare(strict_types=1);

namespace Tg\FiberServer\Runtime\Runners;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Tg\FiberServer\Extension\Handler\SymfonyHandler;
use Tg\FiberServer\FiberServer;
use Tg\FiberServer\Handler\StaticFileHandler;

class FibersSymfonyRunner implements RunnerInterface {
    public function __construct(
        private HttpKernelInterface $kernel,
        private string $publicPath,
        private string $host, 
        private int $port = 80, 
        private string $prefix = '',
        private array $allowedStatics = [
            'ico',
            'jpg',
            'jpeg',
            'png',
            'webp',
            'webm',
            'js',
            'css',
            'svg',
        ]
    ){}


    public function run(): int {
        $server = new FiberServer(
            \sprintf(
                "tcp://%s:%s%s",
                $this->host,
                $this->port,
                $this->prefix
            )
        );

        $server->addHandler(new SymfonyHandler($this->kernel));
        $server->addPrivilegedHandler(new StaticFileHandler($this->allowedStatics, $this->publicPath));
        $server->listen();
    }
}