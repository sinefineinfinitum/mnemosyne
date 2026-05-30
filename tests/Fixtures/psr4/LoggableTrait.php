<?php declare(strict_types=1);

namespace App\Traits;

trait LoggableTrait
{
    public function log(string $message): void
    {
        echo $message;
    }
}
