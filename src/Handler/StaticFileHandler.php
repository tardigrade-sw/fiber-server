<?php
declare(strict_types=1);

namespace Tg\FiberServer\Handler;

use Ds\Map;
use Symfony\Component\Stopwatch\Stopwatch;
use Tg\FiberServer\Component\HttpCore\BinaryFileResponse;
use Tg\FiberServer\Component\HttpCore\HttpStatus;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Handler\AbstractHandler;

class StaticFileHandler extends AbstractHandler {

    private array $formats;
    private string $dir;
    private Map $cache;

    public function __construct(array $formats, string $dir)
    {
        $this->formats = $formats;
        $this->dir = $dir;
        $this->cache = new Map();

        parent::__construct();
    }

    public function getRoutePrefix(): string
    {
        return '/';
    }

    public function getRoutePattern(): ?string
    {
        $formats = [];
        foreach($this->formats as $format) {
            $formats[] = "\.$format";
        }

        $pattern =  \sprintf(
            '/^\/.+(%s)$/',
            \implode("|", $formats)
        );

        return $pattern;
    }

    protected function doInvoke(Request $request): Response
    {
        $file = $request->attributes->get('path');
        $fullPath = $this->dir . $file;

        if (!\file_exists($fullPath)) {
            return Response::createNotFound();
        }

        $mtime = \filemtime($fullPath);
        $size = \filesize($fullPath);
        $etag = \sprintf('"%x-%x"', $mtime, $size);

        if ($request->headers->get('if-none-match') === $etag) {
            return new Response('', HttpStatus::NotModified);
        }

        if ($request->headers->has('if-modified-since')) {
            $since = \strtotime($request->headers->get('if-modified-since'));
            if ($since !== false && $since >= $mtime) {
                return new Response('', HttpStatus::NotModified);
            }
        }

        if ($size < 1024 * 1024) {
            $cached = $this->cache->get($fullPath, null);
            if ($cached && $cached['mtime'] === $mtime) {
                return clone $this->cache[$fullPath]['response'];
            }
        }

        $resp = new BinaryFileResponse($fullPath);
        
        if ($size < 1024 * 1024) {
            $this->cache[$fullPath] = [
                'mtime' => $mtime,
                'response' => $resp
            ];
        }

        return $resp;
    }
    
}