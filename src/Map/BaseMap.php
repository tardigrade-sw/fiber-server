<?php
declare(strict_types=1);

namespace Tg\FiberServer\Map;

use Ds\Map;
use IteratorAggregate;
use Traversable;

/**
 * @template K
 * @template V
 * @implements IteratorAgregate<K, V>
 */
class BaseMap implements IteratorAggregate {
    protected Map $internal;

    public function __construct(iterable $values = [])
    {
        $this->internal = new Map($values);
    }

    /** @param K $key */
    public function get(mixed $key, mixed $default = null) : mixed {
        return $this->internal->get($key, $default);
    }

    /**
     * @param K $key
     * @param V $value
     */
    public function put(mixed $key, mixed $value): void {
        $this->internal->put($key, $value);
    }

    /** @param K $key */
    public function has(mixed $key): bool {
        return $this->internal->hasKey($key);
    }

    /** @param K $key */
    public function remove(mixed $key, mixed $default = null): mixed {
        return $this->internal->remove($key, $default);
    }

    public function count(): int {
        return $this->internal->count();
    }

    public function clear(): void {
        $this->internal->clear();
    }

    /** @return Traversable<K, V> */
    public function getIterator(): Traversable {
        return $this->internal->getIterator();
    }

    public function toArray(): array {
        return $this->internal->toArray();
    }

    public function replace(array $values): static {
        $this->internal = new Map($values);

        return $this;
    }

    public function intersect(Map $other): Map {
        return $this->internal->intersect($other);
    }

    public function __clone() {
        $this->internal = clone $this->internal;
    }

}