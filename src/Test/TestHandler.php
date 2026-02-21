<?php
declare(strict_types=1);

namespace Tg\FiberServer\Test;

use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Extension\Handler\TwigHandler;
use Tg\FiberServer\Handler\AbstractHandler;

class TestHandler extends TwigHandler {

    public function getRoutePrefix(): string
    {
        return '/';
    }

    protected function doInvoke(Request $req): Response
    {
        return $this->render('test.html.twig', [
            'title' => 'Test page passed',
            'menu' => [
                'itemA',
                'itemB',
                'itemC',
            ]
        ]);
    }
    
}