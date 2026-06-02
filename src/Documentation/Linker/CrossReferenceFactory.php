<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Linker;

use SineFine\Ponymator\Analyzer\CombinedAnalysisResult;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceContext;
use SineFine\Ponymator\Filesystem\PathResolver;

final class CrossReferenceFactory
{
    public function __construct(
        private PathResolver $pathResolver,
    ) {
    }

    public function create(
        CombinedAnalysisResult $analysis,
        ?CrossReferenceContext $context,
        string $relativePath,
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
