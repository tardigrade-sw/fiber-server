<?php
declare(strict_types=1);

namespace Tg\FiberServer\Encoding;

interface EncoderInterface {
    public function encode(string $content, int $level = -1): string;

    public static function getAlgo(): string;
}