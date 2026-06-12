<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Contracts;

interface RepositoryInterface
{
    public function findById(int $id): ?object;

    public function save(object $entity): void;
}
