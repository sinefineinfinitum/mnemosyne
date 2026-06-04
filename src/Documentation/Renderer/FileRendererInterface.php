<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

interface FileRendererInterface
{
    /**
     * @param string                           $relativePath
     * @param array<int, array<string, mixed>> $functions
     * @param string[]                         $globals
     * @param array<int, array<string, mixed>> $constants
     */
    public function renderFile(string $relativePath, array $functions, array $globals, array $constants): string;
}
