<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;
use Throwable;

final readonly class ThrowingHandler implements RequestHandler
{
    public function __construct(private Throwable $error) {}

    public function handle(Request $request): Response
    {
        throw $this->error;
    }
}
