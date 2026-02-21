<?php
declare(strict_types=1);

namespace Tg\FiberServer\Exception;

use RuntimeException;
use Throwable;

class MissingExtensionException extends RuntimeException {
    public function __construct(
        array $missingExtensions, 
        int $code = 0, 
        Throwable|null $previous = null
    ){
        return parent::__construct(
            \sprintf(
                "PHP extensions: %s are missing from your installation, please install those",
                \implode(", ", $missingExtensions)
            ), $code, $previous);
    }
}