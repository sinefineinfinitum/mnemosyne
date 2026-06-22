<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Stub;

use SineFine\Mnemosyne\Detector\Pattern\Catalog\PatternInterface;

final class PatternInterfaceStub implements PatternInterface
{
    /**
     * @param string   $name
     * @param string[] $roles
     */
    public function __construct(
        private string $name,
        private array $roles,
        private string $sql = '',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function candidateSql(): string
    {
        return $this->sql;
    }
}
