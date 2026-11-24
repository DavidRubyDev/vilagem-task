<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\InvalidCadastralAreaCode;
use ParcelMap\Domain\Cadastre\ParcelKind;

/**
 * A parsed search text: what people type is "st. 72", "80/2" or "867/121 Robousy", so the parcel number, a
 * cadastral area code and the remaining words are found among the tokens of the normalised text, in any order.
 *
 * Every term consists of [a-z0-9] only (SearchText guarantees it); the repositories rely on it to put terms into
 * LIKE patterns without escaping.
 */
final readonly class SearchQuery
{
    private const int MAX_LENGTH = 100;
    private const string PARCEL_NUMBER_PATTERN = '~^([0-9]{1,5})(?:/([0-9]{1,4}))?$~';
    /** Words that only introduce a parcel number ("p. č. 80/2", "parc. č. 80/2"); "p" and "c" are single letters. */
    private const array IGNORED_WORDS = ['pc', 'parc'];
    private const string BUILDING_MARKER = 'st';

    private const int MAX_PLACE_TERMS = 8;

    /**
     * @param list<string> $nameTerms
     * @param list<string> $placeTerms
     */
    private function __construct(
        private ?ParcelNumberQuery $parcelNumber,
        private ?CadastralAreaCode $cadastralAreaCode,
        private array $nameTerms,
        private array $placeTerms,
    ) {}

    /**
     * @throws InvalidSearchQuery
     */
    public static function parse(string $text): self
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw InvalidSearchQuery::empty();
        }
        $length = mb_strlen($trimmed);
        if ($length > self::MAX_LENGTH) {
            throw InvalidSearchQuery::tooLong($length);
        }

        $tokens = SearchText::of($trimmed)->tokens();
        $numberIndex = self::parcelNumberIndex($tokens);
        $markerIndex = $numberIndex !== null && $numberIndex > 0 && $tokens[$numberIndex - 1] === self::BUILDING_MARKER
            ? $numberIndex - 1
            : null;
        $parcelNumber = $numberIndex === null
            ? null
            : self::asParcelNumber($tokens[$numberIndex], $markerIndex === null ? null : ParcelKind::Building);
        [$areaIndex, $areaCode] = self::areaCode($tokens);

        $nameTerms = [];
        $placeTerms = [];
        foreach ($tokens as $index => $token) {
            if ($index === $markerIndex || in_array($token, self::IGNORED_WORDS, true)) {
                continue;
            }
            $terms = SearchText::of($token)->terms();
            array_push($placeTerms, ...$terms);
            if ($index !== $numberIndex && $index !== $areaIndex) {
                array_push($nameTerms, ...$terms);
            }
        }

        return new self($parcelNumber, $areaCode, $nameTerms, array_slice($placeTerms, 0, self::MAX_PLACE_TERMS));
    }

    public function parcelNumber(): ?ParcelNumberQuery
    {
        return $this->parcelNumber;
    }

    public function cadastralAreaCode(): ?CadastralAreaCode
    {
        return $this->cadastralAreaCode;
    }

    /**
     * @return list<string> the words that must start a word of the cadastral area's or its municipality's name
     */
    public function nameTerms(): array
    {
        return $this->nameTerms;
    }

    public function searchesParcels(): bool
    {
        return $this->parcelNumber !== null;
    }

    /**
     * @return list<string> every word and number of the text: each must start a word of the name or the context of a place
     */
    public function placeTerms(): array
    {
        return $this->placeTerms;
    }

    public function placeKey(): string
    {
        return implode(' ', $this->placeTerms);
    }

    /** Places need a word: a bare number is a parcel number, not a place. */
    public function searchesPlaces(): bool
    {
        foreach ($this->placeTerms as $term) {
            if (strlen($term) >= 2 && preg_match('~[a-z]~', $term) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private static function parcelNumberIndex(array $tokens): ?int
    {
        foreach ($tokens as $index => $token) {
            if (self::asParcelNumber($token, null) !== null) {
                return $index;
            }
        }

        return null;
    }

    private static function asParcelNumber(string $token, ?ParcelKind $kind): ?ParcelNumberQuery
    {
        if (preg_match(self::PARCEL_NUMBER_PATTERN, $token, $parts) !== 1) {
            return null;
        }
        $base = (int) $parts[1];
        $subdivision = isset($parts[2]) ? (int) $parts[2] : null;
        if ($base < 1 || $subdivision === 0) {
            return null;
        }

        return new ParcelNumberQuery($kind, $base, $subdivision);
    }

    /**
     * @param list<string> $tokens
     * @return array{?int, ?CadastralAreaCode}
     */
    private static function areaCode(array $tokens): array
    {
        foreach ($tokens as $index => $token) {
            if (preg_match('~^[0-9]{6}$~', $token) !== 1) {
                continue;
            }
            try {
                return [$index, new CadastralAreaCode((int) $token)];
            } catch (InvalidCadastralAreaCode) {
                continue;
            }
        }

        return [null, null];
    }
}
