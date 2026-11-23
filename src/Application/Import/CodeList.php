<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** A RÚIAN code list whose codes the import checks against the published list. */
enum CodeList: string
{
    case LandType = 'land type';
    case LandUse = 'land use';
}
