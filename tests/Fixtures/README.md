# Test fixtures

Small committed data for the tests. Nothing here is fetched at test time; tests never contact ČÚZK.

## `ruian/20261031_OB_572659_UKSH.xml.zip`

The real RÚIAN VFR export of the municipality Jičín (572659) dated 2026-10-31, downloaded from
`https://vdp.cuzk.gov.cz/vymenny_format/soucasna/20261031_OB_572659_UKSH.xml.zip` and trimmed by a
one-off script (not committed). Kept: the XML prolog, the root element with all namespace declarations,
the whole `vf:Hlavicka`, then `<vf:Data><vf:Parcely>` with only:

- the 22 parcels of cadastral area 659541 whose `DefinicniBod` lies within 75 m of S-JTSK
  (-671975.47, -1013096.26), the map centre: ids 1753292604, 1753293604, 1753294604, 1753295604,
  1753296604, 1753297604, 1753298604, 1753299604, 1753300604, 1753301604, 1753303604, 1753304604,
  1753305604, 1753306604, 1753309604, 1753310604, 1753691604, 1753692604, 1761441604, 2654592604,
  64343200010, 64343201010 (20 building plots, 2 land plots, land types 13 and 14, one with a hole);
- the first 3 parcels of cadastral area 740225 in file order: 95522753010, 99225194010, 100751793010.

Every other collection (Obce, KatastralniUzemi, Ulice, StavebniObjekty, ...) is dropped. The zip entry is
`20261031_OB_572659_UKSH.xml`. Data: ČÚZK, 2026, CC BY 4.0.

## `ruian/20261031_ST_UZSZ.xml.zip`

The real RÚIAN VFR state structure file dated 2026-10-31, downloaded from
`https://vdp.cuzk.gov.cz/vymenny_format/soucasna/20261031_ST_UZSZ.xml.zip` and trimmed by a one-off script
(not committed; text-level copy, so the namespaces stay). Kept: the XML prolog, the root element with all
namespace declarations, the whole `vf:Hlavicka`, then `<vf:Data>` with only:

- `<vf:Obce>`: 572659 Jičín, 572675 Běchary, 577413 Přepeře (district 3608) and 559351 Plasy (district 3407);
- `<vf:KatastralniUzemi>`: 659541, 740225, 725838, 740217, 771775, 601462, 734683, 642941, 721506, 721514,
  721522, 721531, 795682.

Inside every kept element the `Geometrie`, `VlajkaText`, `VlajkaObrazek`, `ZnakText` and `ZnakObrazek`
elements are removed; every other collection is dropped. The zip entry is `20261031_ST_UZSZ.xml`. Area 642941
Horní Hradiště (municipality Plasy) has no digital map; district 3605 has no municipality in it. Data: ČÚZK,
2026, CC BY 4.0.

## `coverage/fixture.csv`

Two areas of the fixture export: 659541 Jičín and 740225 Robousy (municipality 572659).

## `geojson/572659.geojsonl`

The output of `ParcelConverterUsingOgr2ogr` for municipality 572659 with the areas 659541 and 740225 on the
fixture zip (25 lines, WGS84, properties `id`, `landType` and `areaCode`), input for the tileset builder test.

## `data/tiles/parcels.mbtiles`

The fixture tileset (about 40 KB), used as the data dir of the test app (`PARCEL_MAP_DATA_DIR=tests/Fixtures/data`
in `compose.test.yaml`). Produced by `bin/console parcels:import --export-date=2026-10-31` with
`PARCEL_MAP_COVERAGE_FILE=tests/Fixtures/coverage/fixture.csv`, a temporary `PARCEL_MAP_DATA_DIR` whose
`cache/ruian/` holds a copy of the fixture zip, and `RUIAN_EXPORT_BASE_URL=http://127.0.0.1:9/`.
The tests use these facts as constants in `tests/Support/FixtureTileset.php`:

- version `20261120111929`, export date 2026-10-31, zooms 9 to 16;
- bounds `15.350516,50.432327,15.403251,50.437799`;
- tiles `12/2222/1381`, `13/4445/2762`, `14/8890/5525` exist; `14/8891/5525` is inside the bounds but empty.

## `tests/Integration/Support/ruian-router.php`

Not data: a router script for `php -S` that stands in for the ČÚZK download server and serves the
fixture zips, including the state structure file. Municipality 500000 answers 500, 500001 answers 200 with an
HTML body, anything else missing answers 404.
