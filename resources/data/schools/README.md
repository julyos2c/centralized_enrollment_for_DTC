# Bohol school reference list

`bohol-schools.csv` is the list of previous schools offered on the application form. The form lists schools in Bohol only (`SCHOOL_LOOKUP_PROVINCE_CODE`, default `0701200000`), grouped under the 48 Bohol localities from PSGC: 47 municipalities and Tagbilaran City. Applicants whose school is missing, or who studied outside Bohol, choose that option and type the school name and location.

## Source and verification

All 79 rows come from DepEd's official [List of Senior High Schools](https://www.deped.gov.ph/k-to-12/senior-high-school/list-of-senior-high-schools/), Bohol section, last updated by DepEd on May 8, 2018. Each row has its DepEd School ID and program offerings.

- **78 rows are `verified`**: the School ID and municipality match the DepEd record. Some municipality spellings were normalized to PSGC names; each change is recorded in `verification_note`: Garcia-Hernandez → Garcia Hernandez, North Carmen → Carmen, Sagbayan (Borja) → Sagbayan, Tagbilaran City (Capital) → City of Tagbilaran, and President Carlos P. Garcia (Pitogo) → President Carlos P. Garcia.
- **1 row is `needs_review`**: Blessed Trinity College – A.R. of Talibon (404282). DepEd lists its municipality as "Kapawan", which is not a Bohol municipality. It is offered under "Municipality not recorded" until staff confirm its location.
- **Excluded**: Artiaga's Learning Center Inc. (406292). DepEd filed it under Bohol, but its municipality is Cadiz City, which is in Negros Occidental.

**Known gap:** DepEd's list covers private schools, state and local universities, and colleges offering SHS. It does **not** include public (DepEd-run) national high schools or integrated schools. Eleven localities currently have no listed school. Add those schools when a verified source is available, such as the DepEd Bohol Division masterlist or an EMISD/FOI masterlist with School IDs. Add them through **Admissions → School List** or by importing a CSV.

## Importing more schools

```sh
php artisan schools:import path/to/file.csv            # add / fill gaps; nothing is deleted
php artisan schools:import --replace                    # bundled list becomes the offered list
```

CSV columns, matched by header name: `school_id`, `school_name`, `region`, `province`, `municipality`, `barangay`, `street_address`, `sector` (public/private), `program_offerings` (`STEM|ABM|…`), `verification_status` (`verified` / `needs_review` / `unverified`), `verification_note`.

- Rows are matched by DepEd School ID. Rows without one are matched by the same name in the same municipality, so re-importing never creates duplicates.
- Existing values and staff edits are never overwritten; only empty fields are filled.
- Without an explicit status, a row counts as `verified` only when it has a School ID and its municipality matched PSGC.
- `--replace` hides, but never deletes, imported schools missing from the file. Applications keep the school name and details they were submitted with.
