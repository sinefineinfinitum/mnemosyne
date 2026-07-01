<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show\Views;

use SineFine\Mnemosyne\Cli\Show\EntityView;

final class ExternalView implements ViewObject
{
    public function __construct(
        private EntityView $view,
    ) {
    }

    public function render(): string
    {
        $external = $this->view->external;

        if (empty($external)) {
            return '';
        }

        $output = "\n  External (" . count($external) . "):\n";
        foreach ($external as $fqn) {
            $output .= "    $fqn\n";
        }

        return $output;
    }
}
