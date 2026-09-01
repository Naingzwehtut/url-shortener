# URL Shortener API

A backend-focused URL shortener REST API built with **Laravel 12**, **PHP 8.3**, **PostgreSQL**, and **Redis**. This project exists to demonstrate backend engineering practices — clean architecture, correct HTTP semantics, database indexing, concurrency-safe counters, caching, analytics, rate limiting, and automated testing — not just CRUD.

> **On "production ready":** the code is complete and tested against a real PostgreSQL/Redis stack in CI. It has **not** been load tested against a live deployment by anyone but you — see [Load Testing](#load-testing) for why no throughput/latency numbers are published here, and run it yourself before calling this production ready for your use case.

---

## 1. Overview

Clients can create a shortened version of a long URL, retrieve/update/delete it, view access statistics and detailed click analytics, and — most importantly — visit the short URL itself and be redirected to the original, with each visit safely counted even under concurrent traffic and served from a Redis cache on the hot path.

## 2. Features

- Create, retrieve, update, and delete shortened URLs
- Collision-safe short code generation (6-character, mixed-case alphanumeric)
- Atomic, concurrency-safe access counting
- Public redirect endpoint (`GET /{shortCode}`) using a semantically correct `302`
- **Redis cache-aside layer** on the redirect and retrieval lookups, with correct invalidation on update/delete
- **Click analytics** — total/today/this-week counts, last-accessed time, clicks by date
- **Configurable rate limiting** on `POST /api/shorten`
- Form Request validation with clean, consistent JSON error responses (including `429`)
- API Resources controlling the exact public JSON shape
- Full Feature test suite covering every endpoint, caching behavior, analytics, and rate limiting
- Dockerized app + PostgreSQL + Redis, one-command startup
- GitHub Actions CI (install → migrate → test)
- OpenAPI 3.0 spec + importable Postman collection
- A runnable k6 load-testing script for the redirect endpoint

## 3. Architecture

```
Client
  │
  ▼
Laravel API
  │
  ├── Controllers        (UrlController, RedirectController — thin, no business logic)
  ├── Form Requests       (StoreUrlRequest, UpdateUrlRequest — validation only)
  ├── Services            (UrlShortenerService — short-code generation + collision retry)
  ├── API Resources        (UrlResource, UrlStatsResource, UrlAnalyticsResource)
  ├── Eloquent             (Url, UrlClick)
  ▼
PostgreSQL ◄──────────────► Redis (cache-aside, redirect + retrieval lookups only)
```

Redirect flow specifically:

```
Browser
   │
   │ GET /aB92xK
   ▼
RedirectController
   │
   ├── Redis: GET url:short_code:aB92xK
   │     │
   │     ├── HIT  → skip straight to the redirect
   │     │
   │     └── MISS → PostgreSQL SELECT (indexed) → populate Redis
   │
   ├── PostgreSQL UPDATE access_count = access_count + 1   (atomic, never cached)
   ├── PostgreSQL INSERT url_clicks (accessed_at, user_agent, referrer)
   │
   ▼
HTTP 302 + Location header
   │
   ▼
Original URL
```

**Why a service layer but not repositories:** the only piece of logic complex enough to deserve its own class is short-code generation with collision handling — everything else (a `WHERE short_code = ?` lookup, a delete) is a one-liner Eloquent already expresses clearly. A repository interface here would be indirection with no payoff — there's no second data source to swap in.

## 4. Technology Stack

| Concern | Choice |
|---|---|
| Language | PHP 8.3 |
| Framework | Laravel 12 |
| Database | PostgreSQL 16 |
| Cache | Redis 7 (predis client) |
| ORM | Eloquent |
| Validation | Laravel Form Requests |
| Response shaping | Laravel API Resources |
| Testing | PHPUnit (Feature + Unit) |
| Containerization | Docker / Docker Compose |
| CI | GitHub Actions |
| Load testing | k6 |
| API docs | OpenAPI 3.0 + Postman collection |

No auth or queues — deliberately, because neither is load-bearing for the stated requirements.

## 5. Database Schema

```
urls
----------------------------------
id             bigint, PK
url            text, required
short_code     varchar(10), required, UNIQUE
access_count   bigint, default 0
created_at     timestamp
updated_at     timestamp

url_clicks
----------------------------------
id             bigint, PK
url_id         bigint, FK -> urls.id, cascade on delete
accessed_at    timestamp
user_agent     varchar(512), nullable
referrer       varchar(2048), nullable
                  INDEX (url_id, accessed_at)
```

**Why `short_code` needs a UNIQUE constraint:** it's the whole identity of a short URL — two rows sharing a code would make redirects ambiguous. This is enforced at the database level, not just checked in application code (see the race condition explanation below).

**Index vs. unique constraint:** an index is a data structure (a B-tree, by Postgres default) that makes lookups by a column fast. A unique constraint is a *rule* — no two rows may share a value. In PostgreSQL, declaring a column `UNIQUE` automatically creates a unique index to enforce that rule, so fast lookups on `short_code` come "for free" as a side effect of correctness. This is why the migration only calls `->unique()` — a second plain index on top would be redundant and would slow down every write for no benefit.

**Why `url_clicks` has a composite index on `(url_id, accessed_at)` rather than two separate indexes:** every analytics query is "clicks for this url_id, filtered/grouped by accessed_at" — the composite index matches that exact query shape, letting Postgres use the index for both the equality filter on `url_id` and the range/group operations on `accessed_at` in one structure.

**How Postgres finds a row by `short_code`:**

```sql
EXPLAIN ANALYZE
SELECT * FROM urls WHERE short_code = 'aB92xK';
```

Expect an **Index Scan** (or **Index Only Scan**), O(log n), rather than a **Sequential Scan**, O(n):

```
Index Scan using urls_short_code_unique on urls  (cost=0.28..8.30 rows=1 width=...)
  Index Cond: (short_code = 'aB92xK'::text)
```

If you see `Seq Scan` on a small dev table, that's normal — Postgres's planner may decide a scan is cheaper below some row-count threshold; it flips to Index Scan as the table grows. `ANALYZE urls;` refreshes stale planner statistics if the choice looks wrong on a table that's no longer small.

## 6. Short Code Generation & the Race Condition

Codes are 6 characters from `[A-Za-z0-9]` (62 characters), generated with PHP's `random_int()` — a CSPRNG, because predictable codes would let an attacker guess other users' short URLs.

**The race condition, explicitly:** a naive flow — check if a code exists, then insert — has a gap between the two steps. Two concurrent requests can both check the same not-yet-taken code, both see "free," and both attempt to insert it. Only the database can arbitrate between two requests it's aware of simultaneously; PHP-level code running in separate processes cannot.

This project's fix: **don't pre-check**. `UrlShortenerService::create()` attempts the `INSERT` directly and relies on the `UNIQUE` constraint as the source of truth. If Postgres rejects the insert with SQLSTATE `23505` (unique violation), the service catches it, generates a new code, and retries (up to 5 attempts — with a 62⁶ ≈ 56.8 billion code keyspace, exhausting that isn't a practical concern short of billions of rows).

## 7. Validation

Handled entirely in Form Request classes (`StoreUrlRequest`, `UpdateUrlRequest`), never in controllers. Rules: required, string, max 2048 characters, and Laravel's `url:http,https` rule — deliberately *not* `active_url`, which does a per-request DNS lookup and would be slow and unreliable for URLs that may not resolve yet.

## 8. Concurrency: Atomic Access Counting

`Url::incrementAccessCount()` calls `$this->increment('access_count')`, compiled to one atomic SQL statement:

```sql
UPDATE urls SET access_count = access_count + 1 WHERE id = ?;
```

Compare to `$url->access_count++; $url->save();` — a PHP-side read, increment, and write with a gap between each step. Under concurrency, two redirects can both read `41`, both compute `42`, and both write `42` — one visit silently lost (a classic **lost update**). The atomic `UPDATE ... SET x = x + 1` has no such gap: the increment happens inside the database in one indivisible statement.

## 9. HTTP Status Codes

| Code | Used for |
|---|---|
| `200 OK` | Successful GET/PUT with a body |
| `201 Created` | Successful POST that creates a resource |
| `204 No Content` | Successful DELETE |
| `302 Found` | The redirect endpoint — see below for why not 301 |
| `404 Not Found` | Short code doesn't exist |
| `422 Unprocessable Entity` | Well-formed request, invalid content (validation) |
| `429 Too Many Requests` | Rate limit exceeded on `POST /api/shorten` |
| `500 Internal Server Error` | Unhandled server-side failure |

**302 vs. 301:** a 301 tells browsers/crawlers to cache the redirect indefinitely and stop asking the origin. That would break both click counting (repeat visitors never hit the server again) and the Update endpoint (already-cached clients would never see the new destination). `302` says "this is where it points *right now*" — correct for an updatable resource whose value depends on every hit being measured.

**422 vs. 400:** Laravel's idiomatic default. The request is well-formed JSON hitting a valid route — it's the *content* that fails business rules, which is what 422 means. 400 is reserved for genuinely malformed requests, which Laravel handles automatically before your code runs.

## 10. Error Handling

All exceptions are handled centrally in `bootstrap/app.php` via `->withExceptions()`, so every API error response has the same shape and never leaks stack traces or SQL fragments:

```json
{ "message": "Resource not found." }
```
```json
{ "message": "The given data was invalid.", "errors": { "url": ["The url field must be a valid URL."] } }
```
```json
{ "message": "Too many requests. Please slow down and try again shortly." }
```

## 11. API Documentation

- **OpenAPI 3.0 spec:** [`docs/openapi.yaml`](docs/openapi.yaml)
- **Postman collection:** [`docs/postman_collection.json`](docs/postman_collection.json)

### Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/shorten` | Create a short URL (rate limited) |
| `GET` | `/api/shorten/{shortCode}` | Retrieve a short URL's details (cached) |
| `PUT` | `/api/shorten/{shortCode}` | Update the target URL (invalidates cache) |
| `DELETE` | `/api/shorten/{shortCode}` | Delete a short URL (invalidates cache) |
| `GET` | `/api/shorten/{shortCode}/stats` | Basic access count |
| `GET` | `/api/shorten/{shortCode}/analytics` | Detailed click analytics |
| `GET` | `/{shortCode}` | Redirect to the original URL (cached, logs a click) |

### Example: analytics response

```json
{
  "id": 1,
  "url": "https://www.example.com/example",
  "shortCode": "aB92xK",
  "totalClicks": 42,
  "clicksToday": 5,
  "clicksThisWeek": 18,
  "lastAccessedAt": "2026-08-31T09:12:00+00:00",
  "clicksByDate": [
    { "date": "2026-08-29", "count": 12 },
    { "date": "2026-08-30", "count": 25 },
    { "date": "2026-08-31", "count": 5 }
  ]
}
```

## 12. Setup

### Docker (recommended)

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

API available at `http://localhost:8000`. This brings up the app, PostgreSQL, and Redis together.

### Local (without Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
# create a local Postgres DB matching .env, and either run a local Redis
# or set CACHE_STORE=array in .env to skip caching entirely
php artisan migrate
php artisan serve
```

## 13. Testing

```bash
docker compose exec app php artisan test
```

Tests run against real PostgreSQL (the `23505` unique-violation retry path and atomic increments are Postgres-specific behaviors an in-memory substitute wouldn't faithfully exercise) and the `array` cache driver (fast, isolated, same `Cache` facade interface as Redis in production — so the caching *logic* is verified without needing a live Redis instance in CI).

**Coverage:** creation, retrieval, update, delete, redirect, stats, analytics, caching (populate/hit/invalidate/never-cache-404s), and rate limiting — see `tests/Feature/*` and `tests/Unit/*`.

## 14. Caching (Redis, Cache-Aside)

**Pattern:** on `GET /api/shorten/{shortCode}` and `GET /{shortCode}`, check Redis first. On a **hit**, skip the database entirely. On a **miss**, query Postgres and populate Redis for next time (`Cache::remember`).

**TTL:** 1 hour (`Url::CACHE_TTL_SECONDS`). Chosen as a reasonable default for a mapping that changes rarely (updates are a deliberate, infrequent user action) — not derived from measurement, and worth revisiting once you have real update-frequency data.

**Invalidation:** `PUT` and `DELETE` both call `Cache::forget()` on the entry before returning, so the next read is guaranteed fresh rather than waiting out the TTL. This is the one place correctness matters more than the cache's performance benefit.

**What's deliberately never cached:**
- **404s** — `Cache::remember`'s closure throws `ModelNotFoundException` for a missing code, which propagates out of `remember()` without being cached.
- **`access_count`** — the stats and analytics endpoints always query Postgres directly; caching a click count would mean serving a stale number on the one field whose whole purpose is to be exact.

**Why the redirect lookup is a good caching candidate:** it's read-heavy (every visitor, not just API consumers), the underlying data changes rarely, and a bounded staleness window (up to 1 hour, only for the *target URL*, not the click count) is an acceptable trade-off for a link-following redirect. **Problems stale data could cause here:** if a URL is updated, a cached visitor could be redirected to the old destination for up to the TTL if invalidation ever failed — which is exactly why invalidation on update/delete is synchronous and unconditional, not best-effort.

## 15. Analytics

`url_clicks` logs one row per redirect: `url_id`, `accessed_at`, `user_agent`, `referrer`. Deliberately **no IP address** and no other persistent identifier — the aggregate stats this project exposes don't need one, and collecting it wouldn't be justified by that use case.

`access_count` (on `urls`) and `url_clicks` serve different purposes: `access_count` is a cheap, always-fresh O(1) total maintained via atomic increment for the hot redirect path; `url_clicks` is a detailed log that makes time-based slicing (today, this week, by date) possible, at the cost of being a full table rather than a single counter.

## 16. Rate Limiting

`POST /api/shorten` is limited via a named limiter (`RateLimiter::for('shorten', ...)` in `AppServiceProvider`), keyed by client IP, with the limit itself read from `config('url_shortener.rate_limit_per_minute')` — set via the `SHORTEN_RATE_LIMIT_PER_MINUTE` env var (default `30`, see `.env.example`).

**Why a URL shortener specifically needs rate limiting:** the creation endpoint is cheap to call and easy to script, making it an attractive target for spam-link generation, open-redirect abuse (using your domain to mask a malicious destination), or simply exhausting the short-code keyspace/database with junk rows. Unlike the redirect endpoint — which *should* handle high legitimate traffic — creation traffic has no legitimate reason to be extremely high-frequency from a single client.

**On the number itself:** 30/minute is a placeholder default, not a tuned value — there's no real traffic pattern to tune it against yet. It's a config value specifically so it can change without a code deploy once you have one.

## 17. Load Testing

See [`load-testing/`](load-testing/) — a k6 script targeting `GET /{shortCode}` plus a seeding helper and instructions.

**No numbers are published here.** A load-test result only means something for the exact environment it ran on; publishing a number without having run it (or copying one from a different machine) would be indistinguishable from fabricating it. Run `load-testing/README.md`'s steps yourself and record what you actually observe.

## 18. Design Decisions

Thin controllers; validation only in Form Requests; the one piece of real business logic (collision-safe code generation) in a single service class; JSON shape controlled entirely by API Resources; every concurrency-sensitive operation (code creation, access counting) pushed to a database-level atomic operation; cache reads/writes kept strictly separate from the fields that must always be exact (`access_count`, click logs).

## 19. Performance Considerations

The core lookup is index-backed (§5) and O(log n) as the table grows. The Redis cache-aside layer (§14) further reduces database load on the hot redirect path specifically, since that path is read-heavy and tolerant of a short, bounded staleness window. No throughput/latency numbers are claimed — see §17.

## 20. Roadmap

Implemented:

```
✅ Clean REST API
✅ PostgreSQL + Indexing
✅ HTTP Redirects
✅ Atomic Click Tracking
✅ Automated Tests
✅ Docker
✅ CI
✅ API Documentation (OpenAPI + Postman)
✅ Redis cache-aside layer with invalidation
✅ Click analytics (url_clicks, aggregate stats)
✅ Configurable rate limiting (429 responses)
✅ Load-testing tooling (k6 script + seeding helper)
```

Genuinely open, and honestly so — these require your own run, not more code from me:

```
⏭ Actually running the load test against a real deployment and recording
   the results (RPS, avg/P95/P99 latency, error rate)
⏭ Tuning SHORTEN_RATE_LIMIT_PER_MINUTE against observed traffic instead of
   the 30/min placeholder
⏭ Deciding a caching TTL from measured update frequency instead of the
   1-hour placeholder
```

## 21. License

MIT — see [LICENSE](LICENSE).
