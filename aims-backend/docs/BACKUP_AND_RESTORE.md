# AIMS Backup and Restore

AIMS backups must keep the MySQL/MariaDB database and protected files in `storage/app` from the same backup window. The database stores the workflow state and file paths; restoring only one side produces incomplete historical records.

## Backup policy

- Run an encrypted database and protected-file backup at least daily and before every production migration.
- Store backups outside the application server, with access limited to authorized operators.
- Keep at least 30 daily copies and follow the institution's approved retention policy for older archives.
- Monitor scheduled jobs and investigate every failed or unexpectedly small backup.
- Never put `.env`, SQL dumps, or uploaded documents in source control or a public web directory.

## Create a backup

Put the application in maintenance mode for a fully consistent manual snapshot:

```powershell
php artisan down
mysqldump --single-transaction --routines --triggers --host=127.0.0.1 --user=aims_backup --password --result-file=aims-db.sql aims_db
Compress-Archive -LiteralPath storage\app -DestinationPath aims-private-files.zip
php artisan up
```

Use a dedicated database account with read and lock privileges required by `mysqldump`. Do not place its password directly in a scheduled command; use the database client's protected option file or the deployment secret store.

Record the application release, migration status, UTC backup time, database dump checksum, and file archive checksum beside each backup. Encrypt the artifacts before transferring them to backup storage.

## Restore procedure

Restore into an isolated database and storage directory first:

```powershell
mysql --host=127.0.0.1 --user=aims_restore --password aims_restore_test --execute="source C:/absolute/path/to/aims-db.sql"
Expand-Archive -LiteralPath aims-private-files.zip -DestinationPath restore-test
```

Configure a staging AIMS instance to use the restored database and copy the restored `storage/app` contents into that instance. Then run:

```powershell
php artisan migrate:status
php artisan optimize:clear
php artisan test
```

Verify authentication, attendance history, requirement downloads, MOA downloads, completed travel routes, and arrival-photo downloads using authorized test accounts. Confirm that unauthorized file requests are rejected.

## Restore drill

Perform and document a restore drill at least quarterly and before launch. The drill record must include backup identifiers, checksums, start/end time, restored row counts, file sample results, application test results, operator, reviewer, and any corrective action. A backup is not considered usable until a restore drill succeeds.

For an actual incident, keep the production site in maintenance mode, preserve the damaged database and files for investigation, restore the last verified matching pair, run pending migrations only after confirming the restored release version, complete the verification checklist, and then return the site to service.
