# PSGC lookup snapshot

These compact JSON files contain PSGC codes, names, and parent relationships used by the applicant address picker and server validation. They are built from the MIT-licensed `fish-and-bear/psgc` project (`psgc/data/core`, Q1 2026 data): https://github.com/fish-and-bear/psgc

The project republishes the PSA PSGC masterlist and PHLPost ZIP codes and is not affiliated with PSA or PHLPost.

| File | Contents |
| --- | --- |
| `regions.json`, `provinces.json`, `cities.json` | PSGC codes, names, and parents. Provinces include pseudo-provinces so independent cities can be represented; the picker lists those cities directly under their region. |
| `barangays/{region_code}.json` | `{city_code: [[barangay_code, name], …]}`, split per region so a request reads one small file. Manila's barangays belong to its 14 districts in PSGC; they are listed under the City of Manila with the district in the name. |
| `postal.json` | `{city_code: [{code, area}, …]}`. ZIP codes matched to PSGC towns by name, with conservative matching for spelling variants. About 88% of cities and municipalities have a code; applicants type the rest, and every code can be corrected. |
| `meta.json` | Data version (used to version API URLs), source, build time, and record counts. |

Coordinates, population, and other unrelated metadata are intentionally excluded.

## Refreshing

Do not edit these files by hand. Rebuild them with:

```sh
php artisan psgc:sync --bundled            # developers: rewrite this folder, then commit it
php artisan psgc:sync                      # servers: install a newer copy in storage/app/psgc
php artisan psgc:sync --dry-run            # download and validate only
php artisan psgc:sync --source=/path/dir   # build from local copies of the five source files
```

The command replaces data only after every source file downloads, parses, and passes the integrity and minimum-size checks. Otherwise the current data stays in use. At runtime the app prefers a complete `storage/app/psgc` copy and falls back to this bundled snapshot. It never calls an external service while serving a page.
