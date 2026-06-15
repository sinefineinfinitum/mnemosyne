<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class PhpTypeParser
{
    /**
     * @var list<string> shortcut for built-in type lookup
     */
    private const BUILTIN_TYPES = [
        'string', 'int', 'float', 'bool', 'array', 'void', 'null',
        'object', 'mixed', 'never', 'true', 'false',
        'self', 'parent', 'static', 'iterable', 'callable',
    ];

    /**
     * @return list<string>
     */
    public function extractClassTypes(string $type): array
    {
        $type = ltrim($type, '?');
        $parts = preg_split('/[|&]/', $type);
        if ($parts === false) {
            return [];
        }
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || in_array(strtolower($part), self::BUILTIN_TYPES, true)) {
                continue;
            }
            if ($part[0] === '\\') {
                $part = substr($part, 1);
            }
            $result[] = $part;
        }
        return $result;
    }

    public function isNullable(string $type): bool
    {
        return str_starts_with($type, '?') || stripos($type, '|null') !== false || stripos($type, 'null|') !== false;
    }
}
