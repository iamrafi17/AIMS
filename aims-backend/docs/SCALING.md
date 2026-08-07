# Running AIMS for 1,000+ users

AIMS should use MySQL or MariaDB in production. SQLite is appropriate only for local development and tests because its single-writer design becomes a bottleneck with concurrent attendance updates.

## Production environment

Set the following in the production `.env` (use secure, non-default credentials):

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

Run the migration that adds the high-volume indexes:

```bash
php artisan migrate --force
```

For each application server, run PHP-FPM behind Nginx or Apache. Run at least one separate queue worker for slow work such as bulk notifications, emails, exports, and file processing:

```bash
php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
```

Finally, cache Laravel's production configuration during deployment:

```bash
php artisan optimize
```

Do not use `php artisan serve` as the production web server. Before launch, load-test the real deployment with representative attendance and dashboard traffic; 1,000 registered accounts is modest, but the required server capacity depends on how many are active at once and on the size of uploaded files.
