<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Detect;

use PDO;
use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\AbstractFactory;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Adapter;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Bridge;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Builder;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\ChainOfResponsibility;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Command as CommandPattern;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Composite;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Decorator;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\FactoryMethod;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Flyweight;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Iterator;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Mediator;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Memento;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Observer;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Prototype;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Proxy;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Singleton;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\State;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Strategy;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\TemplateMethod;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Visitor;
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
            new AbstractFactory(),
            new Adapter(),
            new Bridge(),
            new Builder(),
            new ChainOfResponsibility(),
            new CommandPattern(),
            new Composite(),
            new Decorator(),
            new FactoryMethod(),
            new Flyweight(),
            new Iterator(),
            new Mediator(),
            new Memento(),
            new Observer(),
            new Prototype(),
            new Proxy(),
            new Singleton(),
            new State(),
            new Strategy(),
            new TemplateMethod(),
            new Visitor(),
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
