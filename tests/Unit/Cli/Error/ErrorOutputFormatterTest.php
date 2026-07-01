<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Cli\Error;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\Error\ErrorOutputFormatter;
use SineFine\Mnemosyne\Documentation\Generator\ErrorDiagnostic;
use SineFine\Mnemosyne\Documentation\Generator\ErrorReport;

final class ErrorOutputFormatterTest extends TestCase
{
    public function testFormatEmptyReport(): void
    {
        $formatter = new ErrorOutputFormatter();
        $report = new ErrorReport();
        $this->assertSame('', $formatter->format($report));
    }

    public function testFormatWithErrorsAndWarnings(): void
    {
        $formatter = new ErrorOutputFormatter();
        $diag1 = new ErrorDiagnostic(ErrorDiagnostic::ERROR, 'Error 1', 'file1.php', 10);
        $diag2 = new ErrorDiagnostic(ErrorDiagnostic::WARNING, 'Warning 1', 'file2.php');
        $report = new ErrorReport([$diag1, $diag2]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('--- Errors (1 error, 1 warning) ---', $output);
        $this->assertStringContainsString('Error: [file1.php:10] Error 1', $output);
        $this->assertStringContainsString('Warning: [file2.php] Warning 1', $output);
        $this->assertStringEndsWith("---\n", $output);
    }
}
