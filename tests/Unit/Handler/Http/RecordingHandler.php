<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

final class RecordingHandler implements RequestHandler
{
    public ?Request $received = null;

    public function __construct(private readonly Response $response) {}

    public function handle(Request $request): Response
    {
        $this->received = $request;

        return $this->response;
    }
}
