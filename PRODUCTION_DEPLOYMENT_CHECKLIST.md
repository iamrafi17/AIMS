# AIMS Production Deployment and Load Readiness Checklist

## Kasalukuyang setup

- React/Vite development server ang frontend sa port `5173`.
- Laravel development server (`php artisan serve`) ang backend sa port `8000`.
- Ang PHP development server sa Windows ay single-threaded at hindi ginawa para sa production traffic.
- Kapag may mabagal na request tulad ng PDF generation, malaking upload, o report query, maaaring maghintay at mag-timeout ang ibang requests.
- Ang local-network link o temporary tunnel ay para lamang sa testing at demonstration.
- Hindi garantiya ng tunnel na kakayanin ng local server ang maraming sabay-sabay na users.

## Mga kailangang ihanda bago production deployment

1. **Production web server**
   - I-build ang React frontend gamit ang production build.
   - I-serve ang frontend gamit ang Nginx, Apache, o CDN.
   - Patakbuhin ang Laravel gamit ang Nginx/Apache at PHP-FPM o katumbas na multi-worker setup.

2. **Background queues**
   - Ilipat sa queue workers ang PDF at report generation.
   - I-queue ang notifications, email, at mabibigat na file-processing tasks.
   - Gumamit ng process monitor para awtomatikong i-restart ang queue workers kapag nag-fail.

3. **Cache at rate limiting**
   - Gumamit ng Redis o katumbas na shared cache sa production.
   - I-store sa shared cache ang rate-limit counters para consistent sa lahat ng server workers.
   - Panatilihin ang login at password-recovery rate limits.
   - Magdagdag ng makatwirang limits sa expensive endpoints tulad ng report generation at file uploads.

4. **Database readiness**
   - Gumamit ng production database server at hiwalay na database credentials.
   - Siguraduhing may indexes ang madalas gamiting filters, foreign keys, dates, statuses, at program/student identifiers.
   - I-configure ang database connection at query timeouts.
   - I-review ang mabibigat na queries gamit ang realistic production-sized data.
   - Gumamit ng transactions at locking sa operations na maaaring sabay-sabay baguhin ng maraming users.

5. **File storage**
   - Panatilihing private ang requirements, MOAs, travel photos, at report templates.
   - Gumamit ng durable storage na may backups; object storage kung kailangan ng multiple application servers.
   - Maglagay ng malware scanning para sa uploaded documents.
   - Magtakda ng file-size, MIME-type, at processing-time limits.

6. **Availability at monitoring**
   - Gumamit ng HTTPS at production domain.
   - Mag-configure ng health checks at automatic service restart.
   - I-monitor ang response time, error rate, CPU, memory, disk space, database connections, at queue backlog.
   - Magkaroon ng centralized application logs at alerts.
   - Maghanda ng regular database at file backups at subukan ang restoration procedure.

7. **Security bago ilabas sa internet**
   - Ayusin muna ang mga findings sa `SECURITY_AUDIT.md`.
   - Huwag gumamit ng tunay na student data sa public temporary tunnel.
   - Gumamit ng least-privilege production credentials at secret management.
   - Huwag ilagay sa repository ang `.env`, API keys, database password, o private keys.
   - I-disable ang debug mode at alisin ang development-only services sa production.

## Load-testing checklist

- Gumamit ng staging environment na kapareho ng production configuration.
- Gumamit ng test accounts at synthetic data; huwag totoong personal information.
- Subukan nang paunti-unti ang `10`, `50`, at `100` simultaneous users.
- Isama sa test ang login, dashboard loading, attendance, requirements, travel monitoring, uploads, at report generation.
- Sukatin ang average at slowest response times, failed requests, timeouts, CPU, memory, database load, at queue backlog.
- Subukan ang sabay-sabay na update sa parehong attendance/travel record para makita ang race conditions.
- Subukan ang recovery pagkatapos ng worker, database, o queue interruption.
- Itakda ang acceptable response-time at error-rate targets bago ang test.
- Huwag mag-load test laban sa live production data nang walang hiwalay na pahintulot at safety controls.

## Release decision

Hindi dapat gawing permanent production server ang Vite development server, `php artisan serve`, o temporary tunnel. Ituturing lamang na production-ready ang AIMS kapag:

- naayos ang critical/high security findings;
- pasado ang automated functional at security tests;
- pasado ang load test sa inaasahang sabay-sabay na users;
- maayos ang monitoring, backups, queues, at automatic recovery; at
- nasubukan ang buong deployment sa staging environment.

## References

- [PHP built-in web server](https://www.php.net/features.commandline.webserver.php)
- [Laravel queues](https://laravel.com/framework/docs/12.x/queues)
