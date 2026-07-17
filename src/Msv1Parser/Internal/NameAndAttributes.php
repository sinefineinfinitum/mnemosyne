<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Internal;

/**
 * @internal
 */
final class NameAndAttributes
{
    /**
     * @param string   $name
     * @param string[] $attributes
     */
    public function __construct(
        public string $name,
        public array $attributes,
    ) {
    }
}
