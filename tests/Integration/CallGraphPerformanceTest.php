<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Filesystem\FileLoader;
use SineFine\Mnemosyne\Msv1Parser\Parser;

final class CallGraphPerformanceTest extends TestCase
{
    public function testParseCallGraphCompletesInUnder500ms(): void
    {
        $parser = new Parser();
        $loader = new FileLoader();
        $files = glob(__DIR__ . '/../Fixtures/docs/*.msv1');
        $this->assertNotEmpty($files);

        $start = microtime(true);
        foreach ($files as $file) {
            $content = $loader->load($file);
            $parser->parse($content);
        }
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500.0, $elapsed, sprintf('Parsing took %.2fms, expected < 500ms', $elapsed));
    }
}
