<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

interface RequestHandler
{
    public function handle(Request $request): Response;
}
