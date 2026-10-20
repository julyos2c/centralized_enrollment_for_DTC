# Approved academic configuration

`AcademicConfigurationSeeder` reads `academic-configuration.json` from this
directory. **The file must only contain values taken from approved
institutional records** (registrar-approved curriculum, official academic
calendar). If the file is absent, the seeder does nothing.

```json
{
  "approved_by": "Name and position of the approving official",
  "approved_on": "YYYY-MM-DD",
  "source": "Document the values were copied from (e.g. curriculum memo reference)",
  "term": { "school_year": "YYYY-YYYY", "semester": "1st | 2nd | Summer" },
  "programs": [
    {
      "code": "Existing or new program code",
      "name": "Official program name",
      "subjects": [
        { "subject_code": "…", "subject_name": "…", "units": 3, "year_level": 1, "semester": 1 }
      ]
    }
  ]
}
```

`term` and `programs` are both optional. Rules applied by the seeder:

- The whole file is validated first; any error aborts with nothing written.
- Changes are applied in a single transaction.
- Existing programs, subjects, and an already-configured term are never
  renamed, updated, or deleted. Differences are reported so they can be
  corrected deliberately through the admin pages.
- Re-running with the same file changes nothing.

Run it on its own after taking a database backup:

```sh
php artisan db:seed --class=AcademicConfigurationSeeder --force
```
