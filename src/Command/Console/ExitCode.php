<?php

declare(strict_types=1);

namespace ParcelMap\Command\Console;

final class ExitCode
{
    public const int SUCCESS = 0;
    public const int FAILURE = 1;
    public const int USAGE = 2;

    private function __construct() {}
}
