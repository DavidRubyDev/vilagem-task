# Configuration

## `coverage.csv`

The cadastral areas the map covers: the whole Jičín district (okres 3604), 240 cadastral areas of 111
municipalities. `make data` reads it to decide which municipality files to download and which areas to import.

Format (UTF-8, LF line endings, trailing newline): the header `code,name,municipality_code`, then one row per
cadastral area, sorted by area code, e.g. `659541,Jičín,572659`. A name is quoted only if it contains a comma,
a double quote or a line break.

It is generated from the ČÚZK RÚIAN state structure file and committed, so the import stays reproducible and every
change is a reviewable diff. Only cadastral areas with a digital map are listed (the others have no parcel
polygons). To regenerate it:

```bash
make coverage                                # latest published export, district 3604
docker compose run --rm --no-deps app bin/console coverage:generate --district=3604 --export-date=2026-10-31
```

`make data` never rewrites the file. Review the diff after regenerating and commit it.

The committed version comes from the RÚIAN export of 2026-10-31 for district 3604 (sha256
`bf5b1a9219041e9d97dabb170d0062f1bbd5c72c1cb78ab3c54082d84146b55c`).
