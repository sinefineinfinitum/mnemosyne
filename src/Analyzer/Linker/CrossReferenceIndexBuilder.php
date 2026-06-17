<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Linker;

use FilesystemIterator;
use PhpParser\NodeTraverser;
use Ponymator\Parser\Ast\MemberNode;
use Ponymator\Parser\Parser as PsParser;
use Ponymator\Parser\SyntaxException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\ParserException;
use SineFine\Ponymator\Analyzer\Visitor\CrossReferenceScannerVisitor;
use SineFine\Ponymator\Documentation\Generator\ErrorDiagnostic;
use SineFine\Ponymator\Documentation\Generator\GenerationResult;
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
        $allFunctionFqns = [];
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
                foreach ($scanner->getFunctionFqns() as $fnFqn) {
                    $allFunctionFqns[] = $fnFqn;
                }
            } catch (ParserException $e) {
                $result?->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::ERROR,
                        message: 'Cross-file scan failed for ' . $relativePath . ' — ' . $e->getMessage(),
                        filePath: $relativePath,
                        exception: $e,
                    )
                );
            } catch (Throwable $e) {
                $result?->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::WARNING,
                        message: 'Cross-file scan failed for ' . $relativePath . ' — ' . $e->getMessage(),
                        filePath: $relativePath,
                        exception: $e,
                    )
                );
            }
        }

        $index->freeze(array_values(array_unique($allEntityFqns)));

        $typeIndex = $this->buildTypeIndex($result);

        return new CrossReferenceContext($index, $fqnToDocPath, $typeIndex, array_values(array_unique($allFunctionFqns)));
    }

    /**
     * @return array<string, TypeInfo>
     */
    private function buildTypeIndex(?GenerationResult $result): array
    {
        $psParser = new PsParser();
        $typeIndex = [];
        $targetDir = $this->pathResolver->targetDir();

        if (!is_dir($targetDir)) {
            return $typeIndex;
        }

        $files = $this->discoverPsv1Files($targetDir);

        foreach ($files as $psv1Path) {
            try {
                $document = $psParser->parseFile($psv1Path);
            } catch (SyntaxException $e) {
                $result?->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::WARNING,
                        message: 'PSV1 index build failed for ' . $psv1Path . ' — ' . $e->getMessage(),
                        filePath: $psv1Path,
                        exception: $e,
                    )
                );
                continue;
            } catch (Throwable $e) {
                $result?->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::WARNING,
                        message: 'PSV1 index build failed for ' . $psv1Path . ' — ' . $e->getMessage(),
                        filePath: $psv1Path,
                        exception: $e,
                    )
                );
                continue;
            }

            foreach ($document->entities as $entity) {
                if ($entity->type === 'file') {
                    continue;
                }
                $typeIndex[$entity->name] = $this->buildTypeInfo($entity->type, $entity->name, $entity->members);
            }
        }

        ksort($typeIndex);

        return $typeIndex;
    }

    /**
     * @param  string       $kind
     * @param  string       $fqcn
     * @param  MemberNode[] $members
     * @return TypeInfo
     */
    private function buildTypeInfo(string $kind, string $fqcn, array $members): TypeInfo
    {
        $methods = [];
        $properties = [];
        $constants = [];
        $caseNames = [];

        foreach ($members as $member) {
            switch ($member->type) {
            case 'method':
                $methods[] = $member->name;
                break;
            case 'property':
                $properties[] = $member->name;
                break;
            case 'constant':
                $constants[] = $member->name;
                break;
            case 'enum_case':
                $caseNames[] = $member->name;
                break;
            }
        }

        sort($methods);
        sort($properties);
        sort($constants);
        sort($caseNames);

        return new TypeInfo(
            fqcn: $fqcn,
            kind: $kind,
            methods: $methods,
            properties: $properties,
            constants: $constants,
            caseNames: $caseNames,
        );
    }

    /**
     * @return string[]
     */
    private function discoverPsv1Files(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'psv1') {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}
