#!/usr/bin/env bash
set -euo pipefail

# Creates N short URLs against a running instance and prints their short
# codes as a comma-separated list, ready to paste into SHORT_CODES for
# redirect-load-test.js.
#
# Usage: ./seed-short-codes.sh [base_url] [count]

BASE_URL="${1:-http://localhost:8000}"
COUNT="${2:-20}"

codes=()
for i in $(seq 1 "$COUNT"); do
  code=$(curl -s -X POST "${BASE_URL}/api/shorten" \
    -H "Content-Type: application/json" \
    -d "{\"url\": \"https://example.com/load-test-${i}\"}" \
    | grep -o '"shortCode":"[^"]*"' | cut -d'"' -f4)
  codes+=("$code")
done

IFS=,; echo "${codes[*]}"
