<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

/**
 * Within one municipality a municipality, a cadastral area and a part of a municipality with the same name are one
 * place to a user, so only the biggest kind is kept: "Jičín" finds the municipality, not also the cadastral area.
 * Streets and addresses are never dropped.
 */
final class DistinctPlaces
{
    private function __construct() {}

    /**
     * @param list<Place> $places
     * @return list<Place> in the input order
     */
    public static function keep(array $places): array
    {
        $biggest = [];
        foreach ($places as $place) {
            if (self::isArea($place)) {
                $id = self::nameId($place);
                $biggest[$id] = min($biggest[$id] ?? $place->kind->value, $place->kind->value);
            }
        }

        return array_values(array_filter(
            $places,
            static fn(Place $place): bool => !self::isArea($place) || $biggest[self::nameId($place)] === $place->kind->value,
        ));
    }

    private static function isArea(Place $place): bool
    {
        return $place->kind->value <= PlaceKind::MunicipalityPart->value;
    }

    private static function nameId(Place $place): string
    {
        return $place->municipality->value . ':' . $place->key();
    }
}
