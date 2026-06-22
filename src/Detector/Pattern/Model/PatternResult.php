<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Model;

final class PatternResult
{
    /**
     * @param PatternMatch[] $matches
     * @param list<string>   $errors
     */
    public function __construct(
        public array $matches,
        public array $errors = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
