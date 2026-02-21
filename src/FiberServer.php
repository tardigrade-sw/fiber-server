<?php
declare(strict_types=1);

namespace Tg\FiberServer;

use Closure;
use Ds\Map;
use Ds\PriorityQueue;
use Ds\Vector;
use Fiber;
use Tg\FiberServer\Component\HttpCore\Memory\RequestPool;
use Tg\FiberServer\Component\HttpCore\Parsers\Http1Parser;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Component\HttpCore\HttpStatus;
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

        echo "Server started at {$this->address}\n";

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

        $conn = stream_socket_accept($this->server);
        if(!$conn) return;

        stream_set_blocking($conn, false);
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
            echo "Connection dropped: " . $e->getMessage() . "\n";
            $this->clearConnection(null, $conn);
        }
    }

    private function handleClient($conn): void {
        $parser = new Http1Parser();
        $parser->setClientIp((string)stream_socket_get_name($conn, true));
        $request = null;

        try {
            while (true) {
                if ($request === null) {
                    $request = $this->requestPool->get();
                }

                $data = \fread($conn, 8192);
                
                if ($data === false || \feof($conn)) break;

                if ($data === '') {
                    Fiber::suspend();
                    continue;
                }

                if ($parser->parse($data, $request)) {
                    try {
                        $handlers = $this->handlerPool->getHandlers($request);
                        $response = $this->applyHandersUntilOutput($handlers, $request);
                    } catch (\Throwable $e) {
                        echo "Error processing request: " . $e->getMessage() . "\n";
                        $response = new Response(
                            "Internal Server Error: " . $e->getMessage(), HttpStatus::InternalServerError
                        );
                        $response->setHeader('Connection', 'close');
                    }
                    
                    \fwrite($conn, $response->dumpTcp());

                    $connectionHeader = $response->getHeader('Connection', 'keep-alive');
                    
                    $this->requestPool->release($request);
                    $request = null;
                    $parser->reset();

                    if ($connectionHeader === 'close') {
                        break;
                    }
                }

                Fiber::suspend();
            }
        } finally {
            $this->clearConnection($request, $conn);
        }
    }

    private function clearConnection(?Request $request, $conn) {
        if ($request !== null) $this->requestPool->release($request);

        \fclose($conn);

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