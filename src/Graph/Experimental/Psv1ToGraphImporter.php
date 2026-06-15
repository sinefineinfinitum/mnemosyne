<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

use Ponymator\Parser\Ast\Document;
use Ponymator\Parser\Parser;
use RuntimeException;
use Throwable;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class Psv1ToGraphImporter
{
    private Parser $parser;
    private NamespaceResolver $namespaceResolver;
    private EntityGraphProcessor $entityProcessor;

    public function __construct(
        private GraphCommand $command,
        private GraphQuery $query,
    ) {
        $this->parser = new Parser();
        $this->namespaceResolver = new NamespaceResolver($command, $this->query);
        $this->entityProcessor = new EntityGraphProcessor($command, $this->namespaceResolver);
    }

    /**
     * @param  list<string> $filePaths
     * @throws Throwable
     */
    public function buildFromFiles(array $filePaths, ?string $basePath = null): void
    {
        $this->command->beginTransaction();
        $currentFilePath = null;
        try {
            foreach ($filePaths as $filePath) {
                $currentFilePath = $filePath;
                $document = $this->parser->parseFile($filePath);
                $this->processDocument($document, $basePath);
            }

            $this->command->resolvePendingTargets($this->entityProcessor->getEntityIds());
            $this->command->commit();
        } catch (Throwable $e) {
            $this->command->rollback();
            $message = $e->getMessage() . "\n" . "Graph import failed";
            if ($currentFilePath !== null) {
                $message .= " on file " . $currentFilePath;
            }
            throw new RuntimeException($message);
        }
    }

    private function processDocument(Document $document, ?string $basePath): void
    {
        $filePath = $document->sourcePath ?? '';
        $relativePath = null;
        if ($filePath !== '' && $basePath !== null && str_starts_with($filePath, $basePath)) {
            $relativePath = ltrim(substr($filePath, strlen($basePath)), '/\\');
        } elseif ($filePath !== '') {
            $relativePath = $filePath;
        }

        $fileId = $this->command->insertFile($filePath, $relativePath, $document->sourceHash);

        foreach ($document->entities as $entity) {
            $this->entityProcessor->processEntity($entity, $fileId);
        }
    }
}
