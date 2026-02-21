<?php
declare(strict_types=1);

namespace Tg\FiberServer\Extension\Handler;

use Tg\FiberServer\Component\HttpCore\HttpStatus;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Handler\AbstractHandler;
use Tg\FiberServer\Map\HttpHeadersMap;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;


abstract class TwigHandler extends AbstractHandler {

    private Twig $twig;
    public function __construct(array $paths, array $twigOptions = [])
    {
        $loader = new FilesystemLoader($paths);
        $this->twig = new Twig($loader, $twigOptions);

        parent::__construct();
    }

    protected function render(
        string $view, 
        array $context = [], 
        int $status = HttpStatus::OK, 
        ?HttpHeadersMap $headers = null
    ) : Response {
        
        $content = $this->twig->render($view, $context);

        return new Response($content, $status, $headers);
    }
}