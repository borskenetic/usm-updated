# Production Data Cutover

The production importer is a blue-green database copy. It reads a separate legacy MySQL schema and writes to the database configured by the selected Laravel connection. The schemas must be on the same MySQL server and must have different names.

## Prepare

1. Take and verify a backup of the legacy database and uploaded files.
2. Create a new target database; do not point production traffic at it.
3. Configure a Laravel MySQL connection whose database is the new target.
4. Run `php artisan migrate --force` against the target. Do not run demo or account seeders.
5. Restore the legacy SQL dump into a separate, read-only schema on the same server.

The normal rehearsal command only accepts an empty migrated target:

```bash
php artisan usm:import-dump --source=legacy_schema --connection=mysql
```

To deliberately erase managed data in a rehearsal target and repeat the import, both destructive flags are required:

```bash
php artisan usm:import-dump --source=legacy_schema --connection=mysql --fresh --force
```

The importer fails non-zero for missing required tables, unsafe identifiers, a source/target collision, unknown user roles, copy errors, count mismatches, invalid Library visit patron references, or foreign-key orphans. Optional version-specific source tables are reported when absent. Data changes are transactional and foreign-key checks are restored even after failure.

Legacy `students` and `employees` are copied with preserved IDs into both patron domains. Legacy `attendance_logs` are imported only into `library_attendance_logs`; the school `attendance_logs` table remains empty. Legacy `admin` and `staff` user roles become authoritative Spatie `library_admin` and `library_staff` assignments. Existing domain-specific roles are retained, and unknown roles stop the import for explicit review.

## Files and traffic

Database file records and path values are copied, but uploaded binaries are not transferred by SQL. Copy and verify the legacy storage/public upload directories separately before switching traffic. Keep the legacy application and schema unchanged until database counts, sampled workflows, uploaded files, and mobile API authentication have been verified against the green target.

## Initial administrator

Production migrations create the `developer` role but no hardcoded user. After a successful import, create the first super administrator with either a hidden password prompt:

```bash
php artisan system:bootstrap-super-admin owner@example.com
```

or a generated 32-character password shown once:

```bash
php artisan system:bootstrap-super-admin owner@example.com --generate
```

Promoting an existing imported user resets that user's password and therefore requires `--promote`. The command refuses to run after any `super_admin` assignment exists.
