<?php
declare(strict_types=1);

namespace Tg\FiberServer\Extension\Handler;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tg\FiberServer\Bridge\RequestTransfromer;
use Tg\FiberServer\Bridge\ResponseTransfromer;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Handler\AbstractHandler;

class SymfonyHandler extends AbstractHandler {


    private HttpKernelInterface $kernel;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
        parent::__construct();
    }

    public function getRoutePrefix(): string
    {
        return "/";
    }

    protected function doInvoke(Request $request): Response {
        $symfonyRequest = RequestTransfromer::transform($request);
        $symfonyResponse = $this->kernel->handle(
            $symfonyRequest, 
            HttpKernelInterface::MAIN_REQUEST, 
            true
        );
        $response = ResponseTransfromer::transform($symfonyResponse);

        return $response;
    }
}