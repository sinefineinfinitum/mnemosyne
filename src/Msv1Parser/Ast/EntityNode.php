<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Ast;

use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

class EntityNode
{
    public const ENTITY_START = '@';
    public const TYPES = [
        'class',
        'interface',
        'trait',
        'file',
        'enum',
    ];
    public const RELATION_MARKERS = ['>', '<', '%'];

    /**
     * @var string[]
     */
    public array $attributes = [];
    /**
     * @var MemberNode[]
     */
    public array $members = [];
    /**
     * @var string[]
     */
    public array $extends = [];
    /**
     * @var string[]
     */
    public array $implements = [];
    /**
     * @var string[]
     */
    public array $traits = [];
    public ?EntityNode $parent = null;

    public function __construct(
        public string $type,
        public string $name,
    ) {
    }

    public static function detectType(string $trimmedLine): ?string
    {
        foreach (self::TYPES as $type) {
            if (str_starts_with($trimmedLine, '@' . $type)) {
                return $type;
            }
        }
        return null;
    }

    public static function isRelationMarker(string $char): bool
    {
        return in_array($char, self::RELATION_MARKERS, true);
    }


    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function canHaveRelations(): bool
    {
        return !$this->isFile();
    }

    public function canHaveVisibility(): bool
    {
        return !$this->isFile();
    }

    public function canHaveEnumCases(): bool
    {
        return !$this->isFile();
    }

    public function addRelation(string $marker, string $target): void
    {
        match ($marker) {
            '>' => $this->extends[] = $target,
            '<' => $this->implements[] = $target,
            '%' => $this->traits[] = $target,
            default => throw new SyntaxException("Invalid relation marker: $marker"),
        };
    }
}
