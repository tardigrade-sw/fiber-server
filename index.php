<?php
declare(strict_types=1);

namespace Main;

require_once(__DIR__. "/vendor/autoload.php");

use Tg\FiberServer\Encoding\GzipEncoder;
use Tg\FiberServer\FiberServer;
use Tg\FiberServer\Handler\StaticFileHandler;
use Tg\FiberServer\Middleware\SslMiddleware;
use Tg\FiberServer\SslBridge\CertGenerator;
use Tg\FiberServer\Test\TestHandler;



$server = new FiberServer('tcp://127.0.0.1:8899');

$server->addMiddleware(new SslMiddleware(
    new CertGenerator(
        __DIR__."/var/ssl/cert.pem",
        __DIR__."/var/ssl/key.pem"
    )
));

$controller = new TestHandler([__DIR__."/templates"]);
$controller->addEncoder(new GzipEncoder());

$fileServer = new StaticFileHandler([
    'ico',
    'css',
    'js'
], __DIR__);

$fileServer->addEncoder(new GzipEncoder());




$server->addHandler($controller);
$server->addPrivilegedHandler($fileServer);
$server->listen();



