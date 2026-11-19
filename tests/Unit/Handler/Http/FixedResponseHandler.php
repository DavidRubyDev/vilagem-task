<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

final readonly class FixedResponseHandler implements RequestHandler
{
    public function __construct(private Response $response) {}

    public function handle(Request $request): Response
    {
        return $this->response;
    }
}
