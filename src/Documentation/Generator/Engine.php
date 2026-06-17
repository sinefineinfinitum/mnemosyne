<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\ParserException;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Comparator\HashGenerator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Filesystem\FileSystemException;
use SineFine\Ponymator\Filesystem\PathResolver;
use Throwable;

final class Engine
{
    public function __construct(
        private HashComparator               $hashComparator,
        private PathResolver                 $pathResolver,
        private PageGenerator                $documenter,
        private OutdatedDocumentationRemover $outdatedRemover,
        private CrossReferenceIndexBuilder   $indexBuilder,
    ) {
    }

    /**
     * @param string[] $sourceFiles
     */
    public function generateFull(array $sourceFiles): GenerationResult
    {
        return $this->generate($sourceFiles, false);
    }

    /**
     * @param string[] $sourceFiles
     */
    public function generateDiff(array $sourceFiles): GenerationResult
    {
        $result = $this->generate($sourceFiles, true);
        $this->outdatedRemover->remove($sourceFiles);
        return $result;
    }

    /**
     * @param string[] $sourceFiles
     */
    private function generate(array $sourceFiles, bool $diffMode): GenerationResult
    {
        $result = new GenerationResult();

        $targetDir = $this->pathResolver->targetDir();

        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new FileSystemException("Could not create target directory: $targetDir");
            }
        }

        $context = $this->indexBuilder->build($sourceFiles, $result);
        $this->documenter->setContext($context);

        foreach ($sourceFiles as $relativePath) {
            $sourcePath = $this->pathResolver->sourcePath($relativePath);
            $docPath = $this->pathResolver->docPath($relativePath);

            try {
                $docDir = dirname($docPath);
                if (!is_dir($docDir)) {
                    if (!mkdir($docDir, 0755, true) && !is_dir($docDir)) {
                        throw new FileSystemException("Could not create output directory: $docDir");
                    }
                }

                $content = $this->documenter->document($sourcePath, $relativePath);

                if ($diffMode) {
                    $newHash = $this->bodyHash($content);
                    $storedHash = $this->hashComparator->extractStoredHash($docPath);
                    if ($newHash === $storedHash) {
                        $result->incrementUnchanged();
                        continue;
                    }
                }

                file_put_contents($docPath, $content);
                $result->incrementGenerated();
                echo "  $relativePath\n";

            } catch (FileSystemException $e) {
                $result->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::ERROR,
                        message: $e->getMessage(),
                    )
                );
                break;
            } catch (ParserException $e) {
                $result->incrementSkipped();
                $result->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::ERROR,
                        message: 'Failed to parse ' . $relativePath . ' — ' . $e->getMessage(),
                        filePath: $relativePath,
                        exception: $e,
                    )
                );
            } catch (Throwable $e) {
                $result->incrementSkipped();
                $result->addError(
                    new ErrorDiagnostic(
                        severity: ErrorDiagnostic::WARNING,
                        message: 'Skipped ' . $relativePath . ' — ' . $e->getMessage(),
                        filePath: $relativePath,
                        exception: $e,
                    )
                );
            }
        }

        return $result;
    }

    private function bodyHash(string $fullDocument): string
    {
        $body = $fullDocument;
        if (str_starts_with($body, "---\n")) {
            $end = strpos($body, "\n---\n", 4);
            if ($end !== false) {
                $body = substr($body, $end + 5);
            }
        }
        return HashGenerator::shortHash($body);
    }
}
