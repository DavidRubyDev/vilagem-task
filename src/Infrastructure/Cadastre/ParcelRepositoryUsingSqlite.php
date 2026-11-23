<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Cadastre;

use InvalidArgumentException;
use JsonException;
use ParcelMap\Domain\Cadastre\Area;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\LandType;
use ParcelMap\Domain\Cadastre\LandUse;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNotFound;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Cadastre\ParcelRepository;
use ParcelMap\Domain\Cadastre\ParcelStoreNotPublished;
use ParcelMap\Domain\Cadastre\ParcelStoreUnreadable;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\TilesetVersion;
use PDO;
use PDOException;
use Pdo\Sqlite;

/**
 * Reads one parcel from the parcel store of a tileset version by primary key. The file is opened read-only per call
 * (as the MBTiles reader does), because a re-import replaces files and a long-lived connection would keep serving
 * the old one.
 */
final readonly class ParcelRepositoryUsingSqlite implements ParcelRepository
{
    public function __construct(private string $directory) {}

    public function isPublished(TilesetVersion $version): bool
    {
        return is_file($this->file($version));
    }

    public function get(TilesetVersion $version, ParcelId $id): Parcel
    {
        $file = $this->file($version);
        if (!is_file($file)) {
            throw ParcelStoreNotPublished::forVersion($version->value);
        }

        try {
            $row = $this->row($file, $id);
        } catch (PDOException $exception) {
            throw ParcelStoreUnreadable::because($exception->getMessage());
        }
        if ($row === null) {
            throw ParcelNotFound::withId($id->value);
        }

        try {
            return $this->parcel($id, $row);
        } catch (InvalidArgumentException|JsonException $exception) {
            throw ParcelStoreUnreadable::because(sprintf('parcel %d is invalid: %s', $id->value, $exception->getMessage()));
        }
    }

    private function file(TilesetVersion $version): string
    {
        return sprintf('%s/%s.sqlite', $this->directory, $version->value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $file, ParcelId $id): ?array
    {
        $connection = new Sqlite(
            'sqlite:' . $file,
            options: [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $statement = $connection->prepare(
            'SELECT p.kind, p.base_number, p.subdivision, p.land_type, p.land_use, p.area, p.boundary,
                    a.code AS area_code, a.name AS area_name, a.municipality_code, a.municipality_name
             FROM parcels p JOIN cadastral_areas a ON a.code = p.cadastral_area_code
             WHERE p.id = ?',
        );
        $statement->execute([$id->value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        /** @var array<string, mixed>|false $row */
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function parcel(ParcelId $id, array $row): Parcel
    {
        $landUse = self::optionalInteger($row, 'land_use');

        return new Parcel(
            $id,
            new CadastralArea(
                new CadastralAreaCode(self::integer($row, 'area_code')),
                self::string($row, 'area_name'),
                new Municipality(new MunicipalityCode(self::integer($row, 'municipality_code')), self::string($row, 'municipality_name')),
            ),
            new ParcelNumber(
                ParcelKind::fromCode(self::integer($row, 'kind')),
                self::integer($row, 'base_number'),
                self::optionalInteger($row, 'subdivision'),
            ),
            new LandType(self::integer($row, 'land_type')),
            $landUse === null ? null : new LandUse($landUse),
            new Area(self::integer($row, 'area')),
            $this->boundary(self::string($row, 'boundary')),
        );
    }

    private function boundary(string $compressed): ParcelBoundary
    {
        // PHP raises a warning before returning false; the failure is reported as an exception instead.
        $json = @gzuncompress($compressed);
        if ($json === false) {
            throw new InvalidArgumentException('the boundary is not zlib data.');
        }

        $rings = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($rings)) {
            throw new InvalidArgumentException('the boundary is not a list of rings.');
        }

        $coordinates = [];
        foreach ($rings as $ring) {
            if (!is_array($ring)) {
                throw new InvalidArgumentException('a boundary ring is not a list of positions.');
            }
            $positions = [];
            foreach ($ring as $position) {
                if (!is_array($position) || !is_numeric($position[0] ?? null) || !is_numeric($position[1] ?? null)) {
                    throw new InvalidArgumentException('a boundary position is not [longitude, latitude].');
                }
                $positions[] = new Coordinates((float) $position[0], (float) $position[1]);
            }
            $coordinates[] = $positions;
        }

        return ParcelBoundary::fromRings($coordinates);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        return is_int($value) ? $value : throw new InvalidArgumentException(sprintf('the column "%s" is not an integer.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function optionalInteger(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::integer($row, $column);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return is_string($value) ? $value : throw new InvalidArgumentException(sprintf('the column "%s" is not text.', $column));
    }
}
