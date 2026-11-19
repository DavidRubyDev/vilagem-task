<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

/** Serves a static page, so it has no use case behind it. */
final readonly class ShowMapPageHandler implements RequestHandler
{
    public function __construct(private string $pageFile) {}

    public function handle(Request $request): Response
    {
        $html = is_readable($this->pageFile) ? file_get_contents($this->pageFile) : false;
        if ($html === false) {
            throw MapPageUnavailable::cannotRead($this->pageFile);
        }

        return Response::html($html);
    }
}
