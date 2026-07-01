<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli;

final class ExecutionTimer
{
    private int $start;

    public function __construct()
    {
        $this->start = hrtime(true);
    }

    public function elapsed(): float
    {
        return (hrtime(true) - $this->start) / 1e9;
    }

    public function finish(): void
    {
        $elapsed = $this->elapsed();
        if ($elapsed >= 60) {
            $minutes = (int) ($elapsed / 60);
            $seconds = $elapsed - $minutes * 60;
            printf("Execution time: %dm %02.0fs\n", $minutes, $seconds);
        } else {
            printf("Execution time: %.2fs\n", $elapsed);
        }
    }
}
