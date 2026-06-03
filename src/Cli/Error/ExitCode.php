<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Error;

final class ExitCode
{
    public const SUCCESS = 0;
    public const GENERIC_ERROR = 1;
    public const COMMAND_LINE_MISTAKE = 2;
    public const WRONG_USAGE = 64;
    public const SOURCE_NOT_FOUND = 66;
    public const OUTPUT_ERROR = 73;
    public const CONFIG_ERROR = 78;
}
