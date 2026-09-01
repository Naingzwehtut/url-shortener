# Load Testing

Targets `GET /{shortCode}`, the redirect endpoint, since it's the highest-traffic path in this API — every visitor hits it, not just API consumers.

No results are checked into this repository. A load test result is only meaningful for the exact hardware, database state, and network it was run on; a number copied from someone else's run (or generated without actually running anything) is worse than no number, because it looks authoritative while being wrong for your environment.

## Prerequisites

- [k6](https://k6.io/docs/get-started/installation/) installed locally
- The app running (`docker compose up -d`) with migrations applied

## Running it

```bash
# 1. Seed some short codes to hit
./load-testing/seed-short-codes.sh http://localhost:8000 20

# 2. Run the load test against them
k6 run \
  -e BASE_URL=http://localhost:8000 \
  -e SHORT_CODES=<paste the comma-separated codes from step 1> \
  load-testing/redirect-load-test.js
```

## Reading the output

k6 prints, per run:

- **Requests per second** — `http_reqs` rate
- **Latency** — `http_req_duration` (avg, and the `p(90)`/`p(95)` you configure)
- **Error rate** — `http_req_failed`

The script's default scenario is 50 constant virtual users for 30 seconds — a starting point, not a prescribed target. Adjust `vus`/`duration` in `redirect-load-test.js` to match what you actually want to learn (baseline capacity, breaking point, sustained load, etc.), then report whatever k6 actually prints from your run — not what you expect it to print.

## What to check afterward

- Whether the Redis cache is actually being hit (Phase 22) — a cold cache during the run will show materially worse latency than a warm one, so decide deliberately whether you're testing cold-start or steady-state behavior
- Postgres connection pool exhaustion under the configured concurrency
- Whether the rate limiter (Phase 24) is interfering with the load test itself — the `shorten` limiter only applies to `POST /api/shorten`, not the redirect endpoint, so it shouldn't, but verify
