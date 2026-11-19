<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

use Throwable;

final class StderrErrorReporter implements ErrorReporter
{
    public function report(Throwable $error): void
    {
        error_log((string) $error);
    }
}
