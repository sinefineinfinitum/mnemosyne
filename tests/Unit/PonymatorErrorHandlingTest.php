<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Error\ErrorOutputFormatter;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\Processor\ErrorDiagnostic;
use SineFine\Ponymator\Documentation\Processor\ErrorReport;
use SineFine\Ponymator\Ponymator;

final class PonymatorErrorHandlingTest extends TestCase
{
    public function testExitCodesConstants(): void
    {
        $this->assertSame(0, ExitCode::SUCCESS);
        $this->assertSame(1, ExitCode::GENERIC_ERROR);
        $this->assertSame(2, ExitCode::COMMAND_LINE_MISTAKE);
        $this->assertSame(64, ExitCode::WRONG_USAGE);
        $this->assertSame(66, ExitCode::SOURCE_NOT_FOUND);
        $this->assertSame(73, ExitCode::OUTPUT_ERROR);
        $this->assertSame(78, ExitCode::CONFIG_ERROR);
    }

    public function testErrorBlockFormatWithErrors(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(
                severity: ErrorDiagnostic::ERROR,
                message: 'Parse error in file',
                filePath: 'src/Foo.php',
                lineNumber: 42,
            ),
            new ErrorDiagnostic(
                severity: ErrorDiagnostic::WARNING,
                message: 'Deprecated method used',
                filePath: 'src/Bar.php',
            ),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringContainsString('--- Errors (1 error, 1 warning) ---', $output);
        $this->assertStringContainsString('Error: [src/Foo.php:42] Parse error in file', $output);
        $this->assertStringContainsString('Warning: [src/Bar.php] Deprecated method used', $output);
        $this->assertStringEndsWith("---\n", $output);
    }

    public function testErrorBlockFormatWithOnlyWarnings(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(
                severity: ErrorDiagnostic::WARNING,
                message: 'Something minor',
            ),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringContainsString('--- Errors (1 warning) ---', $output);
        $this->assertStringContainsString('Warning: Something minor', $output);
        $this->assertStringEndsWith("---\n", $output);
    }

    public function testErrorBlockFormatWithOnlyErrors(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(
                severity: ErrorDiagnostic::ERROR,
                message: 'Fatal error',
                filePath: 'src/Foo.php',
            ),
            new ErrorDiagnostic(
                severity: ErrorDiagnostic::ERROR,
                message: 'Another error',
            ),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringContainsString('--- Errors (2 errors) ---', $output);
    }

    public function testNoOutputForEmptyReport(): void
    {
        $report = new ErrorReport();
        $formatter = new ErrorOutputFormatter();
        $this->assertSame('', $formatter->format($report));
    }

    public function testSingularError(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'one error'),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringContainsString('--- Errors (1 error) ---', $output);
    }

    public function testBlockStartsWithHeader(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'test'),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringStartsWith('--- Errors', $output);
    }

    public function testBlockEndsWithFooter(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'test'),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringEndsWith("---\n", $output);
    }

    public function testErrorBlockWithSuggestion(): void
    {
        $report = new ErrorReport(
            [
            new ErrorDiagnostic(
                severity: ErrorDiagnostic::ERROR,
                message: 'File not found',
                filePath: 'src/Foo.php',
                suggestion: 'Check that the file exists',
            ),
            ]
        );

        $formatter = new ErrorOutputFormatter();
        $output = $formatter->format($report);

        $this->assertStringContainsString('File not found — Check that the file exists', $output);
    }
}
