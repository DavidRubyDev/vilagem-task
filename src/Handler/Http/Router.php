<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

final readonly class Router
{
    /** @var list<Route> */
    private array $routes;

    public function __construct(Route ...$routes)
    {
        $this->routes = array_values($routes);
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if ($route->path !== $request->path) {
                continue;
            }
            if ($route->method === $request->method) {
                return $route->handler->handle($request);
            }
            $allowedMethods[] = $route->method;
        }

        if ($allowedMethods !== []) {
            return Response::methodNotAllowed($allowedMethods);
        }

        return Response::notFound();
    }
}
