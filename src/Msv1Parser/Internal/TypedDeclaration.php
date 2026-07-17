<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Internal;

/**
 * @internal
 */
final class TypedDeclaration
{
    public function __construct(
        public string $nameAndKeywords,
        public ?string $dataType,
        public ?string $value,
    ) {
    }
}
