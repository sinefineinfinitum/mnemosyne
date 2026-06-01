<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

use SineFine\Ponymator\Analyzer\BuiltinClassList;
use SineFine\Ponymator\Analyzer\Link\CrossReferenceContext;
use SineFine\Ponymator\Analyzer\VendorPackageResolver;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class VendorIndexGenerator
{
    public function __construct(
        private MarkdownBuilder $builder,
        private VendorPackageResolver $resolver,
    ) {
    }

    public function generate(CrossReferenceContext $context): string
    {
        $index = $context->getIndex();
        $externalFqns = $index->getExternalFqns();

        $byPackage = [];

        foreach ($externalFqns as $externalFqn) {
            if (BuiltinClassList::isBuiltin($externalFqn)) {
                continue;
            }
            $packageName = $this->resolver->resolve($externalFqn);
            if ($packageName === null) {
                continue;
            }

            $shortName = $this->resolver->getShortName($externalFqn);
            if (!isset($byPackage[$packageName])) {
                $info = $this->resolver->getPackageInfo($packageName);
                $byPackage[$packageName] = [
                    'package' => $packageName,
                    'version' => $info['version'],
                    'description' => $info['description'],
                    'classes' => [],
                ];
            }
            if (!in_array($shortName, $byPackage[$packageName]['classes'], true)) {
                $byPackage[$packageName]['classes'][] = $shortName;
            }
        }

        foreach ($byPackage as $name => $data) {
            sort($data['classes']);
            $byPackage[$name] = $data;
        }

        ksort($byPackage);

        return $this->builder->vendorIndex('Vendor Packages', array_values($byPackage));
    }
}
