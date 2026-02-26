<?php
declare(strict_types=1);

namespace Tg\FiberServer;

use Closure;
use Ds\Map;
use Ds\PriorityQueue;
use Ds\Vector;
use Fiber;
use Symfony\Component\Stopwatch\Stopwatch;
use Tg\FiberServer\Component\HttpCore\Memory\RequestPool;
use Tg\FiberServer\Component\HttpCore\Parsers\Http1Parser;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Component\HttpCore\HttpStatus;
use Tg\FiberServer\Component\HttpCore\Session\SessionManager;
use Tg\FiberServer\Handler\HandlerInterface;
use Tg\FiberServer\Handler\HandlerPool;
use Tg\FiberServer\Middleware\MiddlewarePool;
use Tg\FiberServer\Middleware\ServerCycleInterface;
use Throwable;

/**
 * @property resource $server
 */
class FiberServer {

    private array $sockets = [];
    private Map $fibers;
    private $server;
    private HandlerPool $handlerPool;
    private RequestPool $requestPool;
    private MiddlewarePool $middlewarePool;

    private ?Vector $middleware = null;

    public function __construct(private string $address, int $requestLimit = 50)
    {
        $this->handlerPool = new HandlerPool();
        $this->requestPool = new RequestPool($requestLimit);
        $this->middlewarePool = new MiddlewarePool();
        $this->fibers = new Map();
        SessionManager::register();
    }

    public function addHandler(HandlerInterface $handler): static {
        $this->handlerPool->addHandler($handler);

        return $this;
    }

    public function addPrivilegedHandler(HandlerInterface $handler): static {
        $this->handlerPool->addPrivilegedHandler($handler);

        return $this;
    }

    public function addMiddleware(ServerCycleInterface $middleware, int $priority = 1) {
        $this->middlewarePool->addMiddleware($middleware, $priority);
    }


    public function listen(): never {
        $this->middleware ??= $this->middlewarePool->getMiddleware();

        $contextOptions = [];

        foreach($this->middleware as $mw) {
            $mw->beforeListen($contextOptions, $this->address);
        }

        $this->server = \stream_socket_server(
            $this->address, 
            $errno, 
            $err,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            \stream_context_create($contextOptions)
        );

        if (!$this->server) {
            throw new \RuntimeException("Could not bind: $err");
        }

        \stream_set_blocking($this->server, false);
        $this->sockets[] = $this->server;

        \fwrite(STDERR, "Server started at {$this->address}\n");

        while(true){
            $read = $this->sockets;
            $write = $except = null;

            if (\stream_select($read, $write, $except, 0, 100000) > 0) {
                foreach ($read as $socket) {
                    if ($socket === $this->server) {
                        $this->acceptConnection();
                    } else {
                        $this->fibers[(int)$socket]->resume();
                    }
                }
            }
        }
    }


    private function acceptConnection(): void {

        $conn = \stream_socket_accept($this->server);
        if(!$conn) return;

        \stream_set_blocking($conn, false);
        $this->sockets[] = $conn;

        $fiber = new Fiber($this->handleConnetion(...));
        $this->fibers->put((int)$conn, $fiber);
        $fiber->start($conn);
    }


    private function handleConnetion($conn): void {
        $this->middleware ??= $this->middlewarePool->getMiddleware();
        try {
            foreach($this->middleware as $mw) {
                $mw->onConnection($conn);
            }

            $this->handleClient($conn);
        } catch (Throwable $e) {
            \fwrite(STDERR, "Connection dropped: " . $e->getMessage() . "\n");
            $this->clearConnection(null, $conn);
        }
    }

    private function handleClient($conn): void {
        $stopwatch = new Stopwatch(true);
        $parser = new Http1Parser();
        $parser->setClientIp((string)stream_socket_get_name($conn, true));
        $request = null;

        try {
            while (true) {
                if ($request === null) {
                    $request = $this->requestPool->get();
                }

                $stopwatch->start('server_read', 'server');
                $data = (string)\fread($conn, 8192);
                $stopwatch->stop('server_read');
                
                if ($data === false || ($data === '' && \feof($conn))) break;

                $stopwatch->start('server_parse', 'server');
                $parseSuccess = $parser->parse($data, $request);
                $stopwatch->stop('server_parse');

                while ($parseSuccess) {
                    $request->attributes->put('_server_stopwatch', $stopwatch);

                    $stopwatch->start('server_handle', 'server');
                    SessionManager::startRequest($request);
                    try {
                        $stopwatch->start('server_handler_lookup', 'server');
                        $handlers = $this->handlerPool->getHandlers($request);
                        $stopwatch->stop('server_handler_lookup');

                        $stopwatch->start('server_handler_exec', 'server');
                        $response = $this->applyHandersUntilOutput($handlers, $request);
                        $stopwatch->stop('server_handler_exec');
                    } catch (\Throwable $e) {
                         \fwrite(STDERR, "Error processing request: " . $e->getMessage() . "\n");
                        \fwrite(STDERR, $e->getTraceAsString() . "\n");
                        
                        $response = new Response(
                            \sprintf(
                                "<html><head><title>Internal Server Error</title><style>body{font-family:sans-serif;padding:2em}pre{background:#f4f4f4;padding:1em;overflow:auto}</style></head><body><h1>Internal Server Error</h1><p>%s</p><h3>Stack Trace</h3><pre>%s</pre></body></html>",
                                \htmlspecialchars($e->getMessage()),
                                \htmlspecialchars($e->getTraceAsString())
                            ), 
                            HttpStatus::InternalServerError
                        );
                        $response->setHeader('Connection', 'close');
                        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
                    }
                    
                    SessionManager::endRequest($response);
                    
                    $stopwatch->start('server_write', 'server');
                    stream_set_blocking($conn, true);
                    \fwrite($conn, $response->dumpTcp());
                    stream_set_blocking($conn, false);
                    $stopwatch->stop('server_write');

                    $stopwatch->stop('server_handle');

                    $connectionHeader = $response->getHeader('Connection', 'keep-alive');
                    
                    $this->requestPool->release($request);
                    $request = null;
                    $parser->reset();

                    if ($connectionHeader === 'close') {
                        return;
                    }

                    $data = ''; 
                    $request = $this->requestPool->get();

                    $stopwatch->start('server_parse', 'server');
                    $parseSuccess = $parser->parse($data, $request);
                    $stopwatch->stop('server_parse');
                }

                $stopwatch->start('server_idle', 'server');
                Fiber::suspend();
                $stopwatch->stop('server_idle');
            }
        } finally {
            $this->clearConnection($request, $conn);
        }
    }

    private function clearConnection(?Request $request, $conn) {
        if ($request !== null) $this->requestPool->release($request);

        if (is_resource($conn)) {
            \fclose($conn);
        }

        $this->fibers->remove((int)$conn);

        $key = \array_search($conn, $this->sockets);
        if ($key !== false) {
            unset($this->sockets[$key]);
        }
    }

    private function applyHandersUntilOutput(PriorityQueue $handlers, Request $request): Response {
        $handlers = clone $handlers;

        if($handlers->isEmpty()) {
            return new Response("No handlers found for path: {$request->getPath()}", HttpStatus::InternalServerError);
        }
        while(!$handlers->isEmpty()) {
            /** @var HandlerInterface $handler */
            $handler = $handlers->pop();

            if($handler::hasOutput()){
                return $handler($request);
            }

            $handler($request);
        }

        return Response::createNotFound();
    }
}