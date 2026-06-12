<?php declare(strict_types=1);

namespace GraphDbTest\Domain\Support;

trait TimestampTrait
{
    protected ?string $createdAt = null;

    protected ?string $updatedAt = null;

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $timestamp): void
    {
        $this->createdAt = $timestamp;
    }
}
