<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

final class FileRenderer
{
    public function __construct(
        private MarkdownBuilder $builder,
    ) {
    }

    /**
     * @param string                           $relativePath
     * @param array<int, array<string, mixed>> $functions
     * @param string[]                         $globals
     * @param array<int, array<string, mixed>> $constants
     */
    public function renderFile(string $relativePath, array $functions, array $globals, array $constants, string $sourceHash): string
    {
        $content = '';
        if (!empty($functions)) {
            $content .= $this->builder->section('Global functions', 3, $this->functionsList($functions));
        }
        if (!empty($globals)) {
            $content .= $this->builder->section('Global variables', 3, $this->globalsList($globals));
        }
        if (!empty($constants)) {
            $content .= $this->builder->section('Global constants', 3, $this->constantsList($constants));
        }

        $hash = hash('sha256', $content);
        $md = $this->builder->frontmatter(
            [
            'type' => 'file',
            'hash' => $hash,
            'source_hash' => $sourceHash,
            ]
        );

        $md .= "\n";
        $md .= $this->builder->header(1, '`' . $relativePath . '`');
        $md .= "\n";

        $md .= $content;

        return $md;
    }

    /**
     * @param array<int, array<string, mixed>> $constants
     */
    private function constantsList(array $constants): string
    {
        $rows = [];
        foreach ($constants as $c) {
            $value = $c['value'] ?? '—';
            $rows[] = ['`' . $c['name'] . '`', '`' . $value . '`'];
        }
        return $this->builder->table(['Name', 'Value'], $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $functions
     */
    private function functionsList(array $functions): string
    {
        $result = '';
        foreach ($functions as $fn) {
            $sig = $this->builder->methodSignature($fn);

            $result .= $this->builder->header(4, $fn['name']);
            $result .= $this->builder->codeBlock($sig, 'php');
        }
        return $result;
    }

    /**
     * @param string[] $globals
     */
    private function globalsList(array $globals): string
    {
        $result = '';
        foreach ($globals as $global) {
            $result .= $this->builder->listItem('`$' . $global . '`');
        }
        return $result;
    }
}
