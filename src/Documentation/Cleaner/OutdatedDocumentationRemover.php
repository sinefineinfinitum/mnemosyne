<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Cleaner;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SineFine\Ponymator\Filesystem\PathResolver;

final class OutdatedDocumentationRemover
{
    public function __construct(
        private PathResolver $paths
    ) {
    }

    /**
     * @param string[] $currentFiles
     */
    public function remove(array $currentFiles): int
    {
        $targetDir = $this->paths->targetDir();

        if (!is_dir($targetDir)) {
            return 0;
        }

        $currentDocs = [];
        foreach ($currentFiles as $relativePath) {
            $currentDocs[] = $this->paths->docRelativePath($relativePath);
        }

        $removed = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $docRelative = $this->paths->relativeTargetPath($file->getPathname());

            if (!in_array($docRelative, $currentDocs, true)) {
                unlink($file->getPathname());
                $removed++;
                echo "  Removed: $docRelative\n";
            }
        }

        return $removed;
    }
}
