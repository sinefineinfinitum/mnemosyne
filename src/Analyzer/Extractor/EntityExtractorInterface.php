<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Extractor;

use PhpParser\Node;

interface EntityExtractorInterface
{
    /**
     * @param  Node $node
     * @return bool
     */
    public function supports(Node $node): bool;

    /**
     * @param  Node $node
     * @return array<string, mixed>
     */
    public function extract(Node $node): array;
}
