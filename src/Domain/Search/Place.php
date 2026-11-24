<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;

/** A search result that is a location; identity is its kind and RÚIAN code. */
final readonly class Place
{
    /**
     * @param ?Bounds $bounds null for a place that is a point
     * @param ?ParcelId $parcel the parcel an address's building stands on
     */
    public function __construct(
        public PlaceKind $kind,
        public int $code,
        public MunicipalityCode $municipality,
        public string $name,
        public string $context,
        public Coordinates $center,
        public ?Bounds $bounds,
        public ?ParcelId $parcel,
    ) {
        if ($code < 1) {
            throw InvalidPlace::codeNotPositive($code);
        }
        if (trim($name) === '') {
            throw InvalidPlace::blankName($code);
        }
        if ($parcel !== null && $kind !== PlaceKind::Address) {
            throw InvalidPlace::parcelOnlyForAddresses($code);
        }
        if ($bounds !== null && !self::contains($bounds, $center)) {
            throw InvalidPlace::centerOutsideBounds($code);
        }
    }

    public static function fromAddress(int $code, MunicipalityCode $municipality, Address $address, ParcelId $parcel): self
    {
        return new self(PlaceKind::Address, $code, $municipality, $address->label(), $address->context(), $address->point, null, $parcel);
    }

    public function key(): string
    {
        return SearchText::of($this->name)->key();
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->code === $other->code;
    }

    private static function contains(Bounds $bounds, Coordinates $point): bool
    {
        return $point->longitude >= $bounds->southWest->longitude
            && $point->longitude <= $bounds->northEast->longitude
            && $point->latitude >= $bounds->southWest->latitude
            && $point->latitude <= $bounds->northEast->latitude;
    }
}
