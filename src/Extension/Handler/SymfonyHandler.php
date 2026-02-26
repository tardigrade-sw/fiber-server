<?php
declare(strict_types=1);

namespace Tg\FiberServer\Extension\Handler;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Tg\FiberServer\Bridge\RequestTransfromer;
use Tg\FiberServer\Bridge\ResponseTransfromer;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Handler\AbstractHandler;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Tg\FiberServer\Component\HttpCore\Memory\DumpStorage;

class SymfonyHandler extends AbstractHandler {


    private Kernel $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        parent::__construct();
    }

    public function getRoutePrefix(): string
    {
        return "/";
    }

    protected function doInvoke(Request $request): Response {
        $stopwatch = $request->attributes->get('_server_stopwatch');
        
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

        try {
            if ($stopwatch instanceof Stopwatch) {
                $stopwatch->start('bridge_transform_request', 'bridge');
            }
            $symfonyRequest = RequestTransfromer::transform($request);
            if ($stopwatch instanceof Stopwatch) {
                $stopwatch->stop('bridge_transform_request');
            }

            if ($stopwatch instanceof Stopwatch) {
                $stopwatch->start('symfony_kernel_handle', 'symfony');
            }
            $symfonyResponse = $this->kernel->handle(
                $symfonyRequest, 
                HttpKernelInterface::MAIN_REQUEST, 
                true
            );
            if ($stopwatch instanceof Stopwatch) {
                $stopwatch->stop('symfony_kernel_handle');
            }

            if ($stopwatch instanceof Stopwatch) {
                $stopwatch->start('bridge_transform_response', 'bridge');
            }
            $response = ResponseTransfromer::transform($symfonyResponse);
            if ($stopwatch instanceof Stopwatch) {
                $stopwatch->stop('bridge_transform_response');
            }
            
            try {
                if ($stopwatch instanceof Stopwatch) {
                    $stopwatch->start('symfony_kernel_terminate', 'symfony');
                }
                $this->kernel->terminate($symfonyRequest, $symfonyResponse);
            } catch (\Throwable $e) {
                \fwrite(STDERR, "Kernel termination error: " . $e->getMessage() . "\n");
            } finally {
                if ($stopwatch instanceof Stopwatch) {
                    $stopwatch->stop('symfony_kernel_terminate');
                }
            }

            return $response;
        } catch (\Throwable $e) {
            \fwrite(STDERR, "[SymfonyHandler] Caught exception: " . \get_class($e) . ": " . $e->getMessage() . "\n");
            \fwrite(STDERR, "Critical error in SymfonyHandler: " . $e->getMessage() . "\n");
            
            return new Response(
                \sprintf(
                    "<html><body><h1>Critical Error</h1><p>%s</p><pre>%s</pre></body></html>",
                    \htmlspecialchars($e->getMessage()),
                    \htmlspecialchars($e->getTraceAsString())
                ),
                500
            );
        }
    }
}