<?php
declare(strict_types=1);

namespace Tg\FiberServer\Map;

use Ds\Map;

/**
 * @extends BaseMap<string, string|int|float|array|null>
 */
class HttpHeadersMap extends BaseMap {

    public function __construct(iterable $values = [])
    {
        $normalized = [];
        foreach($values as $k => $v) {
            $normalized[\strtolower((string)$k)] = $v;
        }
        parent::__construct($normalized);
    }

    public function get(mixed $key, mixed $default = null): mixed
    {
        return parent::get(\strtolower((string)$key), $default);
    }

    public function put(mixed $key, mixed $value): void
    {
        parent::put(\strtolower((string)$key), $value);
    }

    public function has(mixed $key): bool
    {
        return parent::has(\strtolower((string)$key));
    }

    public function remove(mixed $key, mixed $default = null): mixed
    {
        return parent::remove(\strtolower((string)$key), $default);
    }

    public function withDefault(array|Map $defaults): static {
        foreach($defaults as $k => $v) {
            if(!$this->has($k)) $this->put($k, $v);
        }

        return $this;
    }
}