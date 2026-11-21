# IslaSource Seafoods API

Laravel 13 API with admin-only Sanctum bearer authentication and persisted product
management. The Next.js admin uses these endpoints for authentication and products.
Other business-data integration and the public storefront backend remain separate phases.

## Local setup

Keep your configured `.env` database settings. Apply the additive migrations:

```powershell
php artisan migrate
php artisan storage:link
php artisan admin:create
php artisan serve --host=127.0.0.1 --port=8000
```

`admin:create` asks for a name, email and hidden password/confirmation. Optional
`--name` and `--email` arguments are supported. Passwords require at least 12
characters and, with bcrypt, at most 72 bytes. There is no default admin password,
public registration endpoint, or password argument exposed to shell history.
Existing accounts are never overwritten or elevated by this command; existing
users receive `is_admin=false` when the migration is applied.

The product migrations create `products` and `product_slugs` without changing
existing users or business records. `storage:link` exposes uploaded product
images under `/storage/products/`; the four supplied `/images/*.jpg` assets are
served by the Next.js apps. No sample products are seeded into your database.

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

## Products contract

Base path: `/api/v1/admin`. Every endpoint below requires the same bearer token,
active admin role and `admin` ability, uses the 120-per-minute admin limiter,
and returns `Cache-Control: no-store`. Product policies authorize each action.

| Method | Path | Behavior |
| --- | --- | --- |
| GET | `/products` | Search/filter/paginate active products |
| POST | `/products` | Create a product; return 201 |
| GET | `/products/{id}` | Show an active product with previous slugs |
| PATCH | `/products/{id}` | Apply a validated partial edit |
| DELETE | `/products/{id}` | Soft archive; return 204 |
| POST | `/products/{id}/restore` | Restore an archived product |
| POST | `/product-images` | Upload multipart `image`; return 201 |

List query fields: `search` (name/slug/category, maximum 100 characters), exact
`category`/`status`, `archived=0` (default) or `archived=1` (archived only), positive
`page`, and `per_page` from 1 to 100 (default 15). Results use descending numeric
IDs, with Laravel `data`, `links` and `meta`. List rows omit `previous_slugs` to
avoid loading every product's history. Missing/archived active IDs and active IDs
on the restore route return 404. Invalid input returns Laravel field errors with 422.

Minimal create body:

```json
{
  "name": "Fresh squid",
  "category": "Fresh seafood",
  "unit": "kg",
  "status": "Draft",
  "description": "Seafood sourced from Bantayan Island on request.",
  "slug": "fresh-squid",
  "meta_title": "Fresh squid | IslaSource Seafoods",
  "meta_description": "Request a quotation for fresh squid."
}
```

All fields above are required on create. PATCH accepts any subset and preserves
omitted values. Unknown input keys, client IDs, timestamps and deletion flags are
ignored. Slugs are trimmed/lowercased, maximum 160 characters, and contain only
lowercase letters/numbers separated by single hyphens. Categories are `Dried
seafood`, `Fresh seafood`, `Frozen seafood`; units are `kg`, `pack`, `piece`;
statuses are `Draft`, `Published`, `Hidden`.

Limits: name 100, description 20000, optional specs 5000, meta title 70, meta
description 160, OG title 100, OG description 200, image alt 255, canonical URL 2048
(HTTP/HTTPS only), and availability note 500 characters. Optional metadata fields
are `specs`, `og_title`, `og_description`, `image`, `image_alt`, `og_image`,
`canonical_url`, `indexable`, `min_order_quantity`, and `availability_note`.
`indexable` defaults to true, `image` to `/images/squid.jpg`, and blank alt text to
the product name. The minimum quantity defaults to `1.000`, must be positive,
permits up to three decimals and at most `9999999.999`, and is returned as a
three-decimal string. The default availability note is:

> Sourced on request. Availability and final pricing are confirmed after your quote request.

Resources return the fields above, numeric `id`, ISO `created_at`, `updated_at`,
nullable ISO `deleted_at`, and `previous_slugs` on detail/create/update/restore.
Nullable text appears as an empty string. No prices, stock guarantees, supplier
costs or internal notes are exposed.

Current and historical slugs share one database-unique registry, retained through
archive and restore. Slug claims and product changes are transactional; a collision
returns a 422 `slug` error. Products may return to their own prior slugs. Histories
reference the product ID directly. Public redirects are not live in this phase.

Upload actual JPEG/PNG/WebP contents up to 5 MiB using multipart field `image`.
The result is `{"data":{"image":"/storage/products/<generated-name>.png"}}`.
Original filenames are discarded. Product `image` and `og_image` accept only
`/images/squid.jpg`, `/images/crab.jpg`, `/images/scallops.jpg`,
`/images/dried-fish.jpg`, or an existing generated raster upload on the public
disk. Arbitrary URLs, paths, traversal, SVG and nonexistent uploads are rejected.
Filesystem failures return an error. Uploads remain after archive or image changes
because other products/OG metadata may reference them; unused upload cleanup is deferred.

## Code responsibilities

- `app/Http/Requests/Api/V1/Admin/Auth/LoginRequest.php`: input validation and email normalization.
- `app/Http/Controllers/Api/V1/Admin/Auth/`: HTTP coordination only.
- `app/Services/Admin/`: credential checks, token lifecycle and admin provisioning.
- `app/Http/Resources/Api/V1/Admin/Auth/`: explicit response fields and `TokenCollection`.
- `app/Http/Middleware/`: active admin/ability enforcement and auth response cache control.
- `app/Policies/PersonalAccessTokenPolicy.php`: token ownership authorization.
- `app/Http/Requests/Api/V1/Admin/Products/`: list, write and upload validation/authorization.
- `app/Http/Controllers/Api/V1/Admin/Products/`: product HTTP coordination.
- `app/Services/Admin/ProductService.php`: filtered listing, transactional product/slug mutations and archive/restore.
- `app/Services/Admin/ProductImageService.php`: generated native public-disk uploads.
- `app/Http/Resources/Api/V1/Admin/Products/`: explicit product response allow-list and collection.
- `app/Policies/ProductPolicy.php`: active-admin product permissions; hard deletes denied.
- `app/Rules/ProductImagePath.php`: supplied assets or existing generated raster paths.

## Verification

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer audit
```

Tests use real bearer headers and an isolated in-memory SQLite database forced by
`phpunit.xml`; they do not reset the database configured in your local `.env`.
