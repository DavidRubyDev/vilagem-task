<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

/**
 * Text normalised for search: lower case, no diacritics, only a-z, 0-9, "/" and single spaces.
 * One normalisation for the index and the query, so they always agree.
 */
final readonly class SearchText
{
    /** There is no intl extension, so the letters of Czech and Slovak names are mapped explicitly. */
    private const array DIACRITICS = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r',
        'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
        'ľ' => 'l', 'ĺ' => 'l', 'ŕ' => 'r', 'ô' => 'o',
    ];

    private function __construct(public string $normalized) {}

    public static function of(string $text): self
    {
        $mapped = strtr(mb_strtolower($text), self::DIACRITICS);

        return new self(trim((string) preg_replace('~[^a-z0-9/]+~', ' ', $mapped)));
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->normalized === '' ? [] : explode(' ', $this->normalized);
    }

    /**
     * The tokens split at "/", without empty parts and without single letters (the "u" of "Popovice u Jičína").
     *
     * @return list<string>
     */
    public function terms(): array
    {
        $terms = [];
        foreach ($this->tokens() as $token) {
            foreach (explode('/', $token) as $part) {
                if ($part === '' || (strlen($part) === 1 && ctype_alpha($part))) {
                    continue;
                }
                $terms[] = $part;
            }
        }

        return $terms;
    }

    public function key(): string
    {
        return implode(' ', $this->terms());
    }

    public function isEmpty(): bool
    {
        return $this->normalized === '';
    }

    public function equals(self $other): bool
    {
        return $this->normalized === $other->normalized;
    }
}
