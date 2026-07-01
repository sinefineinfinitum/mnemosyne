<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show;

interface EntityRenderer
{
    public function render(EntityView $view): string;
}
