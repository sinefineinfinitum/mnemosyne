<?php declare(strict_types=1);

namespace App\Model;

readonly class ReadOnlyEntity
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }

    public function getLabel(): string
    {
        return $this->name;
    }
}
