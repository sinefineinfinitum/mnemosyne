<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Documentation\Generator\ErrorDiagnostic;

final class ErrorDiagnosticTest extends TestCase
{
    public function testCanCreateErrorDiagnostic(): void
    {
        $diag = new ErrorDiagnostic(
            severity: ErrorDiagnostic::ERROR,
            message: 'Something went wrong',
            filePath: 'src/Foo.php',
            lineNumber: 42,
            suggestion: 'Check syntax',
        );

        $this->assertSame(ErrorDiagnostic::ERROR, $diag->severity);
        $this->assertSame('Something went wrong', $diag->message);
        $this->assertSame('src/Foo.php', $diag->filePath);
        $this->assertSame(42, $diag->lineNumber);
        $this->assertSame('Check syntax', $diag->suggestion);
    }

    public function testMinimalWarningDiagnostic(): void
    {
        $diag = new ErrorDiagnostic(
            severity: ErrorDiagnostic::WARNING,
            message: 'Minor issue',
        );

        $this->assertSame(ErrorDiagnostic::WARNING, $diag->severity);
        $this->assertSame('Minor issue', $diag->message);
        $this->assertNull($diag->filePath);
        $this->assertNull($diag->lineNumber);
        $this->assertNull($diag->suggestion);
    }

    public function testConstructorThrowsOnEmptyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message must be non-empty');
        new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: '');
    }

    public function testConstructorThrowsOnInvalidSeverity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Severity must be Error or Warning');
        new ErrorDiagnostic(severity: 'Invalid', message: 'test');
    }

    public function testConstructorThrowsOnEmptyFilePath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FilePath must be non-empty if provided');
        new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'test', filePath: '');
    }

    public function testConstructorThrowsOnInvalidLineNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LineNumber must be >= 1 if provided');
        new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'test', lineNumber: 0);
    }

    public function testConstructorThrowsOnNegativeLineNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LineNumber must be >= 1 if provided');
        new ErrorDiagnostic(severity: ErrorDiagnostic::ERROR, message: 'test', lineNumber: -5);
    }
}
