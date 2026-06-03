<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Processor;

class GenerationResult
{
    /**
     * @param int $generated
     * @param int $skipped
     * @param int $unchanged
     * @param int $removed
     * @param ErrorReport|null $errorReport
     * @param int|null $executionTimeNs
     */
    private ErrorReport $errorReport;

    public function __construct(
        private int $generated = 0,
        private int $skipped = 0,
        private int $unchanged = 0,
        private int $removed = 0,
        ?ErrorReport $errorReport = null,
        private ?int $executionTimeNs = null,
    ) {
        $this->errorReport = $errorReport ?? new ErrorReport();
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

    public function getErrorReport(): ErrorReport
    {
        return $this->errorReport;
    }

    public function getExecutionTimeSec(): ?float
    {
        return $this->executionTimeNs !== null
            ? $this->executionTimeNs / 1_000_000_000
            : null;
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

    public function addError(ErrorDiagnostic $diagnostic): void
    {
        $this->errorReport->add($diagnostic);
    }

    public function setExecutionTimeNs(int $nanoseconds): void
    {
        $this->executionTimeNs = $nanoseconds;
    }
}
