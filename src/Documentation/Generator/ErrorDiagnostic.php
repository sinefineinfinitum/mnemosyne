<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Generator;

use InvalidArgumentException;
use Throwable;

final class ErrorDiagnostic
{
    public const ERROR = 'Error';
    public const WARNING = 'Warning';

    public function __construct(
        public string     $severity,
        public string     $message,
        public ?string    $filePath = null,
        public ?int       $lineNumber = null,
        public ?string    $suggestion = null,
        public ?Throwable $exception = null
    ) {
        if ($this->message === '') {
            throw new InvalidArgumentException('Message must be non-empty');
        }
        if ($this->severity !== self::ERROR && $this->severity !== self::WARNING) {
            throw new InvalidArgumentException('Severity must be Error or Warning');
        }
        if ($this->filePath === '') {
            throw new InvalidArgumentException('FilePath must be non-empty if provided');
        }
        if ($this->lineNumber !== null && $this->lineNumber < 1) {
            throw new InvalidArgumentException('LineNumber must be >= 1 if provided');
        }
    }
}
