<?php declare(strict_types=1);

namespace App\Abstracts;

abstract class BaseService
{
    abstract public function findById(int $id): ?object;
}
