<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Linker;

final class TypeInfo
{
    /**
     * @param string       $fqcn
     * @param string       $kind
     * @param list<string> $methods
     * @param list<string> $properties
     * @param list<string> $constants
     * @param list<string> $caseNames
     */
    public function __construct(
        public string $fqcn,
        public string $kind,
        public array $methods = [],
        public array $properties = [],
        public array $constants = [],
        public array $caseNames = [],
    ) {
    }

    public function hasMember(string $name): bool
    {
        return in_array($name, $this->methods, true)
            || in_array($name, $this->properties, true)
            || in_array($name, $this->constants, true)
            || in_array($name, $this->caseNames, true);
    }
}
