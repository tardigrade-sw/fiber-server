<?php
declare(strict_types=1);

namespace Tg\FiberServer\Encoding;

class GzipEncoder implements EncoderInterface {

    public static function getAlgo(): string
    {
        return 'gzip';
    }

    public function encode(string $content, int $level = -1): string
    {
        return gzencode($content, $level);
    }
}