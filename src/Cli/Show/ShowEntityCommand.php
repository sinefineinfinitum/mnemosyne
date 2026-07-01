<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show;

use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Cli\Error\ExitCode;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

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
