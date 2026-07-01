<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer;

final class CallAnalysisResult
{
    /**
     * @param array<string, array<string, list<CallInfo>>> $calls
     * @param array<string, list<CallInfo>>                $fileCalls
     */
    public function __construct(
        private array $calls,
        private array $fileCalls = [],
    ) {
    }

    /**
     * @return array<string, array<string, list<CallInfo>>>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return array<string, list<CallInfo>>
     */
    public function getFileCalls(): array
    {
        return $this->fileCalls;
    }
}
