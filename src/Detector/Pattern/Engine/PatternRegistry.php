<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Engine;

use SineFine\Mnemosyne\Detector\Pattern\Catalog\PatternInterface;

final class PatternRegistry
{
    /** @var PatternInterface[] */
    private array $patterns = [];

    /**
     * @param PatternInterface[] $patterns
     */
    public function __construct(array $patterns = [])
    {
        foreach ($patterns as $pattern) {
            $this->register($pattern);
        }
    }

    public function register(PatternInterface $pattern): void
    {
        $this->patterns[$pattern->name()] = $pattern;
    }

    public function get(string $name): ?PatternInterface
    {
        return $this->patterns[$name] ?? null;
    }

    /**
     * @return PatternInterface[]
     */
    public function all(): array
    {
        return array_values($this->patterns);
    }
}
