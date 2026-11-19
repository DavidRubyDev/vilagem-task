<?php

declare(strict_types=1);

namespace ParcelMap\Bootstrap;

use ParcelMap\Handler\Http\HttpKernel;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\Http\StderrErrorReporter;

final readonly class Container
{
    public function httpKernel(): HttpKernel
    {
        return new HttpKernel(new Router(), new StderrErrorReporter());
    }
}
