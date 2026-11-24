<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

/** What a place is. The values are the ranking order of results: a municipality comes before a street. */
enum PlaceKind: int
{
    case Municipality = 1;
    case CadastralArea = 2;
    case MunicipalityPart = 3;
    case Street = 4;
    case Address = 5;
}
