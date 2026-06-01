<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

use SineFine\Ponymator\Analyzer\Link\CrossReferenceContext;
use SineFine\Ponymator\Analyzer\Link\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Comparator\HashGenerator;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Filesystem\PathResolver;
use Throwable;

final class MarkdownGenerator
{
    private const VENDOR_INDEX_PATH = 'vendor.md';

    public function __construct(
        private HashComparator $hashComparator,
        private PathResolver $pathResolver,
        private FileDocumenter $documenter,
        private OutdatedDocumentationRemover $outdatedRemover,
        private CrossReferenceIndexBuilder $indexBuilder,
        private ?VendorIndexGenerator $vendorIndexGenerator = null,
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
        $extraPaths = $this->vendorIndexGenerator !== null ? [self::VENDOR_INDEX_PATH] : [];
        $this->outdatedRemover->remove($sourceFiles, $extraPaths);
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
            mkdir($targetDir, 0755, true);
        }

        $context = $this->indexBuilder->build($sourceFiles);
        $this->documenter->setContext($context);

        foreach ($sourceFiles as $relativePath) {
            $sourcePath = $this->pathResolver->sourcePath($relativePath);
            $docPath = $this->pathResolver->docPath($relativePath);

            try {
                $docDir = dirname($docPath);
                if (!is_dir($docDir)) {
                    mkdir($docDir, 0755, true);
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

            } catch (Throwable $e) {
                fwrite(STDERR, "Warning: Skipped $relativePath — " . $e->getMessage() . "\n");
                $result->incrementSkipped();
                $result->addError($relativePath);
            }
        }

        $this->generateVendorIndex($targetDir, $context, $diffMode, $result);

        return $result;
    }

    private function generateVendorIndex(string $targetDir, CrossReferenceContext $context, bool $diffMode, GenerationResult $result): void
    {
        if ($this->vendorIndexGenerator === null) {
            return;
        }

        $vendorPath = $targetDir . '/' . self::VENDOR_INDEX_PATH;

        try {
            $content = $this->vendorIndexGenerator->generate($context);

            if ($diffMode) {
                $newHash = $this->bodyHash($content);
                $storedHash = $this->hashComparator->extractStoredHash($vendorPath);
                if ($newHash === $storedHash) {
                    $result->incrementUnchanged();
                    return;
                }
            }

            file_put_contents($vendorPath, $content);
            $result->incrementGenerated();
            echo '  ' . self::VENDOR_INDEX_PATH . "\n";

        } catch (Throwable $e) {
            fwrite(STDERR, "Warning: Skipped vendor index — " . $e->getMessage() . "\n");
            $result->incrementSkipped();
        }
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
