<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Ast;

final class CallNode
{
    public const MARKER_STRONG = '*';
    public const MARKER_WEAK = '?';
    public const TYPE_STATIC = 'static';
    public const TYPE_DYNAMIC = 'dynamic';
    public const TYPE_GLOBAL = 'global';

    public function __construct(
        public string $type,
        public string $targetFQCN,
        public string $targetMethod,
        public string $marker
    ) {
    }

    public static function parseCall(string $line): ?self
    {
        $line = trim($line);
        if ($line === '' || ($line[0] !== self::MARKER_STRONG && $line[0] !== self::MARKER_WEAK)) {
            return null;
        }

        $marker = $line[0] === self::MARKER_STRONG ? 'strong' : 'weak';
        $target = trim(substr($line, 1));
        if ($target === '') {
            return null;
        }

        if (str_contains($target, '::')) {
            [$fqcn, $method] = explode('::', $target, 2);
            return new self(self::TYPE_STATIC, $fqcn, $method, $marker);
        }

        if (str_contains($target, '->')) {
            [$fqcn, $method] = explode('->', $target, 2);
            return new self(self::TYPE_DYNAMIC, $fqcn, $method, $marker);
        }

        return new self(self::TYPE_GLOBAL, '', $target, $marker);
    }
}
