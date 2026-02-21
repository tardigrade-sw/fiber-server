<?php
declare(strict_types=1);

namespace Tg\FiberServer\Runtime;

use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\GenericRuntime;
use Symfony\Component\Runtime\ResolverInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\RuntimeInterface;
use Tg\FiberServer\Runtime\Runners\FibersSymfonyRunner;

class SymfonyFibersRuntime extends GenericRuntime implements RuntimeInterface {

    public function getRunner(?object $application): RunnerInterface
    {

        if($application instanceof KernelInterface) {
            $kernel = $application;

            return new FibersSymfonyRunner(
                kernel: $kernel,
                publicPath: $this->options['public_path'] ?? ($application->getProjectDir() . '/public'),
                host: $this->options['host'],
                port: $this->options['port'] ?? 80,
                allowedStatics: $this->options['allowed_statics'] ?? [
                    'ico', 'jpg', 'jpeg', 'png', 'webp', 'webm', 'js', 'css', 'svg',
                ]
            );
        }

        throw new RuntimeException("Application must implement the: ". KernelInterface::class);
    }
}