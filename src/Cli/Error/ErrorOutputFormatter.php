<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Error;

use SineFine\Ponymator\Documentation\Processor\ErrorReport;

final class ErrorOutputFormatter
{
    public function format(ErrorReport $report): string
    {
        if ($report->isEmpty()) {
            return '';
        }

        $lines = [];

        $header = '--- Errors';
        if ($report->errorCount() > 0) {
            $header .= ' (' . $report->errorCount() . ' error' . ($report->errorCount() !== 1 ? 's' : '');
            if ($report->warningCount() > 0) {
                $header .= ', ' . $report->warningCount() . ' warning' . ($report->warningCount() !== 1 ? 's' : '');
            }
            $header .= ')';
        } elseif ($report->warningCount() > 0) {
            $header .= ' (' . $report->warningCount() . ' warning' . ($report->warningCount() !== 1 ? 's' : '');
            $header .= ')';
        }
        $header .= ' ---';
        $lines[] = $header;

        foreach ($report->getDiagnostics() as $diagnostic) {
            $line = $diagnostic->severity . ': ';
            if ($diagnostic->filePath !== null) {
                $line .= '[' . $diagnostic->filePath;
                if ($diagnostic->lineNumber !== null) {
                    $line .= ':' . $diagnostic->lineNumber;
                }
                $line .= '] ';
            }
            $line .= $diagnostic->message;
            if ($diagnostic->suggestion !== null) {
                $line .= ' — ' . $diagnostic->suggestion;
            }
            $lines[] = $line;
        }

        $lines[] = '---';

        return implode("\n", $lines) . "\n";
    }
}
