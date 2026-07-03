<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Ast;

class ParameterNode
{
    public const BY_REF_MARKER = '&';
    public const VARIABLE_PREFIX = '$';

    public ?string $type = null;
    public bool $byRef = false;
    public bool $variadic = false;
    public ?string $value = null;

    public function __construct(
        public string $name,
    ) {
    }

    /**
     * Splits a parameter directive line into its by-reference flag,
     * variadic flag, and the declaration body (everything after the "$" sigil).
     *
     * Returns null if the line lacks the required "$" prefix
     * (with or without optional leading "&" or "...").
     *
     * @return array{byRef: bool, variadic: bool, body: string}|null
     */
    public static function parsePrefix(string $line): ?array
    {
        $byRef = false;
        $variadic = false;

        if (str_starts_with($line, self::BY_REF_MARKER)) {
            $byRef = true;
            $line = substr($line, 1);
        }

        if (str_starts_with($line, '...')) {
            $variadic = true;
            $line = substr($line, 3);
        }

        if (!str_starts_with($line, self::VARIABLE_PREFIX)) {
            return null;
        }

        return ['byRef' => $byRef, 'variadic' => $variadic, 'body' => substr($line, 1)];
    }
}
