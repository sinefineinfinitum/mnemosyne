<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Linker;

use SineFine\Mnemosyne\Analyzer\EntityAnalysisResult;
use SineFine\Mnemosyne\Analyzer\Linker\CrossReferenceContext;
use SineFine\Mnemosyne\Filesystem\PathResolver;

final class CrossReferenceFactory
{
    public function __construct(
        private PathResolver $pathResolver,
    ) {
    }

    public function create(
        EntityAnalysisResult   $analysis,
        ?CrossReferenceContext $context,
        string                 $relativePath,
    ): CrossReferenceProvider {
        $currentDocPath = $this->pathResolver->docRelativePath($relativePath);
        $linker = $context !== null
            ? new DocLinker($context->getFqnToDocPath(), $this->pathResolver)
            : null;

        return new CrossReferenceProvider(
            $analysis,
            $context,
            $linker,
            $currentDocPath
        );
    }
}
