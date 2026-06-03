<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Processor\ErrorDiagnostic;
use SineFine\Ponymator\Documentation\Processor\GenerationResult;

final class GenerationResultTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $result = new GenerationResult();

        $this->assertSame(0, $result->getGenerated());
        $this->assertSame(0, $result->getSkipped());
        $this->assertSame(0, $result->getUnchanged());
        $this->assertSame(0, $result->getRemoved());
        $this->assertTrue($result->getErrorReport()->isEmpty());
    }

    public function testIncrementGenerated(): void
    {
        $result = new GenerationResult();
        $result->incrementGenerated();
        $result->incrementGenerated();
        $this->assertSame(2, $result->getGenerated());
    }

    public function testIncrementSkipped(): void
    {
        $result = new GenerationResult();
        $result->incrementSkipped();
        $this->assertSame(1, $result->getSkipped());
    }

    public function testIncrementUnchanged(): void
    {
        $result = new GenerationResult();
        $result->incrementUnchanged();
        $this->assertSame(1, $result->getUnchanged());
    }

    public function testIncrementRemoved(): void
    {
        $result = new GenerationResult();
        $result->incrementRemoved();
        $this->assertSame(1, $result->getRemoved());
    }

    public function testAddError(): void
    {
        $result = new GenerationResult();
        $diag = new ErrorDiagnostic(ErrorDiagnostic::WARNING, 'test warning');

        $result->addError($diag);

        $report = $result->getErrorReport();
        $this->assertFalse($report->isEmpty());
        $this->assertSame(1, $report->warningCount());
        $this->assertSame('test warning', $report->getDiagnostics()[0]->message);
    }

    public function testMultipleErrors(): void
    {
        $result = new GenerationResult();
        $result->addError(new ErrorDiagnostic(ErrorDiagnostic::ERROR, 'err1'));
        $result->addError(new ErrorDiagnostic(ErrorDiagnostic::WARNING, 'warn1'));

        $report = $result->getErrorReport();
        $this->assertSame(2, $report->count());
        $this->assertSame(1, $report->errorCount());
        $this->assertSame(1, $report->warningCount());
    }

    public function testExecutionTimeNullByDefault(): void
    {
        $result = new GenerationResult();
        $this->assertNull($result->getExecutionTimeSec());
    }

    public function testExecutionTime(): void
    {
        $result = new GenerationResult();

        $result->setExecutionTimeNs(1_500_000_000);

        $this->assertSame(1.5, $result->getExecutionTimeSec());
    }

    public function testExecutionTimePrecise(): void
    {
        $result = new GenerationResult();

        $result->setExecutionTimeNs(1_234_567_890);

        $this->assertSame(1.23456789, $result->getExecutionTimeSec());
    }

    public function testZeroExecutionTime(): void
    {
        $result = new GenerationResult();

        $result->setExecutionTimeNs(0);

        $this->assertSame(0.0, $result->getExecutionTimeSec());
    }
}
