<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Cadastre;

use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNotFound;
use ParcelMap\Domain\Cadastre\ParcelStoreNotPublished;
use ParcelMap\Domain\Cadastre\ParcelStoreUnreadable;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Infrastructure\Cadastre\ParcelRepositoryUsingSqlite;
use ParcelMap\Tests\Support\FixtureParcelStore;
use ParcelMap\Tests\Support\FixtureTileset;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelRepositoryUsingSqliteTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    #[Test]
    public function it_returns_the_whole_parcel_of_the_fixture_store(): void
    {
        $parcel = $this->fixtureRepository()->get(new TilesetVersion(FixtureTileset::VERSION), new ParcelId(FixtureParcelStore::BUILDING_ID));

        self::assertSame(FixtureParcelStore::BUILDING_ID, $parcel->id->value);
        self::assertSame('st. 72', $parcel->number->toString());
        self::assertSame(ParcelKind::Building, $parcel->number->kind);
        self::assertSame(659541, $parcel->cadastralArea->code->value);
        self::assertSame('Jičín', $parcel->cadastralArea->name);
        self::assertSame(572659, $parcel->cadastralArea->municipality->code->value);
        self::assertSame('Jičín', $parcel->cadastralArea->municipality->name);
        self::assertSame(13, $parcel->landType->code);
        self::assertNull($parcel->landUse);
        self::assertSame(455, $parcel->area->squareMetres);
        $rings = $parcel->boundary->rings();
        self::assertCount(1, $rings);
        self::assertCount(32, $rings[0]);
        self::assertSame(15.3509779, $rings[0][0]->longitude);
        self::assertSame(50.4377444, $rings[0][0]->latitude);
    }

    #[Test]
    public function it_returns_the_land_use_the_subdivision_and_the_holes(): void
    {
        $repository = $this->fixtureRepository();
        $version = new TilesetVersion(FixtureTileset::VERSION);

        $withHoles = $repository->get($version, new ParcelId(FixtureParcelStore::LAND_WITH_HOLES_ID));
        $subdivided = $repository->get($version, new ParcelId(FixtureParcelStore::ROBOUSY_ID));

        self::assertSame(17, $withHoles->landUse?->code);
        self::assertCount(3, $withHoles->boundary->rings());
        self::assertSame('867/121', $subdivided->number->toString());
        self::assertSame(740225, $subdivided->cadastralArea->code->value);
    }

    #[Test]
    public function it_rejects_an_unknown_parcel(): void
    {
        $this->expectException(ParcelNotFound::class);
        $this->expectExceptionMessage('999');

        $this->fixtureRepository()->get(new TilesetVersion(FixtureTileset::VERSION), new ParcelId(999));
    }

    #[Test]
    public function it_rejects_a_version_without_a_store(): void
    {
        $this->expectException(ParcelStoreNotPublished::class);

        $this->fixtureRepository()->get(new TilesetVersion('19990101000000'), new ParcelId(FixtureParcelStore::BUILDING_ID));
    }

    #[Test]
    public function it_reports_a_store_that_is_not_an_sqlite_file(): void
    {
        file_put_contents($this->directory->path . '/v1.sqlite', 'this is not sqlite, but long enough to be looked at as a database header');

        $this->expectException(ParcelStoreUnreadable::class);

        new ParcelRepositoryUsingSqlite($this->directory->path)->get(new TilesetVersion('v1'), new ParcelId(1));
    }

    #[Test]
    public function it_reports_a_boundary_that_is_not_zlib_data(): void
    {
        $this->copyFixtureStore('v1');
        $pdo = new PDO('sqlite:' . $this->directory->path . '/v1.sqlite');
        $pdo->exec("UPDATE parcels SET boundary = x'0102030405' WHERE id = " . FixtureParcelStore::BUILDING_ID);
        $pdo = null;

        $this->expectException(ParcelStoreUnreadable::class);

        new ParcelRepositoryUsingSqlite($this->directory->path)->get(new TilesetVersion('v1'), new ParcelId(FixtureParcelStore::BUILDING_ID));
    }

    #[Test]
    public function it_reports_a_boundary_that_is_not_a_valid_polygon(): void
    {
        $this->copyFixtureStore('v1');
        $pdo = new PDO('sqlite:' . $this->directory->path . '/v1.sqlite');
        $statement = $pdo->prepare('UPDATE parcels SET boundary = ? WHERE id = ?');
        $statement->execute([gzcompress('[[[15.0,50.0],[15.1,50.0]]]'), FixtureParcelStore::BUILDING_ID]);
        $statement = null;
        $pdo = null;

        $this->expectException(ParcelStoreUnreadable::class);

        new ParcelRepositoryUsingSqlite($this->directory->path)->get(new TilesetVersion('v1'), new ParcelId(FixtureParcelStore::BUILDING_ID));
    }

    #[Test]
    public function it_reports_a_store_without_the_expected_tables(): void
    {
        $pdo = new PDO('sqlite:' . $this->directory->path . '/v1.sqlite');
        $pdo->exec('CREATE TABLE other (x INTEGER)');
        $pdo = null;

        $this->expectException(ParcelStoreUnreadable::class);

        new ParcelRepositoryUsingSqlite($this->directory->path)->get(new TilesetVersion('v1'), new ParcelId(1));
    }

    private function fixtureRepository(): ParcelRepositoryUsingSqlite
    {
        return new ParcelRepositoryUsingSqlite(dirname(FixtureParcelStore::file()));
    }

    private function copyFixtureStore(string $version): void
    {
        copy(FixtureParcelStore::file(), $this->directory->path . '/' . $version . '.sqlite');
    }
}
