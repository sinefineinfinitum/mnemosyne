<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Contracts;

interface LoggableInterface
{
    public function getLogContext(): array;
}
