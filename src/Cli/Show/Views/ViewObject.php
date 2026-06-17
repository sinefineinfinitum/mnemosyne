<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show\Views;

interface ViewObject
{
    public function render(): string;
}
