<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Cli\Detect;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\HelpPrinter;

final class HelpPrinterDetectTest extends TestCase
{
    public function testPrintDetectHelp(): void
    {
        ob_start();
        HelpPrinter::printDetectHelp();
        $output = ob_get_clean();

        $this->assertStringContainsString('mnemosyne detect', $output);
        $this->assertStringContainsString('--db-path', $output);
        $this->assertStringContainsString('--config', $output);
        $this->assertStringContainsString('--help', $output);
    }

    public function testMainHelpIncludesDetect(): void
    {
        ob_start();
        HelpPrinter::printHelp();
        $output = ob_get_clean();

        $this->assertStringContainsString('detect', $output);
        $this->assertStringContainsString('Detect design patterns', $output);
    }
}
