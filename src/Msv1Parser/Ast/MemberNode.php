<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Ast;

use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

class MemberNode
{
    public const SYMBOLS = ['$', '!', '.', '~'];
    public const VISIBILITY_MAP = [
        '+' => 'public',
        '-' => 'private',
        '#' => 'protected',
    ];
    public const INSTANTIATION_MARKER = '^';
    public const RETURN_TYPE_MARKER = ':';

    public ?string $visibility = null;
    public ?string $returnType = null;
    public ?string $dataType = null;
    public ?string $value = null;
    /**
     * @var ParameterNode[]
     */
    public array $parameters = [];
    /**
     * @var string[]
     */
    public array $attributes = [];
    /**
     * @var string[]
     */
    public array $creates = [];
    /**
     * @var CallNode[]
     */
    public array $calls = [];

    public function __construct(
        public string $name,
        public string $type,
        public EntityNode $parent
    ) {
    }

    public static function isValidSymbol(string $char): bool
    {
        return in_array($char, self::SYMBOLS, true);
    }

    public static function hasVisibility(string $sign): bool
    {
        return isset(self::VISIBILITY_MAP[$sign]);
    }

    public static function resolveVisibility(string $char): ?string
    {
        return self::VISIBILITY_MAP[$char] ?? null;
    }

    public static function resolveType(string $symbol, EntityNode $entity): string
    {
        return match ($symbol) {
            '$' => $entity->isFile() ? 'global_variable' : 'property',
            '!' => 'constant',
            '.' => $entity->isFile() ? 'function' : 'method',
            '~' => 'enum_case',
            default => throw new SyntaxException("Invalid symbol for member type: $symbol"),
        };
    }

    /**
     * Strips an optional visibility prefix ("+", "-", "#") from the start of
     * a member declaration body and returns the visibility name (e.g. "public")
     * plus the remaining body.
     *
     * Returns visibility=null when the body does not begin with a visibility sigil.
     *
     * @return array{visibility: ?string, body: string}
     */
    public static function parseVisibilityPrefix(string $line): array
    {
        if ($line === '' || !isset(self::VISIBILITY_MAP[$line[0]])) {
            return ['visibility' => null, 'body' => $line];
        }
        return [
            'visibility' => self::VISIBILITY_MAP[$line[0]],
            'body' => substr($line, 1),
        ];
    }

    /**
     * Detects a method-child directive marker (instantiation "^" or return type ":")
     * at the start of an indented line and returns the marker plus the trimmed body.
     *
     * Returns null if the line does not begin with a recognized child marker;
     * the caller is then expected to handle the parameter directive ("$…") case.
     *
     * @return array{marker: string, body: string}|null
     */
    public static function parseChildDirective(string $line): ?array
    {
        if (str_starts_with($line, self::INSTANTIATION_MARKER)) {
            return [
                'marker' => self::INSTANTIATION_MARKER,
                'body' => trim(substr($line, 1)),
            ];
        }

        if (str_starts_with($line, self::RETURN_TYPE_MARKER)) {
            return [
                'marker' => self::RETURN_TYPE_MARKER,
                'body' => trim(substr($line, 1)),
            ];
        }

        return null;
    }
}
