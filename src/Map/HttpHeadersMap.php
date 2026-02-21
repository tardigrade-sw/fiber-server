<?php
declare(strict_types=1);

namespace Tg\FiberServer\Map;

use Ds\Map;

/**
 * @extends BaseMap<string, string|int|float|array|null>
 */
class HttpHeadersMap extends BaseMap {

    public function withDefault(array|Map $defaults): static {
        foreach($defaults as $k => $v) {
            if(!$this->has($k)) $this->put($k, $v);
        }

        return $this;
    }
}