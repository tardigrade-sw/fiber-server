<?php
declare(strict_types=1);

namespace Tg\FiberServer;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FiberServerBundle extends Bundle {
    public function getContainerExtension(): ExtensionInterface|null     
    {
        if (null === $this->extension) {
            $this->extension = new DependencyInjection\FiberServerExtension();
        }

        return $this->extension;
    }
}