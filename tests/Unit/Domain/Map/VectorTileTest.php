<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidVectorTile;
use ParcelMap\Domain\Map\VectorTile;
use ParcelMap\Tests\Support\Gzip;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VectorTileTest extends TestCase
{
    #[Test]
    public function it_keeps_gzipped_data(): void
    {
        $data = Gzip::encode('tile');

        $tile = VectorTile::fromGzippedData($data);

        self::assertFalse($tile->isEmpty());
        self::assertSame($data, $tile->gzippedData());
    }

    #[Test]
    public function it_rejects_data_that_is_not_gzipped(): void
    {
        $this->expectException(InvalidVectorTile::class);

        VectorTile::fromGzippedData('plain bytes');
    }

    #[Test]
    public function it_can_be_empty(): void
    {
        $tile = VectorTile::empty();

        self::assertTrue($tile->isEmpty());
        self::assertSame('', $tile->gzippedData());
    }
}
