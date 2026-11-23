<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Import;

use ParcelMap\Application\Import\CodeList;
use ParcelMap\Application\Import\InvalidUnknownCode;
use ParcelMap\Application\Import\UnknownCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnknownCodeTest extends TestCase
{
    #[Test]
    public function it_rejects_a_code_used_by_no_parcel(): void
    {
        $this->expectException(InvalidUnknownCode::class);

        new UnknownCode(CodeList::LandUse, 31, 0);
    }

    #[Test]
    public function it_keeps_the_list_the_code_and_the_parcel_count(): void
    {
        $code = new UnknownCode(CodeList::LandType, 99, 2);

        self::assertSame(CodeList::LandType, $code->list);
        self::assertSame(99, $code->code);
        self::assertSame(2, $code->parcelCount);
    }
}
