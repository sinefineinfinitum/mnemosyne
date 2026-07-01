<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show\Views;

use SineFine\Mnemosyne\Cli\Show\EntityView;

final class InheritorsView implements ViewObject
{
    public function __construct(
        private EntityView $view,
    ) {
    }

    public function render(): string
    {
        $output = '';
        $relations = $this->view->structuralIncoming;

        $inheritors = array_values(array_filter($relations, fn($r) => $r['type'] === 'extends'));
        $implementers = array_values(array_filter($relations, fn($r) => $r['type'] === 'implements'));
        $traitUsers = array_values(array_filter($relations, fn($r) => $r['type'] === 'uses_trait'));

        if (!empty($inheritors)) {
            $output .= 'Inheritors (' . count($inheritors) . "):\n";
            foreach ($inheritors as $relation) {
                $output .= '    ' . $relation['source_fqn'] . "\n";
            }
        }

        if (!empty($implementers)) {
            $output .= 'Implementers (' . count($implementers) . "):\n";
            foreach ($implementers as $relation) {
                $output .= '    ' . $relation['source_fqn'] . "\n";
            }
        }

        if (!empty($traitUsers)) {
            $output .= 'Used by traits (' . count($traitUsers) . "):\n";
            foreach ($traitUsers as $relation) {
                $output .= '    ' . $relation['source_fqn'] . "\n";
            }
        }

        return $output;
    }
}
