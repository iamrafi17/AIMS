# AIMS Security Audit Findings

## Confirmed protections

- Lahat ng 24 `/api/admin/*` routes ay may `auth:sanctum`, active-account checking, at `role:admin` sa [api.php (line 186)](/C:/xampp/htdocs/AIMS/aims-backend/routes/api.php:186).
- Ang isolated student-account request sa admin dashboard ay nag-return ng **403 Unauthorized**.
- Ang student requirement downloads ay hinahanap gamit ang sariling `student_id`.
- Ang coordinator/program-head records ay program-scoped sa [ProgramAccess.php (line 43)](/C:/xampp/htdocs/AIMS/aims-backend/app/Support/ProgramAccess.php:43).
- Ang travel records ay kailangang pagmamay-ari at participant mismo ang student.
- Eloquent bindings ang ginagamit sa searches; ang raw SQL fragments ay fixed strings lamang.
- Walang `dangerouslySetInnerHTML`, `eval`, o direct `innerHTML` assignment.
- Ang generated report values ay dumadaan sa `htmlspecialchars()`, at sandboxed ang preview iframe.
- Private storage ang requirements, MOAs, travel photos, at report templates.
- May MIME/type at size validation; may ZIP, macro, embedded-object, at decompression-size inspection ang DOCX templates.

## Mga kailangang ayusin

### 1. High: vulnerable backend dependency

`league/commonmark 2.9.1` ang naka-lock sa [composer.lock (line 1688)](/C:/xampp/htdocs/AIMS/aims-backend/composer.lock:1688). Nakakita ang `composer audit` ng high-severity DoS advisory `GHSA-8rr7-cvq3-gmfh`. Wala akong nakitang direct paggamit nito sa AIMS code, kaya hindi confirmed na reachable, pero kailangan pa ring i-update sa `>=2.10.0`.

### 2. Medium: posibleng account enumeration sa password recovery

Sa [AuthController.php (line 572)](/C:/xampp/htdocs/AIMS/aims-backend/app/Http/Controllers/Api/AuthController.php:572), stable na tunay na questions ang ibinabalik sa valid account, random questions naman sa invalid account. Magkaiba rin ang verification error messages. Puwedeng mahulaan kung existing ang Student/User ID. Nakakatulong ang rate limit pero hindi nito tuluyang inaalis ang enumeration.

### 3. Medium: token nasa `localStorage` at walang CSP

Nasa [AuthContext.jsx (line 42)](/C:/xampp/htdocs/AIMS/aims-frontend/src/context/AuthContext.jsx:42) ang token storage. Kapag nagkaroon ng XSS sa hinaharap, puwedeng makuha ang token. Wala ring `Content-Security-Policy` header sa frontend o backend responses na sinuri.

### 4. Medium: walang malware scanning sa ordinary documents

MIME, extension, size, randomized filename, at private storage ay maayos. Pero ang requirements, announcements, at MOA files ay walang antivirus/CDR scanning. Hindi sila magiging web shell, pero posibleng malicious document kapag dinownload at binuksan ng staff.

### 5. Medium: spreadsheet formula injection

Direktang ipinapasa ang report data sa CSV/XLSX sa [ReportExportService.php (line 164)](/C:/xampp/htdocs/AIMS/aims-backend/app/Services/ReportExportService.php:164). Ang value na nagsisimula sa `=`, `+`, `-`, o `@` ay maaaring maging spreadsheet formula. Sa XLSX, awtomatikong ginagawang formula ng OpenSpout ang value na nagsisimula sa `=`.

### 6. Low: wildcard CORS

Confirmed sa localhost na tumatanggap ng arbitrary origin at `Authorization` header:

```http
Access-Control-Allow-Origin: *
```

Hindi nito bina-bypass ang role checks, pero mas ligtas na frontend domain lamang ang allowed sa production.

### 7. Low: maling unauthenticated response

Ang protected API request na walang `Accept: application/json` ay nagre-return ng **500** sa halip na **401**. Kapag JSON request, tama ang `401`. Reliability/error-handling issue ito, hindi authorization bypass.
