<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show;

interface EntityRenderer
{
    public function render(EntityView $view): string;
}
