<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

class GenerationResult
{
    /**
     * @param int      $generated
     * @param int      $skipped
     * @param int      $unchanged
     * @param int      $removed
     * @param string[] $errors
     */
    public function __construct(
        private int $generated = 0,
        private int $skipped = 0,
        private int $unchanged = 0,
        private int $removed = 0,
        private array $errors = [],
    ) {
    }

    public function getGenerated(): int
    {
        return $this->generated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getUnchanged(): int
    {
        return $this->unchanged;
    }

    public function getRemoved(): int
    {
        return $this->removed;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function incrementGenerated(): void
    {
        $this->generated++;
    }

    public function incrementSkipped(): void
    {
        $this->skipped++;
    }

    public function incrementUnchanged(): void
    {
        $this->unchanged++;
    }

    public function incrementRemoved(): void
    {
        $this->removed++;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}
