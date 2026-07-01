<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\Markdown;

use SineFine\Mnemosyne\Comparator\HashGenerator;
use SineFine\Mnemosyne\Documentation\Renderer\FileRendererInterface;

final class FileRenderer implements FileRendererInterface
{
    public function __construct(
        private MarkdownBuilder $builder,
    ) {
    }

    /**
     * @param  string                                                     $relativePath
     * @param  array<int, array<string, mixed>>                           $functions
     * @param  string[]                                                   $globals
     * @param  array<int, array<string, mixed>>                           $constants
     * @param  array<string, list<\SineFine\Mnemosyne\Analyzer\CallInfo>> $fileCalls    functionName => list<CallInfo>
     * @return string
     */
    public function renderFile(
        string $relativePath,
        array $functions,
        array $globals,
        array $constants,
        array $fileCalls = []
    ): string {
        $content = '';
        if (!empty($functions)) {
            $content .= $this->builder->section('Global functions', 3, $this->functionsList($functions, $fileCalls));
        }
        if (!empty($globals)) {
            $content .= $this->builder->section('Global variables', 3, $this->globalsList($globals));
        }
        if (!empty($constants)) {
            $content .= $this->builder->section('Global constants', 3, $this->constantsList($constants));
        }

        $hash = HashGenerator::shortHash($content);
        $md = $this->builder->frontmatter(
            [
            'type' => 'file',
            'hash' => $hash,
            ]
        );

        $md .= "\n";
        $md .= $this->builder->header(1, '`' . $relativePath . '`');
        $md .= "\n";

        $md .= $content;

        return $md;
    }

    /**
     * @param  array<int, array<string, mixed>>                           $functions
     * @param  array<string, list<\SineFine\Mnemosyne\Analyzer\CallInfo>> $fileCalls
     * @return string
     */
    private function functionsList(array $functions, array $fileCalls = []): string
    {
        $result = '';
        $linkResolver = fn(string $fqn): ?string => null;
        foreach ($functions as $fn) {
            $sig = $this->builder->methodSignature($fn);
            $fnName = $fn['name'];

            $result .= $this->builder->header(4, $fnName);
            $result .= $this->builder->codeBlock($sig, 'php');

            $calls = $fileCalls[$fnName] ?? [];
            $methodCalls = array_values(
                array_filter(
                    $calls,
                    fn(\SineFine\Mnemosyne\Analyzer\CallInfo $c) => $c->kind !== \SineFine\Mnemosyne\Analyzer\CallInfo::KIND_CREATE
                )
            );

            if (!empty($methodCalls)) {
                $result .= $this->builder->listItem('**Calls:**');
                foreach ($methodCalls as $callInfo) {
                    $assocLabel = $callInfo->association === \SineFine\Mnemosyne\Analyzer\CallInfo::STRONG ? 'strong' : 'weak';
                    $prefix = $this->builder->inlineCode($assocLabel);

                    $resolved = $callInfo->resolvedTargetFqcn;
                    if ($resolved === null) {
                        $result .= $this->builder->listItem($prefix . ' ' . $this->builder->inlineCode($callInfo->targetName), '  -');
                    } else {
                        $result .= $this->builder->listItem($prefix . ' ' . $this->builder->inlineCode($resolved), '  -');
                    }
                }
            }
        }
        return $result;
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
