<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Linker;

use FilesystemIterator;
use PhpParser\NodeTraverser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SineFine\Mnemosyne\Analyzer\Parser;
use SineFine\Mnemosyne\Analyzer\ParserException;
use SineFine\Mnemosyne\Analyzer\Visitor\CrossReferenceScannerVisitor;
use SineFine\Mnemosyne\Documentation\Generator\ErrorDiagnostic;
use SineFine\Mnemosyne\Documentation\Generator\GenerationResult;
use SineFine\Mnemosyne\Filesystem\FileLoader;
use SineFine\Mnemosyne\Filesystem\PathResolver;
use SineFine\Mnemosyne\Msv1Parser\Ast\MemberNode;
use SineFine\Mnemosyne\Msv1Parser\Parser as PsParser;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;
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
        $loader = new FileLoader();
        $typeIndex = [];
        $targetDir = $this->pathResolver->targetDir();

        if (!is_dir($targetDir)) {
            return $typeIndex;
        }

        $files = $this->discoverMsv1Files($targetDir);

        foreach ($files as $msv1Path) {
            try {
                $content = $loader->load($msv1Path);
                $document = $psParser->parse($content);
                $document->sourcePath = $msv1Path;
                $document->sourceHash = hash('sha256', $content);
            } catch (SyntaxException $e) {
                $result?->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::WARNING,
                        message: 'MSV1 index build failed for ' . $msv1Path . ' — ' . $e->getMessage(),
                        filePath: $msv1Path,
                        exception: $e,
                    )
                );
                continue;
            } catch (Throwable $e) {
                $result?->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::WARNING,
                        message: 'MSV1 index build failed for ' . $msv1Path . ' — ' . $e->getMessage(),
                        filePath: $msv1Path,
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
    private function discoverMsv1Files(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'msv1') {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}
