<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show;

use SineFine\Ponymator\Cli\Show\Views\EntityHeaderView;
use SineFine\Ponymator\Cli\Show\Views\ExtendsView;
use SineFine\Ponymator\Cli\Show\Views\ExternalView;
use SineFine\Ponymator\Cli\Show\Views\InheritorsView;
use SineFine\Ponymator\Cli\Show\Views\MembersView;
use SineFine\Ponymator\Cli\Show\Views\UsedByView;

final class ConsoleRenderer implements EntityRenderer
{
    public function render(EntityView $view): string
    {
        return
            (new EntityHeaderView($view))->render() .
            (new ExtendsView($view))->render() .
            (new InheritorsView($view))->render() .
            (new MembersView($view))->render() .
            (new UsedByView($view))->render() .
            (new ExternalView($view))->render();
    }
}
