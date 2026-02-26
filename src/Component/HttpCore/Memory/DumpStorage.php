<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Memory;

class DumpStorage {
    private static array $dumps = [];

    public static function add(string $dump): void {
        // Keep only the last 50 dumps to avoid memory issues
        \array_unshift(self::$dumps, [
             'time' => (new \DateTime())->format('H:i:s'),
             'content' => $dump
        ]);
        
        if (\count(self::$dumps) > 50) {
            \array_pop(self::$dumps);
        }
    }

    public static function getAll(): array {
        return self::$dumps;
    }

    public static function clear(): void {
        self::$dumps = [];
    }
}
