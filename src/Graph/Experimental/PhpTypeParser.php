<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Graph\Experimental;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class PhpTypeParser
{
    /**
     * @var list<string> shortcut for built-in type lookup
     */
    public const BUILTIN_TYPES = [
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

    /**
     * Parse a type string into atomic types.
     *
     * Given "string|int|null" returns:
     *   [ ['name'=>'string', 'is_union'=>true,  'is_intersection'=>false, 'position'=>0],
     *     ['name'=>'int',    'is_union'=>true,  'is_intersection'=>false, 'position'=>1],
     *     ['name'=>'null',   'is_union'=>true,  'is_intersection'=>false, 'position'=>2] ]
     *
     * Given "App\Entity\User" returns:
     *   [ ['name'=>'App\Entity\User', 'is_union'=>false, 'is_intersection'=>false, 'position'=>0] ]
     *
     * Given "?string" (PHP 8.0 nullable shorthand) returns:
     *   [ ['name'=>'string', 'is_union'=>true, 'is_intersection'=>false, 'position'=>0],
     *     ['name'=>'null',   'is_union'=>true, 'is_intersection'=>false, 'position'=>1] ]
     *
     * @return list<array{name: string, is_union: bool, is_intersection: bool, position: int}>
     */
    public function parseAtomicTypes(string $type): array
    {
        if (str_starts_with($type, '?')) {
            $type = substr($type, 1) . '|null';
        }

        if (str_contains($type, '|')) {
            $parts = explode('|', $type);
            $isUnion = true;
            $isIntersection = false;
        } elseif (str_contains($type, '&')) {
            $parts = explode('&', $type);
            $isUnion = false;
            $isIntersection = true;
        } else {
            $parts = [$type];
            $isUnion = false;
            $isIntersection = false;
        }

        $result = [];
        foreach ($parts as $position => $part) {
            $result[] = [
                'name' => trim($part),
                'is_union' => $isUnion,
                'is_intersection' => $isIntersection,
                'position' => $position,
            ];
        }

        return $result;
    }
}
