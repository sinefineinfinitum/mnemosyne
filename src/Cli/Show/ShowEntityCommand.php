<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show;

use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;

final class ShowEntityCommand
{
    public function execute(Command $cmd, GraphQuery $query): void
    {
        $resolver = new EntityResolver();
        $resolver->resolve($cmd->namedArgs['entity'], $query);

        $view = EntityView::load($resolver->lastResolvedFqn(), $query);
        echo (new ConsoleRenderer())->render($view);
    }
}
