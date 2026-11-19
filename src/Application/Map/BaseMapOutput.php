<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

final readonly class BaseMapOutput
{
    public function __construct(
        public string $tileUrlTemplate,
        public int $tileSize,
        public int $maxZoom,
        public string $attributionText,
        public string $attributionUrl,
    ) {}
}
