<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Processor\ErrorDiagnostic;
use SineFine\Ponymator\Documentation\Processor\ErrorReport;

final class ErrorReportTest extends TestCase
{
    public function testEmptyReport(): void
    {
        $report = new ErrorReport();

        $this->assertTrue($report->isEmpty());
        $this->assertSame(0, $report->count());
        $this->assertSame(0, $report->errorCount());
        $this->assertSame(0, $report->warningCount());
        $this->assertFalse($report->hasErrors());
        $this->assertSame([], $report->getDiagnostics());
    }

    public function testReportWithDiagnostics(): void
    {
        $diag1 = new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'Error 1');
        $diag2 = new ErrorDiagnostic(severity: ErrorDiagnostic::WARNING, message: 'Warning 1');

        $report = new ErrorReport([$diag1, $diag2]);

        $this->assertFalse($report->isEmpty());
        $this->assertSame(2, $report->count());
        $this->assertSame(1, $report->errorCount());
        $this->assertSame(1, $report->warningCount());
        $this->assertTrue($report->hasErrors());
        $this->assertSame([$diag1, $diag2], $report->getDiagnostics());
    }

    public function testReportWithMultipleErrors(): void
    {
        $diag1 = new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'Error 1');
        $diag2 = new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'Error 2');

        $report = new ErrorReport([$diag1, $diag2]);

        $this->assertSame(2, $report->count());
        $this->assertSame(2, $report->errorCount());
        $this->assertSame(0, $report->warningCount());
        $this->assertTrue($report->hasErrors());
    }

    public function testReportWithOnlyWarnings(): void
    {
        $diag = new ErrorDiagnostic(severity: ErrorDiagnostic::WARNING, message: 'Warning 1');

        $report = new ErrorReport([$diag]);

        $this->assertSame(1, $report->count());
        $this->assertSame(0, $report->errorCount());
        $this->assertSame(1, $report->warningCount());
        $this->assertFalse($report->hasErrors());
    }

    public function testAddMutatesReport(): void
    {
        $report = new ErrorReport();
        $diag = new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'new error');

        $report->add($diag);

        $this->assertFalse($report->isEmpty());
        $this->assertSame(1, $report->count());
        $this->assertSame(1, $report->errorCount());
    }

    public function testAddPreservesOrder(): void
    {
        $first = new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'First');
        $second = new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'Second');

        $report = new ErrorReport([$first]);
        $report->add($second);
        $diags = $report->getDiagnostics();

        $this->assertSame('First', $diags[0]->message);
        $this->assertSame('Second', $diags[1]->message);
    }
}
