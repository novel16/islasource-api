# IslaSource Seafoods API

Laravel 13 API with admin-only Sanctum bearer authentication. The Next.js admin
uses these endpoints for login, profile verification and logout. Business-data
integration and the public storefront backend remain separate phases.

## Local setup

Keep your configured `.env` database settings. Apply the additive migrations:

```powershell
php artisan migrate
php artisan admin:create
php artisan serve --host=127.0.0.1 --port=8000
```

`admin:create` asks for a name, email and hidden password/confirmation. Optional
`--name` and `--email` arguments are supported. Passwords require at least 12
characters and, with bcrypt, at most 72 bytes. There is no default admin password,
public registration endpoint, or password argument exposed to shell history.
Existing accounts are never overwritten or elevated by this command; existing
users receive `is_admin=false` when the migration is applied.

Optional settings (defaults already work locally):

```dotenv
ADMIN_APP_URL=http://localhost:3001
SANCTUM_EXPIRATION=720
```

`ADMIN_APP_URL` is the exact allowed browser origin, including its scheme and port,
without a trailing slash. CORS does not allow credential cookies. Token lifetime
is measured in minutes; the default is 12 hours. After changing configuration,
run `php artisan config:clear` locally. In production, use HTTPS, disable debug
output, and run the Laravel scheduler so expired tokens are pruned daily.

## Authentication contract

Base path: `/api/v1/admin/auth`. Send `Accept: application/json` and JSON request
bodies with `Content-Type: application/json`. API errors remain JSON even if the
Accept header is omitted.

| Method | Path | Behavior |
| --- | --- | --- |
| POST | `/login` | Validate credentials and issue an admin token |
| GET | `/me` | Return the authenticated admin's safe profile |
| POST | `/logout` | Revoke the current token; return 204 |
| POST | `/logout-all` | Revoke all tokens belonging to this admin; return 204 |
| GET | `/tokens` | Paginated issued tokens belonging to this admin |
| DELETE | `/tokens/{token}` | Revoke one owned token; return 204 |

Only `/login` is public. All other endpoints require:

```http
Authorization: Bearer <access_token>
```

Login body (replace with the account you created):

```json
{
  "email": "you@example.com",
  "password": "<your-admin-password>",
  "device_name": "Admin browser"
}
```

`device_name` is optional and defaults to `admin-web`. A successful login returns:

```json
{
  "data": {
    "user": { "id": 1, "name": "Your name", "email": "you@example.com" },
    "access_token": "<returned-on-login-only>",
    "token_type": "Bearer",
    "expires_at": "2026-11-13T12:00:00.000000Z"
  }
}
```

The plaintext token is returned only on login. Only its hash is stored. Token
listing returns safe metadata, including expiry, last use and `is_current`, with
15 records per page and Laravel `links`/`meta` pagination. It never returns token
hashes, plaintext tokens or another admin's records. Issued tokens remain listed
until revoked or pruned; expired tokens cannot authenticate.

Missing, invalid, expired or revoked tokens return 401. Disabled/non-admin users
or tokens without the `admin` ability receive 403 on protected routes. Rejected
login credentials return the same generic 422 error regardless of account
existence or admin status. Unknown/unowned token IDs return 404. Rate limits return
429 with retry headers: 5 login requests per normalized email+IP per minute,
20 per IP per minute, and 120 protected requests per admin per minute.

Auth responses use `Cache-Control: no-store`. The API has no stateful Sanctum
SPA domains, session guard fallback, CSRF-cookie bootstrap route, or refresh token
endpoint. Expiry requires another login. The Next.js admin sends the bearer header
and stores only the token and expiry in tab-scoped sessionStorage; stored grants
are verified before restored workspace access.

## Code responsibilities

- `app/Http/Requests/Api/V1/Admin/Auth/LoginRequest.php`: input validation and email normalization.
- `app/Http/Controllers/Api/V1/Admin/Auth/`: HTTP coordination only.
- `app/Services/Admin/`: credential checks, token lifecycle and admin provisioning.
- `app/Http/Resources/Api/V1/Admin/Auth/`: explicit response fields and `TokenCollection`.
- `app/Http/Middleware/`: active admin/ability enforcement and auth response cache control.
- `app/Policies/PersonalAccessTokenPolicy.php`: token ownership authorization.

## Verification

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer audit
```

Tests use real bearer headers and an isolated in-memory SQLite database forced by
`phpunit.xml`; they do not reset the database configured in your local `.env`.
