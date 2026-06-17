<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class NamespaceResolver
{
    /**
     * @var array<string, int> fqn => id
     */
    private array $namespaceIds = [];

    public function __construct(
        private GraphCommand $command,
        private GraphQuery $query,
    ) {
    }

    public function ensure(string $fqn): int
    {
        if (isset($this->namespaceIds[$fqn])) {
            return $this->namespaceIds[$fqn];
        }

        $existing = $this->query->findNamespaceId($fqn);
        if ($existing !== null) {
            $this->namespaceIds[$fqn] = $existing;
            return $existing;
        }

        $parts = explode('\\', $fqn);
        $label = end($parts);
        $depth = count($parts) - 1;

        $parentId = null;
        if (count($parts) > 1) {
            $parentFqn = implode('\\', array_slice($parts, 0, -1));
            $parentId = $this->ensure($parentFqn);
        }

        $id = $this->command->insertNamespace($fqn, $label, $parentId, $depth);
        $this->namespaceIds[$fqn] = $id;
        return $id;
    }

    public static function extractFromFqn(string $fqn): ?string
    {
        $pos = strrpos($fqn, '\\');
        if ($pos === false) {
            return null;
        }
        return substr($fqn, 0, $pos);
    }

    public static function extractShortName(string $fqn): string
    {
        $pos = strrpos($fqn, '\\');
        if ($pos === false) {
            return $fqn;
        }
        return substr($fqn, $pos + 1);
    }
}
