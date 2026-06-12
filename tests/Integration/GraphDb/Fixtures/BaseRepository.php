<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Repositories;

use GraphDbTest\Domain\Contracts\RepositoryInterface;

abstract class BaseRepository implements RepositoryInterface
{
    protected array $storage = [];

    public function findById(int $id): ?object
    {
        return $this->storage[$id] ?? null;
    }
}
