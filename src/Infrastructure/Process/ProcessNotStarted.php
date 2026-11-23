<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Process;

use RuntimeException;

final class ProcessNotStarted extends RuntimeException
{
    public static function program(string $program, string $reason): self
    {
        return new self(sprintf('Cannot start "%s": %s', $program, $reason));
    }
}
