<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Linker;

use PhpParser\NodeTraverser;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\ParserException;
use SineFine\Ponymator\Analyzer\Visitor\CrossReferenceScannerVisitor;
use SineFine\Ponymator\Documentation\Processor\ErrorDiagnostic;
use SineFine\Ponymator\Documentation\Processor\GenerationResult;
use SineFine\Ponymator\Filesystem\PathResolver;
use Throwable;

final class CrossReferenceIndexBuilder
{
    public function __construct(
        private Parser $parser,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param string[] $sourceFiles
     */
    public function build(array $sourceFiles, ?GenerationResult $result = null): CrossReferenceContext
    {
        $index = new CrossReferenceIndex();
        $allEntityFqns = [];
        $fqnToDocPath = [];

        foreach ($sourceFiles as $relativePath) {
            $sourcePath = $this->pathResolver->sourcePath($relativePath);
            try {
                $ast = $this->parser->parseFile($sourcePath);
                $traverser = new NodeTraverser();
                $scanner = new CrossReferenceScannerVisitor();
                $traverser->addVisitor($scanner);
                $traverser->traverse($ast);

                foreach ($scanner->getPairs() as [$referencedFqn, $referencingFqn]) {
                    $index->addReference($referencedFqn, $referencingFqn);
                }
                $fileDocPath = $this->pathResolver->docRelativePath($relativePath);
                foreach ($scanner->getEntityFqns() as $fqn) {
                    $allEntityFqns[] = $fqn;
                    $fqnToDocPath[$fqn] = $fileDocPath;
                }
            } catch (ParserException $e) {
                if ($result !== null) {
                    $result->addError(
                        new ErrorDiagnostic(
                            severity: ErrorDiagnostic::ERROR,
                            message: 'Cross-file scan failed for ' . $relativePath . ' — ' . $e->getMessage(),
                            filePath: $relativePath,
                            exception: $e,
                        )
                    );
                }
            } catch (Throwable $e) {
                if ($result !== null) {
                    $result->addError(
                        new ErrorDiagnostic(
                            severity: ErrorDiagnostic::WARNING,
                            message: 'Cross-file scan failed for ' . $relativePath . ' — ' . $e->getMessage(),
                            filePath: $relativePath,
                            exception: $e,
                        )
                    );
                }
            }
        }

        $index->freeze(array_values(array_unique($allEntityFqns)));

        return new CrossReferenceContext($index, $fqnToDocPath);
    }
}
