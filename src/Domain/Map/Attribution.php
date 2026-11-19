<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** The data credit that must stay visible on the map: text plus a link. */
final readonly class Attribution
{
    public function __construct(
        public string $text,
        public string $url,
    ) {
        if (trim($text) === '') {
            throw InvalidAttribution::emptyText();
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw InvalidAttribution::invalidUrl($url);
        }
    }

    public function equals(self $other): bool
    {
        return $this->text === $other->text && $this->url === $other->url;
    }
}
