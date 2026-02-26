<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Session;

use Ds\Map;
use Ds\PriorityQueue;

/**
 * A persistent in-memory storage for sessions, using a Map for lookup
 * and a PriorityQueue (binary heap) for garbage collection.
 */
class MemorySessionStorage {

    /** @var Map<string, string> */
    private static Map $storage;

    /** @var Map<string, int> */
    private static Map $expiries;

    /** @var PriorityQueue<array{string, int}> */
    private static PriorityQueue $gcQueue;

    public static function init(): void {
        self::$storage ??= new Map();
        self::$expiries ??= new Map();
        self::$gcQueue ??= new PriorityQueue();
    }

    public static function read(string $id): string {
        self::init();
        self::gc();
        return self::$storage->get($id, '');
    }

    public static function write(string $id, string $data, int $ttl = 3600): void {
        self::init();
        self::$storage->put($id, $data);
        $expiry = time() + $ttl;
        self::$expiries->put($id, $expiry);
        self::$gcQueue->push([$id, $expiry], -$expiry);
    }

    public static function destroy(string $id): void {
        self::init();
        self::$storage->remove($id);
        self::$expiries->remove($id);
    }

    public static function gc(): void {
        self::init();
        $now = time();
        while (!self::$gcQueue->isEmpty()) {
            [$id, $expiry] = self::$gcQueue->peek();
            if ($expiry > $now) {
                break;
            }

            self::$gcQueue->pop();
            
            if (self::$expiries->hasKey($id) && self::$expiries->get($id) <= $now) {
                self::destroy($id);
            }
        }
    }
    
    public static function has(string $id): bool {
        self::init();
        return self::$storage->hasKey($id);
    }
}
