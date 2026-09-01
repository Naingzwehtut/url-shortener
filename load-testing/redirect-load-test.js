import http from 'k6/http';
import { check, sleep } from 'k6';

// Load test for GET /{shortCode} — the redirect endpoint — chosen because
// it's the one path every visitor hits, not just API consumers, and the
// one most exposed to real-world burst traffic (a link shared publicly).
//
// USAGE:
//   1. Seed short codes first (see seed-short-codes.sh in this directory)
//   2. Set SHORT_CODES to a comma-separated list of real, existing codes
//   3. k6 run -e BASE_URL=http://localhost:8000 -e SHORT_CODES=aB92xK,X7k91P load-testing/redirect-load-test.js
//
// This project does not publish RPS/latency numbers in the README because
// none have been measured against a real deployment yet — run this
// yourself and record what you actually observe.

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const SHORT_CODES = (__ENV.SHORT_CODES || '').split(',').filter(Boolean);

export const options = {
  scenarios: {
    steady_load: {
      executor: 'constant-vus',
      vus: 50,
      duration: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],   // fail the run if >1% of requests error
    http_req_duration: ['p(95)<300'], // adjust once you have a real baseline
  },
};

export default function () {
  if (SHORT_CODES.length === 0) {
    throw new Error('Set the SHORT_CODES env var to a comma-separated list of existing short codes before running this script.');
  }

  const code = SHORT_CODES[Math.floor(Math.random() * SHORT_CODES.length)];

  const res = http.get(`${BASE_URL}/${code}`, { redirects: 0 });

  check(res, {
    'status is 302': (r) => r.status === 302,
    'has Location header': (r) => r.headers['Location'] !== undefined,
  });

  sleep(0.1);
}
