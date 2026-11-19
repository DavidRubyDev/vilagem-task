<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\ErrorReporter;
use Throwable;

final class RecordingErrorReporter implements ErrorReporter
{
    /** @var list<Throwable> */
    public array $reported = [];

    public function report(Throwable $error): void
    {
        $this->reported[] = $error;
    }
}
