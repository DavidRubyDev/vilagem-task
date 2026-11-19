<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

use Throwable;

final readonly class HttpKernel
{
    public function __construct(
        private Router $router,
        private ErrorReporter $errorReporter,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $error) {
            $this->errorReporter->report($error);

            return Response::internalError();
        }
    }
}
