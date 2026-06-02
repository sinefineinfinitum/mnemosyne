<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

final class CrossReference
{
    /**
     * @param array<string, string>     $dependencies
     * @param array<string, string>     $usedByLinks
     * @param callable(string): ?string $typeLinkResolver
     */
    public function __construct(
        private array $dependencies = [],
        private array $usedByLinks = [],
        private $typeLinkResolver = null
    ) {
        if ($this->typeLinkResolver === null) {
            $this->typeLinkResolver = fn(string $fqn): ?string => null;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @param array<string, string> $dependencies
     */
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    /**
     * @return array<string, string>
     */
    public function getUsedByLinks(): array
    {
        return $this->usedByLinks;
    }

    /**
     * @param array<string, string> $usedByLinks
     */
    public function setUsedByLinks(array $usedByLinks): void
    {
        $this->usedByLinks = $usedByLinks;
    }

    /**
     * @return callable(string): ?string
     */
    public function getTypeLinkResolver(): callable
    {
        return $this->typeLinkResolver;
    }

    /**
     * @param callable(string): ?string $typeLinkResolver
     */
    public function setTypeLinkResolver(callable $typeLinkResolver): void
    {
        $this->typeLinkResolver = $typeLinkResolver;
    }
}
