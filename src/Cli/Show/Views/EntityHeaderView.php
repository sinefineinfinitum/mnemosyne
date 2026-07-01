<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show\Views;

use SineFine\Mnemosyne\Cli\Show\EntityView;

final class EntityHeaderView implements ViewObject
{
    public function __construct(
        private EntityView $view,
    ) {
    }

    public function render(): string
    {
        $entity = $this->view->entity;
        $modifiers = $this->view->modifiers;

        $output = 'Entity: ' . $entity['fqn'] . "\n";

        $typeLine = $entity['type'];
        if (!empty($modifiers)) {
            $typeLine .= ' [' . implode(', ', $modifiers) . ']';
        }
        $output .= 'Type: ' . $typeLine . "\n";

        if ($this->view->filePath !== null) {
            $output .= 'File: ' . $this->view->filePath . "\n";
        }

        return $output;
    }
}
