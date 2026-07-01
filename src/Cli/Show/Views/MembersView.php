<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show\Views;

use SineFine\Mnemosyne\Cli\Show\EntityView;

final class MembersView implements ViewObject
{
    private const SECTION_ORDER = ['property', 'case', 'constant', 'method'];

    public function __construct(
        private EntityView $view,
    ) {
    }

    public function render(): string
    {
        $output = '';
        $query = $this->view->query;
        $members = $this->view->members;
        $outgoingCalls = $this->view->outgoingCalls;

        $callsByMember = [];
        foreach ($outgoingCalls as $rel) {
            $memberId = $rel['source_member_id'];
            if ($memberId === null) {
                continue;
            }
            $callsByMember[$memberId][] = $rel;
        }

        $methodIds = [];
        foreach ($members as $member) {
            if ($member['member_type'] === 'method') {
                $methodIds[] = (int) $member['id'];
            }
        }
        $allParams = $query->findParametersByMembers($methodIds);
        $paramsByMember = [];
        foreach ($allParams as $param) {
            $paramsByMember[(int) $param['member_id']][] = $param;
        }

        $sections = [];
        foreach ($members as $member) {
            $type = $member['member_type'];
            $member['relationships'] = $callsByMember[$member['id']] ?? [];
            $member['_params'] = $paramsByMember[(int) $member['id']] ?? [];
            $sections[$type][] = $member;
        }

        $labels = [
            'property' => 'Properties',
            'case' => 'Cases',
            'constant' => 'Constants',
            'method' => 'Methods',
        ];

        foreach (self::SECTION_ORDER as $sectionType) {
            if (empty($sections[$sectionType])) {
                continue;
            }

            $count = count($sections[$sectionType]);
            $output .= "\n" . $labels[$sectionType] . ' (' . $count . "):\n";

            foreach ($sections[$sectionType] as $member) {
                $output .= $this->renderMember($member, $sectionType);
            }
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $member
     */
    private function renderMember(array $member, string $sectionType): string
    {
        $output = '';
        $name = $member['name'];
        $visibility = $member['visibility'];
        $isStatic = (int) $member['is_static'] === 1;
        $isAbstract = (int) $member['is_abstract'] === 1;
        $isFinal = (int) $member['is_final'] === 1;

        $mods = [];
        if ($isAbstract) {
            $mods[] = 'abstract';
        }
        if ($isFinal) {
            $mods[] = 'final';
        }
        if ($visibility !== null) {
            $mods[] = $visibility;
        }
        if ($isStatic) {
            $mods[] = 'static';
        }

        $prefix = !empty($mods) ? implode(' ', $mods) . ' ' : '';

        if ($sectionType === 'method') {
            $params = $member['_params'] ?? [];
            $paramStrings = [];
            foreach ($params as $param) {
                $pStr = '';
                if ($param['declared_type'] !== null) {
                    $pStr .= $param['declared_type'] . ' ';
                }
                if ((int) $param['is_passed_by_reference'] === 1) {
                    $pStr .= '&';
                }
                if ((int) $param['is_variadic'] === 1) {
                    $pStr .= '...';
                }
                $pStr .= '$' . $param['name'];
                if ($param['default_value'] !== null) {
                    $pStr .= ' = ' . $param['default_value'];
                }
                $paramStrings[] = $pStr;
            }
            $signature = $prefix . 'function ' . $name . '(' . implode(', ', $paramStrings) . ')';
            if ($member['return_type'] !== null) {
                $signature .= ': ' . $member['return_type'];
            }
            $output .= '    ' . $signature . "\n";
        } elseif ($sectionType === 'property') {
            $typeStr = '';
            if ($member['declared_type'] !== null) {
                $typeStr = $member['declared_type'] . ' ';
            }
            $output .= "    $prefix$typeStr\$$name\n";
        } elseif ($sectionType === 'constant') {
            $output .= '    ' . $prefix . 'const ' . "$name\n";
        } elseif ($sectionType === 'case') {
            $output .= "    case $name\n";
        }

        foreach ($member['relationships'] as $rel) {
            $type = $rel['type'];
            $target = $rel['target_fqn_resolved'] ?? '';

            if (str_starts_with($type, 'call_dynamic_')) {
                $strength = str_ends_with($type, '_strong') ? 'strong' : 'weak';
                $output .= "      $strength $target\n";
            } elseif (str_starts_with($type, 'call_static_')) {
                $strength = str_ends_with($type, '_strong') ? 'strong' : 'weak';
                $output .= "      $strength $target\n";
            } elseif (str_starts_with($type, 'call_global_')) {
                $strength = str_ends_with($type, '_strong') ? 'strong' : 'weak';
                $output .= "      $strength $target\n";
            } elseif ($type === 'creates') {
                $output .= "      create $target\n";
            } else {
                $output .= "      [$type] $target\n";
            }
        }

        return $output;
    }
}
