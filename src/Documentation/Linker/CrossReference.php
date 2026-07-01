<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Linker;

use SineFine\Mnemosyne\Analyzer\CallInfo;

final class CrossReference
{
    /**
     * @param array<string, string>         $dependencies
     * @param array<string, string>         $usedByLinks
     * @param callable(string): ?string     $typeLinkResolver
     * @param array<string, list<string>>   $creates
     * @param array<string, list<CallInfo>> $calls            methodName => list<CallInfo>
     */
    public function __construct(
        private array $dependencies = [],
        private array $usedByLinks = [],
        private $typeLinkResolver = null,
        private array $creates = [],
        private array $calls = [],
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
     * @return array<string, string>
     */
    public function getUsedByLinks(): array
    {
        return $this->usedByLinks;
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

    /**
     * @return array<string, list<string>>
     */
    public function getCreates(): array
    {
        return $this->creates;
    }

    /**
     * @return array<string, list<CallInfo>>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
