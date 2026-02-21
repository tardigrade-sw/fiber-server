<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore;

use Tg\FiberServer\Encoding\EncoderInterface;
use Tg\FiberServer\FiberServer;
use Tg\FiberServer\Map\HttpHeadersMap;

class BinaryFileResponse extends Response {
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            parent::__construct("File not found", 404);
            return;
        }

        $content = file_get_contents($path);
        $mtime = filemtime($path);
        $size = strlen($content);

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mime = match($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => mime_content_type($path) ?: 'application/octet-stream'
        };

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string)$size,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'ETag' => sprintf('"%x-%x"', $mtime, $size),
            'Cache-Control' => 'public, max-age=3600',
            'Accept-Ranges' => 'bytes',
            'Vary' => 'Accept-Encoding',
        ];
        return parent::__construct($content, 200, new HttpHeadersMap($headers));
    }
}