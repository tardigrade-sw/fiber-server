<?php
declare(strict_types=1);

namespace Tg\FiberServer\Runtime\Runners;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Tg\FiberServer\Handler\DumpHandler;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Tg\FiberServer\Component\HttpCore\Memory\DumpStorage;
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
        \putenv('SYMFONY_ERROR_RENDERER_SAPI=fpm');
        \putenv('SHELL_VERBOSITY=-1');
        $_SERVER['SYMFONY_ERROR_RENDERER_SAPI'] = 'fpm';
        
        \putenv('VAR_DUMPER_SERVER'); 
        unset($_ENV['VAR_DUMPER_SERVER'], $_SERVER['VAR_DUMPER_SERVER']);

        VarDumper::setHandler(function ($var) {
            $cloner = new VarCloner();
            $dumper = new HtmlDumper();
            
            $output = \fopen('php://memory', 'r+');
            $dumper->dump($cloner->cloneVar($var), $output);
            \rewind($output);
            $content = \stream_get_contents($output);
            \fclose($output);
            DumpStorage::add($content);
        });
        
        $server = new FiberServer(
            \sprintf(
                "tcp://%s:%s%s",
                $this->host,
                $this->port,
                $this->prefix
            )
        );

        $server->addHandler(new SymfonyHandler($this->kernel));
        $server->addHandler(new DumpHandler());
        $server->addPrivilegedHandler(new StaticFileHandler($this->allowedStatics, $this->publicPath));
        $server->listen();
    }
}