<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Processor;

final class ErrorReport
{
    private int $errorCount = 0;
    private int $warningCount = 0;

    /**
     * @param ErrorDiagnostic[] $diagnostics
     */
    public function __construct(
        private array $diagnostics = [],
    ) {
        foreach ($this->diagnostics as $diag) {
            $this->countSeverity($diag);
        }
    }

    /**
     * @return ErrorDiagnostic[]
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    public function count(): int
    {
        return count($this->diagnostics);
    }

    public function errorCount(): int
    {
        return $this->errorCount;
    }

    public function warningCount(): int
    {
        return $this->warningCount;
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    public function isEmpty(): bool
    {
        return $this->diagnostics === [];
    }

    public function add(ErrorDiagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
        $this->countSeverity($diagnostic);
    }

    private function countSeverity(ErrorDiagnostic $diagnostic): void
    {
        if ($diagnostic->severity === ErrorDiagnostic::ERROR) {
            $this->errorCount++;
        } elseif ($diagnostic->severity === ErrorDiagnostic::WARNING) {
            $this->warningCount++;
        }
    }
}
