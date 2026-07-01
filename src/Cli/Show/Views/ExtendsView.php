<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show\Views;

use SineFine\Mnemosyne\Cli\Show\EntityView;

final class ExtendsView implements ViewObject
{
    public function __construct(
        private EntityView $view,
    ) {
    }

    public function render(): string
    {
        $output = '';
        $relations = $this->view->outgoingStructural;

        $parent = array_values(array_filter($relations, fn($r) => $r['type'] === 'extends'));
        $interfaces = array_values(array_filter($relations, fn($r) => $r['type'] === 'implements'));
        $traits = array_values(array_filter($relations, fn($r) => $r['type'] === 'uses_trait'));

        if (!empty($parent)) {
            $output .= "Extends:\n";
            foreach ($parent as $relation) {
                $output .= '    ' . ($relation['target_fqn_resolved'] ?? '') . "\n";
            }
        }

        if (!empty($interfaces)) {
            $output .= "Implements:\n";
            foreach ($interfaces as $relation) {
                $output .= '    ' . ($relation['target_fqn_resolved'] ?? '') . "\n";
            }
        }

        if (!empty($traits)) {
            $output .= "Uses traits:\n";
            foreach ($traits as $relation) {
                $output .= '    ' . ($relation['target_fqn_resolved'] ?? '') . "\n";
            }
        }

        return $output;
    }
}
