<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Filesystem\PathResolver;
use Throwable;

final class MarkdownGenerator
{
    public function __construct(
        private HashComparator $hashComparator,
        private PathResolver $pathResolver,
        private FileDocumenter $documenter,
        private OutdatedDocumentationRemover $outdatedRemover,
    ) {
    }

    /**
     * @param string[] $sourceFiles
     */
    public function generateFull(array $sourceFiles): GenerationResult
    {
        return $this->generate($sourceFiles, true);
    }

    /**
     * @param string[] $sourceFiles
     */
    public function generateDiff(array $sourceFiles): GenerationResult
    {
        $result = $this->generate($sourceFiles, false);
        $this->outdatedRemover->remove($sourceFiles);
        return $result;
    }

    /**
     * @param string[] $sourceFiles
     */
    private function generate(array $sourceFiles, bool $force): GenerationResult
    {
        $result = new GenerationResult();

        $targetDir = $this->pathResolver->targetDir();

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $this->documenter->buildCrossReferences($sourceFiles);

        foreach ($sourceFiles as $relativePath) {
            $sourcePath = $this->pathResolver->sourcePath($relativePath);
            $docPath = $this->pathResolver->docPath($relativePath);

            if (!$force) {
                if (!$this->hashComparator->hasChanged($sourcePath, $docPath)) {
                    $result->incrementUnchanged();
                    continue;
                }
            }

            try {
                $docDir = dirname($docPath);
                if (!is_dir($docDir)) {
                    mkdir($docDir, 0755, true);
                }
                $content = $this->documenter->document($sourcePath, $relativePath);


                file_put_contents($docPath, $content);
                $result->incrementGenerated();
                echo "  $relativePath\n";

            } catch (Throwable $e) {
                fwrite(STDERR, "Warning: Skipped $relativePath — " . $e->getMessage() . "\n");
                $result->incrementSkipped();
                $result->addError($relativePath);
            }
        }
        return $result;
    }
}
