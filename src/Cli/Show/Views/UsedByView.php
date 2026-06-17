<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show\Views;

use SineFine\Ponymator\Cli\Show\EntityView;

final class UsedByView implements ViewObject
{
    public function __construct(
        private EntityView $view,
    ) {
    }

    public function render(): string
    {
        $callIncoming = $this->view->callIncoming;

        if (empty($callIncoming)) {
            return '';
        }

        $memberIds = [];
        foreach ($callIncoming as $relation) {
            if ($relation['source_member_id'] !== null) {
                $memberIds[] = (int) $relation['source_member_id'];
            }
        }
        $allParams = $this->view->query->findParametersByMembers($memberIds);
        $paramsByMember = [];
        foreach ($allParams as $param) {
            $paramsByMember[(int) $param['member_id']][] = $param;
        }

        $output = "\n  Used by (" . count($callIncoming) . "):\n";
        foreach ($callIncoming as $relation) {
            $relation['_params'] = $paramsByMember[(int) $relation['source_member_id']] ?? [];
            $output .= $this->renderLine($relation);
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $relation
     */
    private function renderLine(array $relation): string
    {
        $type = $relation['type'];
        $sourceFqn = $relation['source_fqn'] ?? '';
        $memberId = $relation['source_member_id'];
        $memberName = $relation['source_member_name'];

        if (!str_starts_with($type, 'call_') || $memberId === null) {
            $line = "    [$type] $sourceFqn";
            if ($memberName !== null) {
                $line .= ' +' . $memberName;
            }
            return $line . "\n";
        }

        $sig = self::buildCallSignature($memberId, $relation, $sourceFqn, $type, $memberName);
        return "    $sig\n";
    }

    /**
     * @param  int                  $memberId
     * @param  array<string, mixed> $relation
     * @param  string               $sourceFqn
     * @param  string               $type
     * @param  string               $memberName
     * @return string
     */
    private static function buildCallSignature(
        int        $memberId,
        array      $relation,
        string     $sourceFqn,
        string     $type,
        string     $memberName,
    ): string {
        $returnType = $relation['source_member_return_type'];
        $declaredType = $relation['source_member_declared_type'];
        $memberType = $relation['source_member_type'] ?? 'method';

        if ($memberType === 'property') {
            $prefix = $declaredType !== null ? $declaredType . ' ' : '';
            return "$sourceFqn->\$$memberName ($prefix)";
        }

        $params = $relation['_params'] ?? [];
        $paramStrings = [];
        foreach ($params as $p) {
            $paramWithTypeAndDefaultValue = '';
            if ($p['declared_type'] !== null) {
                $paramWithTypeAndDefaultValue .= $p['declared_type'] . ' ';
            }
            $paramWithTypeAndDefaultValue .= '$' . $p['name'];
            if ($p['default_value'] !== null) {
                $paramWithTypeAndDefaultValue .= ' = ' . $p['default_value'];
            }
            $paramStrings[] = $paramWithTypeAndDefaultValue;
        }

        $paramsWithReturn = '(' . implode(', ', $paramStrings) . ')';
        if ($returnType !== null) {
            $paramsWithReturn .= ': ' . $returnType;
        }
        $strength = str_ends_with($type, '_strong') ? '' : 'maybe ';

        if (str_starts_with($type, 'call_dynamic_')) {
            return $strength . "called by $sourceFqn->$memberName" . $paramsWithReturn;
        }

        if (str_starts_with($type, 'call_static_')) {
            return $strength . "called by $sourceFqn::$memberName" . $paramsWithReturn;
        }

        return "$sourceFqn::$memberName$paramsWithReturn";
    }
}
