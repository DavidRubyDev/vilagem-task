# 002: Jičín parcels from ČÚZK

- **Status:** done
- **Created:** 2026-11-20
- **Request:** "Show the real land parcels of the cadastral area Jičín … on the existing map. The data
  comes from ČÚZK, downloaded ahead of time and served by our backend as vector tiles. A data
  research spike comes first." Direction given: one operator command (`bin/console`, wrapped by
  `make data`) that downloads and builds everything, cached downloads, ports for downloader /
  converter / tile builder, task 003 (whole district) must only add the list of areas;
  `GET /tiles/{z}/{x}/{y}.pbf` or similar served by PHP with correct headers and caching; parcels
  over OSM with a semi-transparent fill coloured by land type and outlines from about zoom 13–14;
  click/detail is task 004; tests at every level with small committed fixtures, never contacting
  ČÚZK or OSM; CI keeps working; a clear behaviour when no data is imported.
  Answers during planning: "paralel containers would be prefered. But in this case, one container
  is sufficient. But make note of this in the Readme"; separate test compose project: yes;
  attribute store and legend deferred to 004: yes; no automatic import: yes; "all 5 areas" of the
  municipality Jičín. Added: "The first commit will still be the task document. Second commit will
  be a little refactor: … add more php rules to CLAUDE.md. Every usecase class' name should end
  with 'UseCase' suffix. Other application classes (that are not usecases) should describe the
  role (for example Converter, Builder, Publisher, …). Every infrastructure class that manipulates
  data (loading, saving, updating, …) should be named with '*RepositoryUsing<service>' suffix. In
  the same commit, All previous work should be refactored using those new rules". Naming details
  confirmed: option A (below), service = technology, fakes use `Memory`, map settings are a
  repository.

## Goal

An operator runs `make data`: the app downloads the ČÚZK RÚIAN export of the municipality Jičín
(cached), converts its 5 cadastral areas (16,972 parcels) and builds a vector tileset (MBTiles).
The map then shows the parcels over OSM: a semi-transparent fill coloured by land type at zoom
9–16+, parcel outlines from zoom 14, and the credit "ČÚZK, 2026". PHP serves the tiles from the
MBTiles file under versioned, immutably cached URLs. The import is built around a coverage list
(`config/coverage.csv`), so task 003 only adds the district's areas to that list. Before that, a
refactor introduces naming rules for use cases and adapters and applies them to task 001's code.

## Scope

**In scope:**

- Step 1: naming rules in CLAUDE.md and the rename of all task 001 classes.
- `bin/console` with `Command/Console/` plumbing.
- Import command `parcels:import` (`make data`): RÚIAN download with cache, GDAL conversion,
  tippecanoe build, atomic publish. GDAL and tippecanoe in the app image.
- Tile endpoint `GET /tiles/parcels/{version}/{z}/{x}/{y}.pbf` with path parameters in the router,
  gzip negotiation, 204 for empty tiles, immutable caching; `PHP_CLI_SERVER_WORKERS`.
- `GET /api/map-config` gains `parcels` (or `null`); frontend vector source, fill and outline
  layers, ČÚZK attribution; notice when no data is imported.
- Separate compose project for API/E2E tests with fixture data.
- Fixtures, unit / integration / API / E2E / Vitest tests, phpunit `integration` suite.
- README and CLAUDE.md (rules, Commands, Glossary, `data/` line) updates.

**Out of scope:**

- Click on a parcel, detail panel, parcel attribute store, `GET /api/parcels/{id}` (task 004).
- Legend and `LandType` / `LandUse` domain types and names (task 004).
- The district's 240 cadastral areas (task 003 adds them to `config/coverage.csv`).
- Import progress output, daily change files, parcel number labels, simplified-register (ZE)
  parcels, separate tool containers, production web server.
- Editing the `done` task file 001.

## Decisions

Decisions made with the developer during planning, each with a short reason.

1. **Source: RÚIAN VFR monthly municipality export, complete data set with original boundaries
   (`OB_UKSH`).** One feature carries all parcel attributes and the polygon; GDAL reads it
   directly from the zip. The per-area SHP splits attributes and polygons into two layers, INSPIRE
   lacks the land type, and the open VFK has no parcel rows (see Verified facts).
2. **Coverage = all 5 cadastral areas of the municipality Jičín** (659541 Jičín, 740225 Robousy,
   725838 Popovice u Jičína, 740217 Moravčice, 771775 Hubálov), listed in the committed
   `config/coverage.csv` (`code,name,municipality_code`). The source is per municipality anyway.
   Task 003 replaces the list with the district's 240 areas; no code change is needed.
3. **GDAL and tippecanoe are installed in the app image** (Debian trixie packages `gdal-bin`
   3.10.3, `tippecanoe` 2.53.0, +287 MB). The import use case drives them through process
   adapters, which needs them in the same container. Separate tool containers would be the
   preferred design (lean runtime image, independent tool upgrades, isolation); one image is
   enough for now because the import is the only consumer. The README says so (developer's
   request).
4. **Naming rules (step 1)**, option A:
   - Use cases: `<Verb><Subject>UseCase`.
   - Other Application classes are named by role: ports (`ParcelConverter`, `TilesetBuilder`,
     `TilesetPublisher`, `RuianExportDownloader`, `Clock`), DTOs (`…Input` / `…Output`),
     exceptions (what went wrong).
   - Infrastructure classes that load, save or update domain data: `<Subject>RepositoryUsing<Service>`,
     implementing the Domain repository interface `<Subject>Repository`.
   - Other Infrastructure adapters: `<Port>Using<Service>`.
   - `<Service>` = technology (Csv, Mbtiles, Http, Ogr2ogr, Tippecanoe, Constants, SystemTime).
   - Test fakes: same rule with `Memory` (`MapSettingsRepositoryUsingMemory`); the fake clock is
     `ClockUsingFixedTime`.
   - Internal Infrastructure helpers that implement no port keep a role name (`ProcessRunner`).
   - Handlers and commands: `<Action>Handler` / `<Action>Command`.
   - Map settings are a repository: the port moves to `Domain\Map\MapSettingsRepository`
     (repositories are domain-owned per CLAUDE.md).
5. **Export date:** without `--export-date`, the import uses the last day of the previous month
   and falls back once to the month before if that file is not yet published (files appear on
   the 1st). `--export-date=YYYY-MM-DD` imports a specific monthly export (reproducible runs,
   tests). All municipalities of one run use the same date.
6. **Download cache:** `data/cache/ruian/{YYYYMMDD}_OB_{municipality}_UKSH.xml.zip`. A cached
   file is never downloaded again; a rerun is offline and cheap. Old months stay in the cache.
7. **Tiles carry only `landType`; the RÚIAN parcel id is the feature id.** tippecanoe options are
   fixed (Architecture). Tiles at most 200 KB; parcels are coalesced only below zoom 14, so
   outlines start at **zoom 14** (at 13 they would outline merged blobs).
8. **Versioned tile URLs with immutable caching.** The tileset version is in the URL;
   `Cache-Control: public, max-age=31536000, immutable`. After a re-import the version changes,
   and `/api/map-config` (now `Cache-Control: no-cache`) announces it. Browsers never ask the
   single-threaded server for a tile twice.
9. **Empty tiles answer 204** with an empty body. MapLibre parses it as an empty tile; a 404
   would be logged as a console error by the browser.
10. **Gzip negotiation:** tiles are stored gzip-compressed. If `Accept-Encoding` contains `gzip`
    the bytes are sent as-is with `Content-Encoding: gzip`; otherwise the handler decompresses.
    MapLibre does not decompress itself.
11. **`PHP_CLI_SERVER_WORKERS: 8`** on the app service (verified to fork workers).
12. **Tests run against a separate compose project** (`parcel-map-test`, `compose.test.yaml`, no
    published port, fixture data). `make check` never restarts the developer's running app.
13. **No automatic import.** Without a tileset the app works, `parcels` is `null`, and the page
    shows a notice telling the operator to run `make data`.
14. **Attribution "ČÚZK, {year of the export}"** linked to the ČÚZK conditions page
    `https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx`
    (the current, Czech page; the English page is outdated and does not mention CC BY 4.0).
15. **Attribute store and legend are deferred to task 004.** Land type colours live in the
    frontend for now.
16. **Coordinate transformation is GDAL's default** (EPSG:5239, 1 m), not pinned.
17. **Fixtures live in `tests/Fixtures/`; `/data/` is fully git-ignored.** The CLAUDE.md line
    about `data/` is updated.
18. **Composer runtime requirements:** `ext-curl` moves from `require-dev` to `require`;
    `ext-pdo_sqlite` and `ext-zlib` are added to `require`. No new packages.

## Verified facts

All verified on 2026-11-19 (real downloads, throwaway containers) unless noted.

**ČÚZK sources**

- RÚIAN VFR monthly municipality file:
  `https://vdp.cuzk.gov.cz/vymenny_format/soucasna/{YYYYMMDD}_OB_{municipality}_UKSH.xml.zip`,
  e.g. `20261031_OB_572659_UKSH.xml.zip` (3,841,299 bytes, `Last-Modified: Sun, 01 Nov 2026
  01:06:42 GMT`, ETag, `Content-Type: application/zip`). One XML file inside, same name without
  `.zip` (38 MB). `vdp.cuzk.cz` links point to `vdp.cuzk.gov.cz`; `services.cuzk.cz` redirects
  (301) to `services.cuzk.gov.cz`. The VDP search page lists the last 3 months.
- [VFR structure document v3.6, 8 Sept 2026](https://cuzk.gov.cz/ruian/Poskytovani-udaju-ISUI-RUIAN-VDP/Vymenny-format-RUIAN-(VFR)/DL058RR2-v5-0-Struktura-a-popis-VFR_final.aspx):
  monthly files are generated on the last day of a month and available the next day (§4.2.1);
  file name `a_b_c_defg` = date, ST/OB, municipality code, U/Z, Z/K, S/H, Z/G/H/O (§4.2.4);
  "V RÚIAN se evidují pouze parcely KN, parcely zjednodušené evidence RÚIAN neobsahuje" (§3.3.13).
- [VFR FAQ 24](https://cuzk.gov.cz/ruian/Poskytovani-udaju-ISUI-RUIAN-VDP/Vymenny-format-RUIAN-(VFR)/FAQ-casto-kladene-otazky-k-VFR/24-Obsahuji-informace-o-parcele-z-RUIAN-take-geom.aspx):
  polygons exist only in areas with a digital map (DKM / KMD).
- Licence ([Podmínky poskytování prostorových dat ČÚZK](https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx),
  [VFR FAQ 4](https://cuzk.gov.cz/ruian/Poskytovani-udaju-ISUI-RUIAN-VDP/Vymenny-format-RUIAN-(VFR)/FAQ-casto-kladene-otazky-k-VFR/4-Je-mozne-ziskat-data-RUIAN-i-pro-komercni-ucely.aspx)):
  CC BY 4.0, free since 1 January 2020; credit "ČÚZK, [year of data currency]"; licence terms
  linked; modifications described. The
  [English page](https://cuzk.gov.cz/English/Practical-Information/Conditions-of-Provision-for-Spatial-Data-and-Netwo/Conditions-for-CUZK-Spatial-Data-Provision.aspx)
  says only "ČÚZK" and does not mention CC BY.
- Alternatives checked and rejected ([dataset list](https://cuzk.gov.cz/Uvod/Produkty-a-sluzby/Otevrena-data/Sady-otevrenych-dat.aspx)):
  - SHP `https://services.cuzk.gov.cz/shp/ku/epsg-5514/{ku}.zip` (weekly, 5.76 MB for 659541):
    polygons in `PARCELY_KN_P` (ID, ID_2, TYPPPD_KOD, KATUZE_KOD, OBEC_KOD), attributes in point
    layer `PARCELY_KN_DEF` (TEXT_KM, PAR_VYMERA, DRUPOZ_KOD, ZPVYPA_KOD, …).
  - INSPIRE CP `https://services.cuzk.gov.cz/gml/inspire/cp/epsg-5514/{ku}.zip` (daily, 5.59 MB,
    145 MB XML): label, nationalCadastralReference, areaValue; no land type.
  - Open VFK `https://services.cuzk.gov.cz/vfk/stavy/{YYYYMM01}/{ku}.zip` (monthly, 7.9 MB):
    groups PKMP, DEBO, ADRM; `&HPAR` header present but 0 `&DPAR` and 0 `&DTEL` rows.
- Code lists: `https://vdp.cuzk.gov.cz/vymenny_format/specialni/20261103_ST_UCIS.xml.zip` (64 KB).
  `DruhyPozemku`: 2 orná půda, 3 chmelnice, 4 vinice, 5 zahrada, 6 ovocný sad, 7 and 8 trvalý
  travní porost, 10 lesní pozemek, 11 vodní plocha, 13 zastavěná plocha a nádvoří, 14 ostatní
  plocha. `DruhyCislovaniParcel`: 1 stavební, 2 pozemková. `ZpusobyVyuzitiPozemku`: 30 codes.

**Data (export of 2026-10-31)**

- Municipality Jičín 572659 (district code 3604) has 5 cadastral areas: 659541 Jičín (12,302
  parcels), 740225 Robousy (2,448), 725838 Popovice u Jičína (1,475), 740217 Moravčice (467),
  771775 Hubálov (280); total 16,972.
- Parcel element `vf:Parcela`: `Id` (10–12 digits), `KmenoveCislo`, optional `PododdeleniCisla`,
  `VymeraParcely` (m²), optional `ZpusobyVyuzitiPozemku`, `DruhCislovaniKod` (1/2),
  `DruhPozemkuKod`, `KatastralniUzemi/Kod`, `PlatiOd`, `IdTransakce`, `RizeniId`, optional
  `BonitovaneDily`, `ZpusobyOchranyPozemku`, `Geometrie/DefinicniBod` (Point) and
  `Geometrie/OriginalniHranice` (Polygon; 826 with holes in the municipality, no multipolygons).
  No LV (ownership sheet) number, in any open source.
- CRS `urn:ogc:def:crs:EPSG::5514`, metres, 2 decimals.
- District 3604: 111 municipalities, 240 cadastral areas (all with a digital map), 272,011
  parcels, 3.41 M vertices, 111 files totalling 53.3 MB zipped. Per-area list kept for task 003.

**GDAL, PROJ, tippecanoe**

- `php:8.6-rc-cli` is Debian 13 trixie. `apt-get install --no-install-recommends gdal-bin
  tippecanoe` gives GDAL 3.10.3 (PROJ 9.6.0) and tippecanoe v2.53.0 (also `tippecanoe-decode`);
  image 571 → 858 MB, about 34 s build. Upstream latest: GDAL 3.13.3, tippecanoe 2.79.0.
- GDAL reads the VFR zip via the GML driver and the bundled `ruian_vf_ob_v1.gfs`. Command (run as
  uid 1000, `HOME=/`):

  ```
  ogr2ogr -f GeoJSONSeq <out>.geojsonl /vsizip/<zip>/<xml> \
    -sql "SELECT Id AS id, DruhPozemkuKod AS landType, OriginalniHranice FROM Parcely WHERE KatastralniUzemiKod = 659541" \
    -dialect OGRSQL -t_srs EPSG:4326 -lco COORDINATE_PRECISION=7
  ```

  12,302 features in 1.2 s; 5 areas 2.7 s; one feature per line, e.g.
  `{ "type": "Feature", "properties": { "id": 97638797010, "landType": 14 }, "geometry": { "type": "Polygon", … } }`.
  It overwrites an existing output; exits 1 for an unreadable input; exits 0 with an empty file
  for an area code not in the export.
- Transformation chosen by GDAL 3.10.3 and 3.14dev: EPSG:5239 "S-JTSK to WGS 84 (5)", 7-parameter
  Helmert, 1 m. Point (-670735.49, -1012253.02) → lon 15.3675035, lat 50.4459482.
- tippecanoe (options in Architecture): 5 areas build in 4.7 s, 3.5 MB, every tile ≤ 200 KB,
  bounds 15.328422,50.395744,15.427924,50.459793. Whole district: 1 min 31 s, 56.5 MB, tiles
  ≤ 200 KB; `coalesced_as_needed` only at z9–z13, none from z14. Tiles are gzip (`1f8b`). It
  writes MBTiles metadata `name, description, version, minzoom, maxzoom, center, bounds,
  antimeridian_adjusted_bounds, type, format=pbf, generator, generator_options, strategies,
  json` (+ `attribution` if given).
- A trimmed fixture (header + only the `Parcely` collection, see Fixtures) zips to 5.7 KB and
  reads in GDAL. Two-area fixture tileset: 40 KB, bounds 15.350516,50.432327,15.403251,50.437799,
  tiles include `12/2222/1381`, `14/8890/5525`, `14/8892/5525`; `14/8891/5525` is inside the
  bounds but empty. `tippecanoe-decode <mbtiles> 14 8890 5525` lists feature `"id": 1753295604`.

**PHP**

- `Pdo\Sqlite` with `[Pdo\Sqlite::ATTR_OPEN_FLAGS => Pdo\Sqlite::OPEN_READONLY]` opens MBTiles
  read-only without deprecations; writes then fail ("attempt to write a readonly database").
  `PDO::SQLITE_ATTR_OPEN_FLAGS` / `PDO::SQLITE_OPEN_READONLY` are deprecated since 8.5 (would
  break `failOnDeprecation`).
- Extensions present in the base image: `curl`, `pdo_sqlite`, `sqlite3`, `zlib` (`gzdecode`).
  `zip` is absent (not needed; GDAL reads the zip).
- Built-in server: a tile read (new connection per request) takes about 2 ms;
  `PHP_CLI_SERVER_WORKERS=4` runs 1 master + 4 worker processes (0 → 1 process). Paths whose last
  segment contains a dot reach `index.php` when no file matches (task 001 fact).

**MapLibre 6.19.0** (npm tarball source)

- `src/util/ajax.ts`: non-2xx → `AJAXError`; 2xx bodies are read as ArrayBuffer.
- `src/source/vector_tile_source.ts`: a 404 tile loads as empty without an error event, but the
  browser still logs the 404 as a console error.
- `src/source/vector_tile_worker_source.ts`: an empty ArrayBuffer parses as an empty tile; gzip
  bytes fail with "please make sure the data is not gzipped …".

**Compose**

- `ports: !reset []` in an override file removes published ports (Compose 5.8.0 locally,
  `docker compose config`). GitHub runner has Compose 2.38.2 (task 001 fact).

**Tile maths for the map centre** (lon 15.351653, lat 50.437045; vector tiles are requested at
the map zoom): z12 `2222/1381`, z13 `4445/2762`, z14 `8890/5525`.

## Steps

Commit 1 on the branch is this task file (`docs(tasks): add 002 Jičín parcels from ČÚZK`). Each
step below is one commit; every commit passes `make check`.

1. **Naming rules** (`refactor: name use cases and repositories by their role (#002)`). CLAUDE.md
   "Code rules" gains the naming rules; all task 001 classes are renamed (Rename list). No
   behaviour change: the existing API and E2E tests pass unchanged; unit tests and the fake
   follow the renames. README Architecture text is checked for old names.
2. **CLI walking skeleton** (`feat(cli): add bin/console with a command list (#002)`). The
   operator can run `bin/console`: it lists the available commands (none yet) and exits 0; an
   unknown command exits 2.
3. **Import** (`feat(import): build the parcel tileset of Jičín from the RÚIAN export (#002)`).
   `make data` (`bin/console parcels:import [--export-date=YYYY-MM-DD]`) downloads (cached),
   converts the 5 covered areas, builds and publishes `data/tiles/parcels.mbtiles`. Includes the
   Dockerfile tools, `config/coverage.csv`, phpunit `integration` suite, `make test-integration`,
   `make data`, fixtures, README data section and CLAUDE.md updates.
4. **Tile endpoint** (`feat(tiles): serve parcel vector tiles from the tileset (#002)`).
   `GET /tiles/parcels/{version}/{z}/{x}/{y}.pbf`; router path parameters; request headers;
   `PHP_CLI_SERVER_WORKERS`; fixture tileset; separate test compose project.
5. **Map layer** (`feat(map): show the parcels coloured by land type (#002)`).
   `/api/map-config` gains `parcels`; the map draws the fill and outline layers with the ČÚZK
   credit. Without data, `parcels` is `null` and the map shows only OSM.
6. **No-data notice** (`feat(map): tell the user when parcel data has not been imported (#002)`).
   With `parcels: null` the page shows the notice.

## Rename list

Step 1 (task 001 code):

| Old | New |
|---|---|
| `src/Application/Map/GetMapConfiguration.php` (`ParcelMap\Application\Map\GetMapConfiguration`) | `src/Application/Map/GetMapConfigurationUseCase.php` (`…\GetMapConfigurationUseCase`) |
| `src/Application/Map/MapSettings.php` (port, `Application\Map\MapSettings`) | `src/Domain/Map/MapSettingsRepository.php` (`ParcelMap\Domain\Map\MapSettingsRepository`), same two methods |
| `src/Infrastructure/Map/StaticMapSettings.php` | `src/Infrastructure/Map/MapSettingsRepositoryUsingConstants.php` (class comment kept) |
| `tests/Fake/InMemoryMapSettings.php` | `tests/Fake/MapSettingsRepositoryUsingMemory.php` |
| `tests/Unit/Application/Map/GetMapConfigurationTest.php` | `tests/Unit/Application/Map/GetMapConfigurationUseCaseTest.php` |

Unchanged: Domain `Map` value objects and exceptions, DTOs (`MapConfigurationOutput`,
`MapViewOutput`, `BaseMapOutput`), handlers, `Handler/Http/*`. `Container` follows the renames.
The use case constructor becomes `__construct(private MapSettingsRepository $settings)` in step 1.

Task 002 names (already following the rules; listed for review):

| Kind | Name |
|---|---|
| Use cases | `Application\Import\ImportParcelsUseCase`, `Application\Map\GetParcelTileUseCase` |
| Application ports | `Application\Import\RuianExportDownloader`, `ParcelConverter`, `TilesetBuilder`, `TilesetPublisher`; `Application\Clock\Clock` |
| Domain repositories | `Domain\Cadastre\CoverageRepository`, `Domain\Map\ParcelTilesetRepository`, `Domain\Map\MapSettingsRepository` |
| Repository adapters | `Infrastructure\Cadastre\CoverageRepositoryUsingCsv`, `Infrastructure\Map\ParcelTilesetRepositoryUsingMbtiles`, `Infrastructure\Map\MapSettingsRepositoryUsingConstants` |
| Other adapters | `Infrastructure\Import\RuianExportDownloaderUsingHttp`, `ParcelConverterUsingOgr2ogr`, `TilesetBuilderUsingTippecanoe`, `TilesetPublisherUsingMbtiles`; `Infrastructure\Clock\ClockUsingSystemTime` |
| Internal helper | `Infrastructure\Process\ProcessRunner` |
| Fakes (`tests/Fake/`) | `CoverageRepositoryUsingMemory`, `ParcelTilesetRepositoryUsingMemory`, `MapSettingsRepositoryUsingMemory`, `RuianExportDownloaderUsingMemory`, `ParcelConverterUsingMemory`, `TilesetBuilderUsingMemory`, `TilesetPublisherUsingMemory`, `ClockUsingFixedTime` |
| Handlers / commands | `Handler\GetParcelTileHandler`, `Handler\ParcelTileRoute`, `Command\ImportParcelsCommand` |

**CLAUDE.md "Code rules" text** (add as a new sub-list "Naming" under Code rules, step 1):

- Use cases are named `<Verb><Subject>UseCase` and have one public method `execute()`.
- Other Application classes are named after their role: ports (`…Converter`, `…Builder`,
  `…Publisher`, `…Downloader`, `Clock`), DTOs (`…Input`, `…Output`), exceptions (what went wrong).
- Infrastructure classes that load, save or update domain data are named
  `<Subject>RepositoryUsing<Service>` and implement the Domain repository interface
  `<Subject>Repository`.
- Other Infrastructure adapters are named `<Port>Using<Service>`.
- `<Service>` is the technology (Csv, Mbtiles, Http, Ogr2ogr, Tippecanoe, Constants, SystemTime),
  not the data provider.
- Test fakes follow the same rule with `Memory` (`MapSettingsRepositoryUsingMemory`); a fixed
  clock is `ClockUsingFixedTime`.
- Infrastructure helpers that implement no port keep a role name (`ProcessRunner`).
- Handlers and commands are named `<Action>Handler` / `<Action>Command`.

## Architecture

Paths and namespaces are binding. Method bodies, private helpers and test code are left to the
implementation. Conventions from task 001 apply: value objects `final readonly` with `equals()`;
exceptions `final class … extends \InvalidArgumentException` (invalid values) or
`\RuntimeException` (failures) with named constructors that build the message.

### Domain

**`ParcelMap\Domain\Cadastre`** (new, step 3)

| Class | Constructor / factories | Invariants and behaviour |
|---|---|---|
| `CadastralAreaCode` | `__construct(public int $value)` | `100000 ≤ value ≤ 999999` → `InvalidCadastralAreaCode::notSixDigits(int)` |
| `MunicipalityCode` | `__construct(public int $value)` | `100000 ≤ value ≤ 999999` → `InvalidMunicipalityCode::notSixDigits(int)` |
| `CadastralArea` | `__construct(public CadastralAreaCode $code, public string $name, public MunicipalityCode $municipality)` | `trim(name) !== ''` → `InvalidCadastralArea::emptyName(int $code)`. `equals()` compares the code only (identity). |
| `Coverage` | `__construct(CadastralArea ...$areas)` | at least one area → `InvalidCoverage::empty()`; unique codes → `InvalidCoverage::duplicateArea(int)`. `areas(): list<CadastralArea>` (given order); `municipalities(): list<MunicipalityCode>` (unique, order of first appearance). |
| `ExportDate` | private constructor; `fromString(string $date)` (`Y-m-d`); `latestPublishedOn(\DateTimeImmutable $now)` | must be the last day of a month → `InvalidExportDate::notADate(string)`, `::notLastDayOfMonth(string)`. `latestPublishedOn` = last day of the month before `$now`'s month (date part of `$now`). `previous()` = last day of the month before. `year(): int`, `toString(): string` (`Y-m-d`), `equals()`. |

- `interface CoverageRepository { public function get(): Coverage; }` throws
  `CoverageUnavailable` (`final class … extends \RuntimeException`, `::because(string $reason)`).

**`ParcelMap\Domain\Map`** (existing namespace)

| Class | Constructor / factories | Invariants and behaviour |
|---|---|---|
| `MapSettingsRepository` (step 1) | interface | `initialView(): MapView`, `baseMap(): BaseMap` |
| `TileCoordinate` (step 4) | `__construct(public int $zoom, public int $x, public int $y)` | `0 ≤ zoom ≤ 24`, `0 ≤ x < 2^zoom`, `0 ≤ y < 2^zoom` → `InvalidTileCoordinate::outOfRange(int $zoom, int $x, int $y)` |
| `Bounds` (step 4) | `__construct(public Coordinates $southWest, public Coordinates $northEast)` | `southWest.longitude < northEast.longitude` and `southWest.latitude < northEast.latitude` → `InvalidBounds::notOrdered()` |
| `TilesetVersion` (step 3) | `__construct(public string $value)`; `fromTime(\DateTimeImmutable $time)` | matches `/^[A-Za-z0-9-]{1,32}$/` → `InvalidTilesetVersion::malformed(string)`. `fromTime` = UTC `YmdHis`, e.g. `20261120093000`. |
| `ParcelTileset` (step 4) | `__construct(public TilesetVersion $version, public ExportDate $exportDate, public Bounds $bounds, public int $minZoom, public int $maxZoom)` | `0 ≤ minZoom ≤ maxZoom ≤ 24` → `InvalidParcelTileset::zoomRange(int, int)`. `attribution(): Attribution` = text `ČÚZK, {exportDate.year}`, URL = the ČÚZK conditions page (Decision 14) as a class constant. `coversZoom(int $zoom): bool`. |
| `VectorTile` (step 4) | private constructor; `fromGzippedData(string $data)`, `empty()` | `fromGzippedData` requires the data to start with `"\x1f\x8b"` → `InvalidVectorTile::notGzipped()`. `isEmpty(): bool`, `gzippedData(): string` (`''` when empty). |

- `interface ParcelTilesetRepository` (step 4):
  - `current(): ParcelTileset` — throws `ParcelDataNotImported`;
  - `tile(TileCoordinate $coordinate): VectorTile` — returns `VectorTile::empty()` when the
    tileset has no tile there; throws `ParcelDataNotImported`.
- `ParcelDataNotImported` (`final class … extends \RuntimeException`, `::create()`) and
  `ParcelTilesetUnreadable` (`… extends \RuntimeException`, `::because(string)`, for a tileset
  file without the expected metadata; it becomes a 500 through the kernel).

### Application

**Clock** (step 3): `ParcelMap\Application\Clock\Clock` with `now(): \DateTimeImmutable` (UTC).

**Import** — namespace `ParcelMap\Application\Import` (step 3).

Ports:

```php
interface RuianExportDownloader
{
    /** @throws ExportNotPublished|SourceDownloadFailed */
    public function download(MunicipalityCode $municipality, ExportDate $date): DownloadedExport;
}

interface ParcelConverter
{
    /** @throws ConversionFailed|CadastralAreaNotInExport */
    public function convert(DownloadedExport $export, CadastralArea $area): ConvertedParcels;
}

interface TilesetBuilder
{
    /** @param non-empty-list<ConvertedParcels> $parcels  @throws TileBuildFailed */
    public function build(array $parcels): BuiltTileset;
}

interface TilesetPublisher
{
    /** @throws TilesetPublishFailed */
    public function publish(BuiltTileset $tileset, TilesetVersion $version, ExportDate $exportDate): void;
}
```

Handles passed between ports (`final readonly`, public promoted properties; paths are opaque to
the use case):

- `DownloadedExport(MunicipalityCode $municipality, ExportDate $date, string $path)`
- `ConvertedParcels(CadastralAreaCode $area, string $path, int $parcelCount)` (`parcelCount ≥ 1`)
- `BuiltTileset(string $path)`

Use case `final readonly class ImportParcelsUseCase`:

- Constructor: `CoverageRepository $coverage, RuianExportDownloader $downloader, ParcelConverter
  $converter, TilesetBuilder $builder, TilesetPublisher $publisher, Clock $clock`.
- `execute(ImportParcelsInput $input): ImportParcelsOutput`.
- Algorithm:
  1. Export date: `$input->exportDate === null` → `ExportDate::latestPublishedOn($clock->now())`
     with one fallback allowed; otherwise `ExportDate::fromString()` (an `InvalidExportDate`
     becomes `InvalidImportRequest`) and no fallback.
  2. `$coverage->get()`; `CoverageUnavailable` and `InvalidCoverage` become `CoverageNotAvailable`.
  3. Download each municipality of `Coverage::municipalities()` in order. If the **first**
     download throws `ExportNotPublished` and the fallback is allowed, switch to
     `$date->previous()` and retry it once; every later download uses that date. Any other
     `ExportNotPublished` propagates.
  4. Convert every covered area (coverage order) from its municipality's export.
  5. Build one tileset from all converted files.
  6. `TilesetVersion::fromTime($clock->now())`, then publish with the export date.
  7. Return the output.
- Input `ImportParcelsInput(?string $exportDate)`.
- Output `ImportParcelsOutput(string $exportDate /* Y-m-d */, string $tilesetVersion, list<ImportedAreaOutput> $areas, int $totalParcels)`;
  `ImportedAreaOutput(int $code, string $name, int $parcelCount)`.
- Exceptions (all in this namespace):
  - `InvalidImportRequest extends \InvalidArgumentException` (`::invalidExportDate(string $given, string $reason)`).
  - `abstract class ImportFailed extends \RuntimeException`, with final subclasses:
    `ExportNotPublished` (`::for(int $municipality, string $date)` → "The RÚIAN export of
    2026-10-31 for municipality 572659 is not published."), `SourceDownloadFailed`
    (`::because(string)`), `ConversionFailed` (`::because(string)`), `CadastralAreaNotInExport`
    (`::for(int $area, int $municipality, string $date)`), `TileBuildFailed` (`::because(string)`),
    `TilesetPublishFailed` (`::because(string)`), `CoverageNotAvailable` (`::because(string)`).

**Map** — namespace `ParcelMap\Application\Map`.

- `GetMapConfigurationUseCase` (step 5 change): constructor
  `(MapSettingsRepository $settings, ParcelTilesetRepository $tilesets)`. Output gains
  `?ParcelLayerOutput $parcels` (null when `ParcelDataNotImported`):

  ```php
  final readonly class ParcelLayerOutput
  {
      public function __construct(
          public string $tilesetVersion,
          public int $minZoom,
          public int $maxZoom,
          public float $west,
          public float $south,
          public float $east,
          public float $north,
          public string $attributionText,
          public string $attributionUrl,
      ) {}
  }
  ```

- `GetParcelTileUseCase` (step 4): constructor `(ParcelTilesetRepository $tilesets)`;
  `execute(ParcelTileInput $input): ParcelTileOutput`.
  - `ParcelTileInput(string $version, int $zoom, int $x, int $y)`;
    `ParcelTileOutput(bool $empty, string $gzippedData)` (`''` when empty).
  - Throws `ParcelTileNotFound extends \RuntimeException` with `::noDataImported()`,
    `::unknownVersion(string)`, `::invalidCoordinate(int $zoom, int $x, int $y)`.
  - Logic: `current()` (`ParcelDataNotImported` → `noDataImported`); a malformed or different
    version → `unknownVersion`; `new TileCoordinate()` (`InvalidTileCoordinate` →
    `invalidCoordinate`); a zoom outside `minZoom..maxZoom` → empty output; otherwise `tile()`.

### Infrastructure

**`Infrastructure\Map\MapSettingsRepositoryUsingConstants`** (renamed in step 1, values unchanged).

**`Infrastructure\Cadastre\CoverageRepositoryUsingCsv(string $file)`** (step 3)

- UTF-8 CSV, header exactly `code,name,municipality_code`, one area per row, read with
  `fgetcsv` (explicit `escape: ''`). Builds the domain objects; a missing/unreadable file, a wrong
  header, a row with the wrong number of fields, non-numeric codes or a domain exception →
  `CoverageUnavailable::because(...)` naming the file and line.
- `config/coverage.csv` (committed):

  ```
  code,name,municipality_code
  659541,Jičín,572659
  740225,Robousy,572659
  725838,Popovice u Jičína,572659
  740217,Moravčice,572659
  771775,Hubálov,572659
  ```

**`Infrastructure\Import\RuianExportDownloaderUsingHttp(string $baseUrl, string $cacheDir)`** (step 3)

- File name `{YYYYMMDD}_OB_{municipality}_UKSH.xml.zip` (`YYYYMMDD` = export date without
  dashes); URL `$baseUrl . $fileName`; cache path `$cacheDir/$fileName`.
- Cached file exists → return it, no request.
- Otherwise curl GET into `$cachePath . '.part'`: `CURLOPT_FOLLOWLOCATION` (max 3 redirects),
  connect timeout 10 s, total timeout 300 s, `CURLOPT_FAILONERROR` off, User-Agent
  `parcel-map-importer`. Creates the cache dir if missing.
  - HTTP 404 → delete `.part`, `ExportNotPublished`.
  - Transport error or status ≠ 200 → delete `.part`, `SourceDownloadFailed` (URL + status/error).
  - Body not starting with `PK\x03\x04` → delete `.part`, `SourceDownloadFailed`.
  - Success → `rename()` to the cache path.
- Production base URL `https://vdp.cuzk.gov.cz/vymenny_format/soucasna/`.

**`Infrastructure\Process\ProcessRunner`** (step 3): `run(list<string> $command): ProcessResult`
(`exitCode`, `stdout`, `stderr`) using `proc_open` with an argument array (no shell).

**`Infrastructure\Import\ParcelConverterUsingOgr2ogr(ProcessRunner $runner, string $workDir)`** (step 3)

- Output `$workDir/parcels/{areaCode}.geojsonl` (directory created if missing).
- Runs:

  ```
  ogr2ogr -f GeoJSONSeq <output> /vsizip/<export path>/<export file name without .zip>
    -sql "SELECT Id AS id, DruhPozemkuKod AS landType, OriginalniHranice FROM Parcely WHERE KatastralniUzemiKod = <code>"
    -dialect OGRSQL -t_srs EPSG:4326 -lco COORDINATE_PRECISION=7
  ```

- Exit code ≠ 0 → `ConversionFailed` with stderr. Counts non-empty lines; 0 → delete the file and
  throw `CadastralAreaNotInExport`. Returns `ConvertedParcels`.

**`Infrastructure\Import\TilesetBuilderUsingTippecanoe(ProcessRunner $runner, string $workDir)`** (step 3)

- Output `$workDir/parcels.mbtiles`; temporary directory `$workDir/tmp` (created if missing).
- Runs (input files appended in the given order):

  ```
  tippecanoe --output=<output> --force --quiet --layer=parcels --name=Parcels
    --minimum-zoom=9 --maximum-zoom=16 --use-attribute-for-id=id --include=landType
    --detect-shared-borders --maximum-tile-bytes=200000 --coalesce-densest-as-needed
    --simplification=4 --simplify-only-low-zooms --extend-zooms-if-still-dropping
    --no-tiny-polygon-reduction-at-maximum-zoom --read-parallel
    --temporary-directory=<workDir>/tmp <input files…>
  ```

- Exit code ≠ 0 → `TileBuildFailed` with stderr. A class comment explains the options (tile byte
  budget for smoothness, coalescing only below z14, ids for task 004).

**`Infrastructure\Import\TilesetPublisherUsingMbtiles(string $targetFile)`** (step 3)

- Opens the built file read-write with `Pdo\Sqlite`, `INSERT OR REPLACE INTO metadata (name,
  value)` the rows `parcel_map_version` (version) and `parcel_map_export_date` (`Y-m-d`), closes
  the connection, creates the target directory, then `rename()`s the file to `$targetFile`
  (same filesystem, so it is atomic; running requests keep reading the old file). Any failure →
  `TilesetPublishFailed`.

**`Infrastructure\Map\ParcelTilesetRepositoryUsingMbtiles(string $file)`** (step 4)

- File missing → `ParcelDataNotImported`. Opens with `new Pdo\Sqlite('sqlite:' . $file, options:
  [Pdo\Sqlite::ATTR_OPEN_FLAGS => Pdo\Sqlite::OPEN_READONLY])` (never the deprecated `PDO::SQLITE_*`).
- `current()` reads metadata `parcel_map_version`, `parcel_map_export_date`, `bounds`
  (`w,s,e,n`), `minzoom`, `maxzoom`; a missing row or invalid value → `ParcelTilesetUnreadable`.
- `tile()` selects `tile_data` with `zoom_level = z`, `tile_column = x`,
  `tile_row = (2^z − 1) − y` (TMS); no row → `VectorTile::empty()`.

**`Infrastructure\Clock\ClockUsingSystemTime`** (step 3): `new \DateTimeImmutable('now', new \DateTimeZone('UTC'))`.

**Data layout** (data dir = `PARCEL_MAP_DATA_DIR`, default `data`, resolved against the project dir
when relative): `cache/ruian/` downloads, `build/` intermediate files, `tiles/parcels.mbtiles`
published tileset.

### Handlers (HTTP)

**Plumbing changes** (`Handler\Http`, step 4):

- `Request` gains `public array $headers` (`array<string, string>`, lower-case names, from
  `$_SERVER` `HTTP_*` keys: `HTTP_ACCEPT_ENCODING` → `accept-encoding`) and
  `public array $pathParameters` (`array<string, string>`), both defaulting to `[]`;
  `header(string $name, string $default = ''): string` (case-insensitive);
  `withPathParameters(array $parameters): self`.
- `Route` paths may contain `{name}` placeholders; a placeholder matches `[A-Za-z0-9_-]+`; all other
  characters are literal (e.g. `.pbf`). Static paths keep exact-match behaviour.
- `Router` passes the matched values via `withPathParameters()`; 404/405 behaviour unchanged for
  pattern routes.
- `Response` gains `withHeader(string $name, string $value): self` and
  `noContent(array $headers = []): self` (204, empty body).

**`Handler\ParcelTileRoute`** (step 4): `final class` with
`public const string PATTERN = '/tiles/parcels/{version}/{z}/{x}/{y}.pbf'` and
`public static function urlTemplate(string $version): string` returning the pattern with
`{version}` replaced (so `{z}/{x}/{y}` stay).

**`GET /tiles/parcels/{version}/{z}/{x}/{y}.pbf`** → `GetParcelTileHandler(GetParcelTileUseCase)` (step 4)

- `z`, `x`, `y` must be `ctype_digit` with at most 9 digits; otherwise 404 `{"error":"not_found"}`.
- Results:

  | Case | Status | Headers | Body |
  |---|---|---|---|
  | tile, `Accept-Encoding` contains `gzip` | 200 | `Content-Type: application/vnd.mapbox-vector-tile`, `Content-Encoding: gzip`, `Cache-Control: public, max-age=31536000, immutable`, `Vary: Accept-Encoding` | gzip bytes |
  | tile, no gzip accepted | 200 | same without `Content-Encoding` | `gzdecode` result |
  | empty tile | 204 | `Cache-Control: public, max-age=31536000, immutable` | empty |
  | `ParcelTileNotFound` (no data, unknown version, invalid coordinate, bad digits) | 404 | `Content-Type: application/json` | `{"error":"not_found"}` |
  | other method | 405 | `Allow: GET` | `{"error":"method_not_allowed"}` |

**`GET /api/map-config`** (step 5): adds `Cache-Control: no-cache` and the `parcels` member:

```json
{
  "initialView": { "center": { "longitude": 15.351653, "latitude": 50.437045 }, "zoom": 12 },
  "baseMap": { "…": "unchanged" },
  "parcels": {
    "tileUrlTemplate": "/tiles/parcels/20261120093000/{z}/{x}/{y}.pbf",
    "minZoom": 9,
    "maxZoom": 16,
    "bounds": { "west": 15.328422, "south": 50.395744, "east": 15.427924, "north": 50.459793 },
    "attribution": {
      "text": "ČÚZK, 2026",
      "url": "https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx"
    }
  }
}
```

Without an imported tileset: `"parcels": null`. `tileUrlTemplate` comes from
`ParcelTileRoute::urlTemplate($output->parcels->tilesetVersion)`.

**Route table** (Container): existing `GET /api/map-config`, `GET /`; step 4 adds
`GET ParcelTileRoute::PATTERN`.

### Commands (CLI)

**Plumbing** (`Command\Console`, step 2):

- `interface Command { name(): string; description(): string; run(list<string> $arguments, ConsoleOutput $output): int; }`
- `interface ConsoleOutput { writeLine(string $line): void; writeError(string $line): void; }`;
  `final class StreamConsoleOutput implements ConsoleOutput` (STDOUT / STDERR).
- `final class ExitCode` constants `SUCCESS = 0`, `FAILURE = 1`, `USAGE = 2`.
- `final readonly class ConsoleApplication(Command ...$commands)`:
  `run(list<string> $arguments, ConsoleOutput $output): int` (arguments without the script name).
  - No arguments → stdout `Available commands:` then one line per command
    `  <name>  <description>`; exit 0.
  - Unknown command → stderr `Unknown command "<name>".` then the list; exit 2.
  - Otherwise runs the command with the remaining arguments; any uncaught `\Throwable` → stderr
    `Unexpected error: <message>` and the throwable string; exit 1.
- `bin/console` (executable, git mode 100755, `#!/usr/bin/env php`, strict types): requires the
  autoloader, builds `Container`, calls `console()->run(array_slice($argv, 1), new StreamConsoleOutput())`
  and exits with its result. No other logic.

**`bin/console parcels:import [--export-date=YYYY-MM-DD]`** → `ImportParcelsCommand(ImportParcelsUseCase)` (step 3)

- Description: `Download the RÚIAN export and build the parcel tileset.`
- Any other argument → stderr `Unknown option "<arg>".` and usage
  `Usage: bin/console parcels:import [--export-date=YYYY-MM-DD]`; exit 2.
- Success (exit 0), stdout:

  ```
  Imported the RÚIAN export of 2026-10-31.
  659541 Jičín: 12302 parcels
  740225 Robousy: 2448 parcels
  725838 Popovice u Jičína: 1475 parcels
  740217 Moravčice: 467 parcels
  771775 Hubálov: 280 parcels
  Published tileset 20261120093000 with 16972 parcels.
  ```

- `InvalidImportRequest` → stderr `Invalid --export-date: <message>`; exit 2.
- `ImportFailed` → stderr `Import failed: <message>`; exit 1.

### Frontend

- **`frontend/src/mapConfiguration.ts`** (step 5): `MapConfiguration` gains
  `readonly parcels: ParcelLayerConfiguration | null` with

  ```ts
  export interface ParcelLayerConfiguration {
    readonly tileUrlTemplate: string;
    readonly minZoom: number;
    readonly maxZoom: number;
    readonly bounds: { readonly west: number; readonly south: number; readonly east: number; readonly north: number };
    readonly attribution: { readonly text: string; readonly url: string };
  }
  ```

  Validation: the `parcels` key must exist; `null` or an object whose `tileUrlTemplate` is a
  string starting with `/` and containing `{z}`, `{x}`, `{y}`, whose numbers are finite and whose
  attribution fields are strings; otherwise `MapConfigurationError`.
- **`frontend/src/landTypes.ts`** (step 5): `LAND_TYPE_COLOURS: ReadonlyMap<number, string>` and
  `landTypeFillColour(): ExpressionSpecification`, a `match` on `['get', 'landType']`:

  | Code | Land type | Colour |
  |---|---|---|
  | 2 | arable land | `#f2d16b` |
  | 3 | hop field | `#b5cf6b` |
  | 4 | vineyard | `#a96ab2` |
  | 5 | garden | `#9fd27f` |
  | 6 | orchard | `#5fb05f` |
  | 7, 8 | permanent grassland | `#c7e59a` |
  | 10 | forest | `#2e8b57` |
  | 11 | water area | `#5aa9e6` |
  | 13 | built-up area and courtyard | `#d9735b` |
  | 14 | other area | `#bdbdbd` |
  | other | fallback | `#9e9e9e` |

- **`frontend/src/mapOptions.ts`** (step 5): `createMapOptions(configuration, container, origin: string)`.
  When `parcels` is not null:
  - source `parcels`: `{ type: 'vector', tiles: [origin + tileUrlTemplate], minzoom, maxzoom,
    bounds: [west, south, east, north], attribution: '<a href="URL">TEXT</a>' }` (escaped as for
    the base map);
  - layer `parcels-fill`: `{ type: 'fill', source: 'parcels', 'source-layer': 'parcels', paint:
    { 'fill-color': landTypeFillColour(), 'fill-opacity': 0.45 } }`;
  - layer `parcels-outline`: `{ type: 'line', source: 'parcels', 'source-layer': 'parcels',
    minzoom: 14, paint: { 'line-color': '#3d3d3d', 'line-opacity': 0.7, 'line-width':
    ['interpolate', ['linear'], ['zoom'], 14, 0.5, 18, 1.5] } }`;
  - layer order: `base-map`, `parcels-fill`, `parcels-outline`.
  When `parcels` is null: only the base map (as today).
- **`frontend/src/main.ts`**: passes `window.location.origin`. Step 6: when `parcels` is null it
  appends `<p class="map-notice" role="status">Parcel data has not been imported yet. Run make data,
  then reload this page.</p>` to `body` (map still created). No `move`/`render` listeners.
- **`frontend/src/app.css`** (step 6): `.map-notice`: absolute, top centre, readable box, does not
  block the map controls.

### Wiring

`Bootstrap\Container`:

- Step 1: uses the renamed classes.
- Step 2: `console(): ConsoleApplication` (no commands).
- Step 3: constructor `__construct(private string $projectDir, private array $environment = [])`
  (`array<string, string>`); `public/index.php` and `bin/console` pass `getenv()`. Reads
  `PARCEL_MAP_DATA_DIR` (default `data`), `PARCEL_MAP_COVERAGE_FILE` (default
  `config/coverage.csv`), `RUIAN_EXPORT_BASE_URL` (default
  `https://vdp.cuzk.gov.cz/vymenny_format/soucasna/`); relative paths resolve against the project
  dir. `console()` registers `ImportParcelsCommand(new ImportParcelsUseCase(new CoverageRepositoryUsingCsv(...), new RuianExportDownloaderUsingHttp(baseUrl, data/cache/ruian), new ParcelConverterUsingOgr2ogr(runner, data/build), new TilesetBuilderUsingTippecanoe(runner, data/build), new TilesetPublisherUsingMbtiles(data/tiles/parcels.mbtiles), new ClockUsingSystemTime()))`.
- Step 4: route for `GetParcelTileHandler(new GetParcelTileUseCase(new ParcelTilesetRepositoryUsingMbtiles(data/tiles/parcels.mbtiles)))`.
- Step 5: `GetMapConfigurationUseCase` also receives the tileset repository.

Wiring only; no other logic.

### Tooling and repository files

- **Dockerfile** (step 3): add `gdal-bin tippecanoe` to the existing `apt-get install`, with a
  comment (one image for now; see README).
- **compose.yaml** (step 4): `app` gets `image: parcel-map-app` (shared by both projects) and
  `PHP_CLI_SERVER_WORKERS: '8'` in `environment`.
- **compose.test.yaml** (step 4):

  ```yaml
  services:
    app:
      ports: !reset []
      environment:
        PARCEL_MAP_DATA_DIR: tests/Fixtures/data
  ```

- **Makefile**:
  - Step 3: `data` (needs `build`; `$(PHP) bin/console parcels:import`), `test-integration`
    (`$(PHP) vendor/bin/phpunit --testsuite integration`); `php-tests` = `install-php test-unit
    test-integration test-api`; `test` adds `test-integration`; `.PHONY` updated.
  - Step 4: `TEST_COMPOSE := $(COMPOSE) -p parcel-map-test -f compose.yaml -f compose.test.yaml`;
    `up-app` = `$(TEST_COMPOSE) up -d --wait --no-deps app`; `test-api` and `test-e2e` run their
    runners with `$(TEST_COMPOSE) run --rm --no-deps app|node …`; `down` also runs
    `$(TEST_COMPOSE) down`. `CHECK_TARGETS` unchanged; CI workflow unchanged.
- **phpunit.xml.dist** (step 3): suite `integration` → `tests/Integration`.
- **phpstan.neon.dist** (step 2): `paths` adds `bin/console`. **.php-cs-fixer.dist.php** (step 2):
  `->append([__FILE__, __DIR__ . '/bin/console'])`. If either tool cannot analyse the
  extensionless file, log it in the implementation log and keep `bin/console` minimal.
- **composer.json** (step 3): Decision 18.
- **.gitignore** (step 3): `/data/`. **.dockerignore** (step 3): `data`.

### Fixtures (`tests/Fixtures/`, documented in `tests/Fixtures/README.md`)

- `ruian/20261031_OB_572659_UKSH.xml.zip` (step 3, about 6 KB): the real export of 2026-10-31,
  trimmed. Keep the XML prolog, root element with all namespace declarations, the whole
  `vf:Hlavicka`, then `<vf:Data><vf:Parcely>…</vf:Parcely></vf:Data>` containing only:
  - the 22 parcels of 659541 whose `DefinicniBod` lies within ±75 m of S-JTSK
    (−671975.47, −1013096.26) (the map centre): ids 1753292604, 1753293604, 1753294604,
    1753295604, 1753296604, 1753297604, 1753298604, 1753299604, 1753300604, 1753301604,
    1753303604, 1753304604, 1753305604, 1753306604, 1753309604, 1753310604, 1753691604,
    1753692604, 1761441604, 2654592604, 64343200010, 64343201010 (20 building plots, 2 land
    plots, land types 13 and 14, one with a hole);
  - the first 3 parcels of 740225 in file order: 95522753010, 99225194010, 100751793010.

  Drop every other collection (Obce, KatastralniUzemi, Ulice, StavebniObjekty, …). Zip entry name
  `20261031_OB_572659_UKSH.xml`. The trimming is a one-off (any throwaway script, not committed);
  the README records the rule above.
- `coverage/fixture.csv` (step 3): rows `659541,Jičín,572659` and `740225,Robousy,572659`.
- `geojson/659541.geojsonl` (step 3): output of `ParcelConverterUsingOgr2ogr` for 659541 on the
  fixture zip (22 lines), input for the builder test.
- `data/tiles/parcels.mbtiles` (step 4, about 40 KB): produced by `bin/console parcels:import
  --export-date=2026-10-31` with `PARCEL_MAP_COVERAGE_FILE=tests/Fixtures/coverage/fixture.csv`,
  a temporary `PARCEL_MAP_DATA_DIR` whose `cache/ruian/` holds a copy of the fixture zip, and
  `RUIAN_EXPORT_BASE_URL=http://127.0.0.1:9/`. The README records the resulting version
  (`FIXTURE_TILESET_VERSION`) and bounds, which the tests use as constants.
- `tests/Integration/Support/ruian-router.php` (step 3): router script for `php -S`: serves files
  from `tests/Fixtures/ruian/`; a request for municipality `500000` answers 500; `500001` answers
  200 with an HTML body; anything else missing answers 404.

## Test scenarios

Write the outer test of each step first (step 1: the existing suites; steps 2–3: CLI API tests;
step 4: HTTP API tests; steps 5–6: E2E) and watch it fail for the right reason.

### Unit

- **Step 1:** `GetMapConfigurationUseCaseTest` (renamed, same assertions, fake
  `MapSettingsRepositoryUsingMemory`).
- **Step 2, `tests/Unit/Command/Console/`:** `ConsoleApplication` lists commands with name and
  description and exits 0 without arguments; runs the named command with the remaining arguments
  and returns its exit code; unknown command → stderr message and exit 2; a throwing command →
  `Unexpected error` on stderr and exit 1. Use an in-memory `ConsoleOutput` and a stub command.
- **Step 3, Domain `Cadastre`:**
  - codes: accept 100000 and 999999; reject 99999 and 1000000;
  - `CadastralArea`: rejects an empty or whitespace name; equals by code;
  - `Coverage`: rejects no areas and a duplicate code; `municipalities()` is unique and in order;
  - `ExportDate`: accepts `2026-10-31` and `2024-02-29`; rejects `2026-10-30`, `2025-02-28x`,
    `2026-13-31`; `latestPublishedOn(2026-11-20)` = 2026-10-31, `(2026-01-05)` = 2025-12-31,
    `(2026-03-01)` = 2026-02-28; `previous()` of 2026-03-31 = 2026-02-28; `year()`.
- **Step 3, Domain `Map`:** `TilesetVersion` accepts `20261120093000`, rejects `''`, 33 chars,
  `a/b`; `fromTime` formats in UTC (a `+01:00` time converts).
- **Step 3, `ImportParcelsUseCaseTest`** (fakes for all ports, `ClockUsingFixedTime`):
  - imports all covered areas: downloads each municipality once, converts each area from its
    municipality's export, builds from all converted files in coverage order, publishes with the
    version from the clock and the export date; output lists areas with counts and the total;
  - no export date given + first download not published → retries the previous month once and
    uses it for all municipalities; not published twice → `ExportNotPublished`;
  - explicit export date → no fallback; invalid date → `InvalidImportRequest`, nothing downloaded;
  - coverage unavailable → `CoverageNotAvailable`;
  - a converter, builder or publisher failure propagates and nothing is published after it.
- **Step 4, Domain:** `TileCoordinate` accepts 0/0/0 and 24/(2^24−1)/(2^24−1); rejects zoom −1
  and 25, x or y = 2^zoom, negatives. `Bounds` rejects equal or swapped corners.
  `ParcelTileset` rejects min > max and values outside 0–24; `attribution()` text `ČÚZK, 2026`
  for export 2026-10-31 with the conditions URL; `coversZoom`. `VectorTile` rejects non-gzip
  data; `empty()` is empty.
- **Step 4, `GetParcelTileUseCaseTest`:** returns the tile bytes; empty tile → `empty: true`;
  wrong version, malformed version, coordinate out of range, no data → `ParcelTileNotFound`;
  zoom below `minZoom` or above `maxZoom` → empty.
- **Step 4, `tests/Unit/Handler/Http/`:** router matches a placeholder route and passes the
  parameters; a placeholder does not match `/` or `.`; literal suffix `.pbf` is required; 405
  for a placeholder route with another method; `Request::fromServer` reads headers lower-case;
  `header()` is case-insensitive with a default; `Response::noContent`, `withHeader`.
- **Step 5, `GetMapConfigurationUseCaseTest`:** maps the tileset into `ParcelLayerOutput`
  (version, zooms, bounds with distinct values so swaps fail, attribution); `parcels` is null
  when the repository throws `ParcelDataNotImported`.

### Integration

`tests/Integration/` mirrors `src/Infrastructure/`; real files and binaries; temporary
directories per test; no network except `127.0.0.1`.

- **Step 3, `CoverageRepositoryUsingCsv`:** reads `config/coverage.csv` (5 areas, municipality
  572659 once); rejects a missing file, a wrong header, a row with 2 fields, a non-numeric code, a
  duplicate code, an empty name (temporary files).
- **Step 3, `RuianExportDownloaderUsingHttp`** (local `php -S 127.0.0.1:<free port>` with the
  router script): downloads into the cache with the expected file name; a second call with the
  server stopped returns the cached file; 404 → `ExportNotPublished`; 500 and HTML body →
  `SourceDownloadFailed`; no `.part` file remains after a failure.
- **Step 3, `ParcelConverterUsingOgr2ogr`** (fixture zip): 659541 → 22 features, every id in the
  fixture list, properties exactly `id` and `landType`, coordinates within lon 15.3–15.5 and
  lat 50.3–50.5; 740225 → 3; 771775 → `CadastralAreaNotInExport` and no file left; a corrupt zip
  → `ConversionFailed`.
- **Step 3, `TilesetBuilderUsingTippecanoe`** (`geojson/659541.geojsonl`): produces an MBTiles
  with metadata `minzoom` 9, `maxzoom` 16, `format` pbf, vector layer `parcels`;
  `tippecanoe-decode <file> 14 8890 5525` contains `"id": 1753295604` and `landType`; an
  unreadable input → `TileBuildFailed`.
- **Step 3, `TilesetPublisherUsingMbtiles`:** publishing a copy of an MBTiles writes both metadata
  rows and moves it to the target; a second publish replaces the target; a non-SQLite input →
  `TilesetPublishFailed`.
- **Step 4, `ParcelTilesetRepositoryUsingMbtiles`** (fixture tileset): `current()` returns the
  fixture version, export date 2026-10-31, the fixture bounds, zooms 9 and 16; `tile(12/2222/1381)`
  is gzip; `tile(14/8891/5525)` is empty; a missing file → `ParcelDataNotImported`; an MBTiles
  without `parcel_map_version` → `ParcelTilesetUnreadable`.

### API (HTTP and CLI)

- **Step 2 (CLI):** `bin/console` → exit 0, stdout starts with `Available commands:`;
  `bin/console nope` → exit 2, stderr `Unknown command "nope".`.
- **Step 3 (CLI, `parcels:import`):** subprocess with `PARCEL_MAP_DATA_DIR=<temp>`,
  `PARCEL_MAP_COVERAGE_FILE=tests/Fixtures/coverage/fixture.csv`,
  `RUIAN_EXPORT_BASE_URL=http://127.0.0.1:<port>/` (router script):
  - `--export-date=2026-10-31` → exit 0; stdout has `Imported the RÚIAN export of 2026-10-31.`,
    `659541 Jičín: 22 parcels`, `740225 Robousy: 3 parcels`,
    `Published tileset <14 digits> with 25 parcels.`; `<temp>/tiles/parcels.mbtiles` exists with
    `parcel_map_export_date` 2026-10-31; `<temp>/cache/ruian/20261031_OB_572659_UKSH.xml.zip`
    exists;
  - second run with the server stopped → exit 0 (cache);
  - `--export-date=2026-10-30` → exit 2, stderr starts with `Invalid --export-date:`;
  - `--export-date=2026-09-30` (not served) → exit 1, stderr `Import failed: The RÚIAN export of
    2026-09-30 for municipality 572659 is not published.`;
  - `--bogus` → exit 2, `Unknown option "--bogus".`;
  - `bin/console` now lists `parcels:import`.
- **Step 4 (HTTP, fixture data):**
  - `GET /tiles/parcels/<FIXTURE_TILESET_VERSION>/12/2222/1381.pbf` with
    `Accept-Encoding: gzip` → 200, `Content-Type: application/vnd.mapbox-vector-tile`,
    `Content-Encoding: gzip`, `Cache-Control: public, max-age=31536000, immutable`,
    `Vary: Accept-Encoding`, body starts with `1f8b`;
  - same without `Accept-Encoding` → 200, no `Content-Encoding`, body does not start with `1f8b`
    and is non-empty;
  - `…/14/8891/5525.pbf` → 204, empty body, the same `Cache-Control`;
  - another version, `…/12/4096/0.pbf`, `…/12/x/1381.pbf`, `…/12/2222/1381.png` → 404
    `{"error":"not_found"}`;
  - `POST` to a tile URL → 405 with `Allow: GET`.
  - The test HTTP client gains optional request headers (curl must not decode bodies itself).
- **Step 5 (HTTP):** `GET /api/map-config` → `Cache-Control: no-cache` and the decoded body equals
  the 001 JSON plus `parcels` with the fixture version template, zooms 9/16, fixture bounds,
  attribution `ČÚZK, 2026` and the conditions URL.

### Frontend (unit and E2E)

**Vitest:**

- Step 5, `mapConfiguration.test.ts`: accepts `parcels: null` and a valid parcels object; rejects
  a missing `parcels` key, a relative template without `/`, a template without `{z}`,
  a non-finite `minZoom`, a bounds value that is a string, an attribution URL that is not a string.
- Step 5, `landTypes.test.ts`: every listed code maps to its colour, 7 and 8 share a colour, the
  expression is a `match` on `landType` with the fallback last.
- Step 5, `mapOptions.test.ts`: with parcels → source `parcels` with
  `tiles: ['http://example.test/tiles/parcels/v1/{z}/{x}/{y}.pbf']` (origin + template, braces not
  encoded), minzoom, maxzoom, bounds order `[west, south, east, north]`, escaped attribution;
  layers in order `base-map`, `parcels-fill`, `parcels-outline`; the outline `minzoom` is 14; the
  fill opacity is 0.45; with `parcels: null` → only the base map source and layer.

**Playwright** (offline-tiles fixture unchanged: app-origin requests continue and are served from
the fixture tileset):

- Step 5, `parcels.spec.ts`:
  - opening `/` requests `/tiles/parcels/<version>/12/2222/1381.pbf` and receives 200 with
    `application/vnd.mapbox-vector-tile`;
  - clicking "Zoom in" twice requests `…/14/8890/5525.pbf`;
  - the attribution link `ČÚZK, 2026` with the conditions URL is visible next to the OSM credit;
  - with `/api/map-config` answered from the real response but `parcels` set to `null`
    (`route.fetch()` + modified JSON), the map canvas is visible and no `/tiles/parcels/` request
    is made;
  - no console or page errors (fixture assertions).
- Step 6, `no-parcel-data.spec.ts`: with `parcels: null`, the status notice
  `Parcel data has not been imported yet. Run make data, then reload this page.` is visible, OSM
  tile `13/4445/2762` is still requested, and no parcel tile is requested.

## Acceptance criteria

- [ ] Step 1: CLAUDE.md contains the naming rules; no class named `GetMapConfiguration`,
      `MapSettings`, `StaticMapSettings` or `InMemoryMapSettings` remains; all suites pass
      unchanged in behaviour.
- [ ] `make data` on a clean checkout downloads one file (3.7 MB) and publishes
      `data/tiles/parcels.mbtiles` with 16,972 parcels from 5 areas; a second `make data` makes no
      network request and finishes in under 30 s.
- [ ] `docker compose up` without data serves the OSM map with the notice; after `make data` and a
      reload, parcels appear over Jičín coloured by land type, outlines appear from zoom 14, and
      "ČÚZK, 2026" is visible next to the OSM credit.
- [ ] Tile responses carry the headers in the Handlers table; a reloaded page fetches no parcel
      tile again (browser cache, checked in DevTools); after a re-import the tile URLs change.
- [ ] Performance (manual, recorded in the implementation log):
  - [ ] every tile in the real tileset is at most 200 KB (query the MBTiles);
  - [ ] 100 sequential tile requests on localhost have a median under 20 ms;
  - [ ] panning and zooming between z12 and z16 over Jičín shows no long tasks over 50 ms after
        the tiles have loaded (Chrome performance panel);
  - [ ] frontend code adds no `move`/`render` listeners.
- [ ] Tests never contact ČÚZK or OSM (local servers and fixtures only).
- [ ] `make check` passes at every commit (`git rebase --exec "make check" main`); CI groups are
      unchanged; `make check` does not restart a running `make up` app.
- [ ] README and CLAUDE.md are updated as listed below.

## Documentation updates per commit

- **Step 1:** CLAUDE.md "Code rules" naming sub-list; README Architecture text if it names a
  renamed class.
- **Step 2:** CLAUDE.md Commands: `docker compose run --rm app bin/console` (lists commands).
- **Step 3:**
  - CLAUDE.md Commands: `make data` (`PHP bin/console parcels:import`), `make test-integration`,
    `make test` includes it. Project tree line for `data/`: "Downloaded and generated data.
    Git-ignored. Test fixtures live in `tests/Fixtures/`." Add `config/` (committed
    configuration, e.g. `coverage.csv`).
  - CLAUDE.md Glossary additions:

    | Czech (source data) | Code name | Notes |
    |---|---|---|
    | kód obce | `MunicipalityCode` | 6 digits. |
    | kód katastrálního území | `CadastralAreaCode` | 6 digits. |
    | měsíční stavový soubor obce VFR (`OB_UKSH`) | RÚIAN export (`RuianExportDownloader`, `DownloadedExport`) | Monthly per-municipality file with parcels and original boundaries. |
    | datum souboru VFR | `ExportDate` | Last day of a month; the file is published the next day. |
    | — | `Coverage` | The cadastral areas the map covers (`config/coverage.csv`). |
    | — | `TilesetVersion` | Identifies one published parcel tileset; part of the tile URLs. |

  - Glossary note changes: `OwnershipSheet` → "Neither the LV number nor ownership is in the open
    data (RÚIAN, SHP, INSPIRE, open VFK)."; `SimplifiedRecordParcel` → "Not in RÚIAN or any open
    source; no own geometry."; `ParcelKind` → "`DruhCislovaniKod` 1 = building, 2 = land."
  - README: status line; new "Data" section (source and URL pattern, CC BY 4.0 and the
    "ČÚZK, year" credit, `make data`, what is cached where, sizes: 3.7 MB download, 3.5 MB
    tileset); Running (run `make data` once); Testing table rows `make data`,
    `make test-integration`; Decisions: VFR as source, GDAL/tippecanoe in the app image **with
    the note that separate tool containers would be preferred (lean runtime image, independent
    tool upgrades, isolation) and why one image is enough now (single consumer, process adapters
    need the binaries in the same container, +287 MB)**; With more time: separate tool
    containers, import progress output, pinned coordinate transformation.
- **Step 4:** Glossary: `TileCoordinate`, `VectorTile`, `Bounds`, `ParcelTileset`. README: tile
  endpoint, caching and workers decisions, tests use the `parcel-map-test` compose project.
  CLAUDE.md Commands: `make up-app` / `test-api` / `test-e2e` run in the test project with
  fixture data; `make down` stops both.
- **Step 5:** README status: parcels are shown; map-config mentions `parcels`.
- **Step 6:** README Running: what the page shows before `make data`.

## Risks and assumptions

- **Monthly data:** the map can lag the cadastre by up to a month (daily change files are out of
  scope).
- **Packaged tool versions** (GDAL 3.10.3, tippecanoe 2.53.0) are older than upstream; the
  options used exist in 2.53.0 (verified). A Debian update could change tile output; the fixture
  tileset is committed, so tests do not depend on rebuilding it.
- **Unpinned transformation:** a future PROJ with grids could choose another S-JTSK → WGS84
  transformation (sub-metre differences).
- **Image size and CI time:** +287 MB and about 35 s per PHP image build in CI.
- **District scale (task 003):** about 1 min download (first run), about 1 min conversion (each
  municipality file is read once per area), 1.5 min tile build, 56.5 MB tileset; the import
  prints only at the end.
- **`!reset`** is verified on Compose 5.8 locally only; the runner has 2.38.2 (the tag exists
  since 2.24).
- **PHP 8.6 RC:** keep to syntax PHP-CS-Fixer supports (task 001 risk).
- **Assumption:** ČÚZK keeps the VFR URL pattern and file naming; a change makes `make data`
  fail with `SourceDownloadFailed` or `ExportNotPublished`, never silently.

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: naming rules

- Test first: renamed `GetMapConfigurationUseCaseTest` and the fake `MapSettingsRepositoryUsingMemory`
  to the new names; the unit suite failed with `Interface "ParcelMap\Domain\Map\MapSettingsRepository"
  not found` (red). Then renamed the production classes per the Rename list; green.
- CLAUDE.md "Code rules" gained the Naming sub-list. README names none of the renamed classes.
- Existing API and E2E tests are unchanged.

### Step 2: CLI walking skeleton

- Red runs: unit `ConsoleApplicationTest` failed with `Interface "ParcelMap\Command\Console\ConsoleOutput"
  not found`; API `ConsoleTest` failed with exit code 1 instead of 0 / 2 (no `bin/console` yet).
- The API test uses a small `ConsoleRunner` (proc_open of `PHP_BINARY bin/console`).
- Unknown command: the `Unknown command` line and the command list both go to stderr.
- phpstan and php-cs-fixer both analyse the extensionless `bin/console` fine. phpstan does not know
  `$argv` in a script, so `bin/console` reads `$_SERVER['argv']` with a `@var list<string>` annotation.

### Step 3: import

- Fixture zip built from the research download with a one-off script (25 parcels, 5.7 KB, as predicted). The
  geojsonl fixture was produced by `ParcelConverterUsingOgr2ogr` itself (22 lines, 20,696 bytes).
- Red runs, in order: API `ImportParcelsCommandTest` (6 tests) failed with `Unknown command "parcels:import"`
  / exit 2 instead of the expected codes; unit domain tests failed with class-not-found; unit
  `ImportParcelsUseCaseTest` failed with `Interface ...RuianExportDownloader not found`; integration
  tests failed with `Class ... not found`; after the infrastructure existed the two builder tests failed
  with `tippecanoe failed: .../659541.geojsonl: No such file or directory` until the fixture was generated.
- `ogr2ogr` ran fine as uid 1000 without a `HOME`; no extra environment is passed to it.
- Deviation (small): `ImportParcelsUseCase` catches only `CoverageUnavailable`, not also `InvalidCoverage` as the
  task text says. The repository port only throws `CoverageUnavailable` (the CSV adapter wraps domain
  exceptions), and PHPStan rejects the dead catch.
- `Coverage::areas()` and `municipalities()` are documented `non-empty-list` so the use case can pass them
  to `TilesetBuilder::build(non-empty-list)` without a runtime check. The use case downloads the first
  municipality separately (the only one that may fall back) and the rest with the resolved date.
- Test helpers beyond the plan: `tests/Support/{LocalHttpServer,TemporaryDirectory,MbtilesMetadata}`,
  `tests/Api/ConsoleRunner`, `tests/Integration/Infrastructure/Process/ProcessRunnerTest` and
  `Clock/ClockUsingSystemTimeTest`.
- `composer update --lock` refreshed `composer.lock` for the new `require` entries.

### Step 4: tile endpoint

- Fixture tileset produced exactly as the task describes (version `20261120111929`, bounds and tiles as predicted,
  `14/8891/5525` empty); constants in `tests/Support/FixtureTileset.php`.
- Red runs: `make test-api` in the new test project failed 4 of 21 (`ParcelTileTest`: 404 instead of 200 / 200 / 204 /
  405); the unit tests (domain, use case, Router/Request/Response) failed with class-not-found and missing
  members; the integration `ParcelTilesetRepositoryUsingMbtilesTest` failed with class-not-found. The
  `ParcelTileRouteTest` was added after the handler (it exercises plumbing already covered by the red API run).
- `Route` compiles its path to a regex (`matches()` and `parametersOf()`, instead of one method returning `null`).
- `ParcelTilesetRepositoryUsingMbtiles` opens a connection per call (read-only via `Pdo\Sqlite`); a missing file
  raises `ParcelDataNotImported` and does not create the file.
- The test project works: `make test-api` starts `parcel-map-test` (no published port) and the developer's app
  is not touched. Running `docker compose down` (without `-p`) is what stopped the dev app during this work.
- `make down` stops both projects; `compose.yaml` names the app image `parcel-map-app` so the test project reuses it.

### Step 5: map layer

- Red runs: API `MapConfigurationTest` failed with `null is identical to 'no-cache'` (header missing); unit
  `GetMapConfigurationUseCaseTest` failed with `Undefined property ...MapConfigurationOutput::$parcels`; Vitest
  failed with `Cannot find module '../src/landTypes'` and "promise resolved instead of rejecting" for the missing
  `parcels` key; E2E `parcels.spec.ts` failed 3 of 4 with `waitForResponse` timeouts (and the credit link not found).
  The fourth E2E test (`parcels: null` shows only the base map) passes before the change by nature.
- Test bug found at green: the first version of the zoom-in E2E clicked "Zoom in" twice quickly; a click during the
  running animation does not add a full level, so tile `14/8890/5525` was never requested. The test now waits for
  the zoom-13 tile before the second click.
- `landTypeFillColour()` builds the `match` expression from `LAND_TYPE_COLOURS` and casts through `unknown`
  because TypeScript cannot type a spread inside a `match` tuple.
- The map-config Handler adds `Cache-Control: no-cache` and the `parcels` member as specified.

### Step 6: no-data notice

- Red run: `no-parcel-data.spec.ts` failed with `element(s) not found` for the status notice (the second test, no
  notice when data exists, passes by nature before the change).
- The notice has `pointer-events: none` so it never blocks the map or the zoom buttons.

### Finish

- `make data` against real ČÚZK: first run 9.8 s wall time (one 3.8 MB download, convert, tile build; image already
  built), second run 8.1 s from the cache. 16,972 parcels, `data/tiles/parcels.mbtiles` 3.5 MB.
- Performance, measured on the real tileset (all 5 areas) through the running app: the largest of the 334 tiles is
  199,436 bytes (gzip, within the 200 KB budget); 100 sequential requests of `14/8890/5525` (127,842 bytes) on
  localhost: median 2.5 ms, maximum 17 ms. The frontend adds no `move`/`render` listeners. NOT done (needs a
  person with Chrome DevTools): the performance-panel check for long tasks over 50 ms while panning and zooming
  between z12 and z16, and the visual check of the parcels over Jičín in a real browser with real data.
- `make check` takes about 26 s (warm).
