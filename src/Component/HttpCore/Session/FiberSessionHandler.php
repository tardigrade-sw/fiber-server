<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Session;

use SessionHandlerInterface;

/**
 * Custom session handler that hooks into the native PHP session mechanism
 * while utilizing persistent in-memory storage (MemorySessionStorage).
 */
class FiberSessionHandler implements SessionHandlerInterface {

    private int $maxLifetime;

    public function __construct(int $maxLifetime = 3600) {
        $this->maxLifetime = $maxLifetime;
    }

    public function open(string $path, string $name): bool {
        MemorySessionStorage::init();
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string {
        return MemorySessionStorage::read($id);
    }

    public function write(string $id, string $data): bool {
        MemorySessionStorage::write($id, $data, $this->maxLifetime);
        return true;
    }

    public function destroy(string $id): bool {
        MemorySessionStorage::destroy($id);
        return true;
    }

    public function gc(int $maxLifetime): int|false {
        MemorySessionStorage::gc();
        return 1; // Simplified return
    }
}
