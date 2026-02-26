<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore\Session;

use Tg\FiberServer\Map\BaseMap;

/**
 * A fiber-safe session object that moves with the Request,
 * avoiding reliance on the global $_SESSION array.
 */
class Session extends BaseMap {
    private string $id;

    public function __construct(string $id, array $data = []) {
        parent::__construct($data);
        $this->id = $id;
    }

    public function getId(): string {
        return $this->id;
    }

    /**
     * Serializes session data back into the storage.
     */
    public function save(): void {
        MemorySessionStorage::write($this->id, serialize($this->toArray()));
    }
}
