<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;

class PSR4Detector
{
    private string $namespacePrefix;

    public function __construct(string $namespacePrefix = '')
    {
        $this->namespacePrefix = rtrim($namespacePrefix, '\\');
    }

    /**
     * @param array<int, Node> $ast
     */
    public function classify(array $ast, string $relativePath): string
    {
        $namespace = $this->extractNamespace($ast);

        if ($namespace === null) {
            return 'non-psr4';
        }

        $normalizedRelative = str_replace('\\', '/', $relativePath);
        $normalizedNamespace = str_replace('\\', '/', $namespace);

        $stripped = $normalizedNamespace;
        if ($this->namespacePrefix !== '') {
            $prefix = str_replace('\\', '/', $this->namespacePrefix);
            if (str_starts_with($normalizedNamespace, $prefix)) {
                $stripped = substr($normalizedNamespace, strlen($prefix));
            }
        }

        $expectedDir = ltrim($stripped, '/');
        $fileDir = dirname($normalizedRelative);
        if ($fileDir === '.') {
            $fileDir = '';
        }

        if ($expectedDir === $fileDir) {
            return 'psr4';
        }

        return 'non-psr4';
    }

    /**
     * @param array<int, Node> $ast
     */
    private function extractNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                return $node->name?->toCodeString();
            }
        }
        return null;
    }
}
