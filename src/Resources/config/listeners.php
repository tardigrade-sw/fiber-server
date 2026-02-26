<?php 
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tg\FiberServer\Bridge\Listener\ServerTimingListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->set(ServerTimingListener::class)
            ->arg('$stopwatch', service('debug.stopwatch'))
            ->tag('kernel.event_listener', [
                'event' => 'kernel.request',
                'method' => 'onKernelRequest',
                'priority' => 2048
            ])
    ;
};