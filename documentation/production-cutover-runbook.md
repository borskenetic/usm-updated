# Production Cutover Runbook

This runbook promotes the separated-domain release to Hostinger without
modifying the legacy database. Replace every uppercase placeholder before
running a command.

## Release gates

Do not deploy unless all of these are true:

- The release branch is reviewed and based on `usm-updated/main`.
- `composer audit --locked --no-dev` and `npm audit --omit=dev` report no
  vulnerabilities.
- `public/build/manifest.json` is committed and matches the release.
- The full test suite, focused cutover tests, route cache, view cache, and
  production asset build pass.
- A rehearsal import from a recent production dump has matching row counts
  and no orphan report.
- Database and uploaded-file restore procedures have been tested.

## Hostinger layout

Prefer this layout:

```text
/home/USERNAME/domains/DOMAIN/releases/RELEASE_ID
/home/USERNAME/domains/DOMAIN/shared/.env
/home/USERNAME/domains/DOMAIN/shared/storage
/home/USERNAME/domains/DOMAIN/public_html -> releases/RELEASE_ID/public
```

If hPanel cannot point the domain to the release's `public` directory, deploy
the repository to `public_html` and retain the hardened root `.htaccess`.
Before cutover, verify that requests for `/.env`, `/composer.json`,
`/storage/logs/laravel.log`, and `/vendor/autoload.php` return 403 or 404.

## One-time production configuration

Keep the existing `APP_KEY`. Never run `php artisan key:generate` during this
cutover. Set at least:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=DATABASE_HOST
DB_PORT=3306
DB_DATABASE=NEW_GREEN_DATABASE
DB_USERNAME=DATABASE_USER
DB_PASSWORD=DATABASE_PASSWORD

BACKUP_NAME=pantas-db
BACKUP_ARCHIVE_PASSWORD=STRONG_UNIQUE_SECRET
BACKUP_NOTIFICATION_EMAIL=OPERATIONS_EMAIL
MYSQL_DUMP_BINARY_PATH=/PATH/CONTAINING/MYSQLDUMP
```

Configure SMTP and SMS credentials only in `.env`. Do not run
`php artisan db:seed` in production.

## Rehearsal

1. Restore a recent production dump to `LEGACY_REHEARSAL_DATABASE`.
2. Create an empty `GREEN_REHEARSAL_DATABASE`.
3. Point a release-candidate `.env` to the green database.
4. Run:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate --force
php artisan usm:import-dump --source=LEGACY_REHEARSAL_DATABASE
php artisan system:bootstrap-super-admin OWNER_EMAIL --generate
php artisan storage:publish
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
```

Record importer counts and sample records for users, patrons, books,
circulation, reservations, files, and Library visits. Confirm school
`attendance_logs` is empty and legacy visits are in
`library_attendance_logs`.

## Final backup

Put the old application into maintenance mode and pause its scheduler before
the final dump:

```bash
php artisan down --secret=CUTOVER_BYPASS
```

Record the current live commit and create all backups:

```bash
git rev-parse HEAD
mysqldump --single-transaction --routines --triggers \
  -h DATABASE_HOST -u DATABASE_USER -p LEGACY_DATABASE > legacy-final.sql
tar -czf uploads-final.tar.gz \
  storage/app \
  public/images/profile_pictures \
  public/images/student_signatures \
  public/images/signatures \
  public/images/formal_pictures \
  public/images/edits \
  public/videos
cp .env production.env.backup
```

Store a copy outside the web account. Test archive listing and SQL readability
before continuing.

## Cutover

1. Create the empty `NEW_GREEN_DATABASE`.
2. Deploy the reviewed release commit.
3. Preserve/link the production `.env` and uploaded-file directories.
4. Point `.env` to `NEW_GREEN_DATABASE`.
5. Run:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
test -f public/build/manifest.json
chmod -R ug+rwX storage bootstrap/cache
php artisan optimize:clear
php artisan migrate --force
php artisan usm:import-dump --source=LEGACY_DATABASE
php artisan system:bootstrap-super-admin OWNER_EMAIL --generate
php artisan storage:publish
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
```

Store the generated Super Admin password in the approved password manager and
change it after first login.

## Smoke tests

Use the maintenance bypass while testing:

- `/up`
- `/`, `/login`, `/register`, `/opac`, and `/attendance`
- Super Admin, Library Admin/Staff, and Attendance Admin/Staff authorization
- catalog search, checkout, return, reservation, and fine workflows
- Library and school attendance isolation
- patron registration and approval
- room reservation
- existing and new uploads, branding, and videos
- `/api/mobile/health`, authentication, profile, catalog, borrowing, and rooms

Check `storage/logs/laravel.log`, failed jobs, browser console/network errors,
and database errors. Remove maintenance mode only after every critical smoke
test passes:

```bash
php artisan up
```

Configure one hPanel cron entry to run every minute from the actual application
root:

```cron
* * * * * cd /home/USERNAME/domains/DOMAIN/APP_ROOT && /PATH/TO/php artisan schedule:run --no-interaction >> /dev/null 2>&1
```

Manually verify `schedule:run`, `backup:run --only-db`, `backup:list`, and a
restorable backup archive.

## Rollback

Treat old code, old `.env`, old database, uploads, and scheduler configuration
as one rollback unit. Do not use `migrate:rollback` for this release.

1. Enable maintenance mode and stop the new scheduler.
2. Capture any writes made after launch.
3. Switch the document root/release pointer to the tagged old release.
4. Restore the old `.env` so it points to the legacy database.
5. Restore uploads if the shared copies changed.
6. Run `php artisan optimize:clear` in the old release.
7. Restore the old scheduler and smoke-test before reopening.

If users wrote data to the green database, reconcile that delta before
rollback. Blindly switching databases would hide or lose those writes.
