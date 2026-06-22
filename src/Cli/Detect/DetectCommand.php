<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Detect;

use PDO;
use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Adapter;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Builder;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Decorator;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\FactoryMethod;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Singleton;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Strategy;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\TemplateMethod;
use SineFine\Mnemosyne\Detector\Pattern\Engine\PatternRegistry;
use SineFine\Mnemosyne\Detector\Pattern\Engine\Engine;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use Throwable;

final class DetectCommand
{
    /**
     * @throws Throwable
     */
    public function execute(Command $cmd, GraphQuery $query, ?PDO $readOnlyPdo = null): void
    {
        $registry = new PatternRegistry(
            [
            new Adapter(),
            new Builder(),
            new Decorator(),
            new FactoryMethod(),
            new Strategy(),
            new Singleton(),
            new TemplateMethod(),
            ]
        );

        $pdo = $query->getPdo();

        $engine = new Engine($registry, $pdo, $readOnlyPdo);
        $result = $engine->run();

        foreach ($result->errors as $error) {
            fwrite(STDERR, "Warning: $error\n");
        }

        $view = new PatternView($result, $query);

        $renderer = new ConsoleRenderer();
        $renderer->render($view->blocks);
    }
}
