<?php

return [
    /*
     * Requests per minute allowed to POST /api/shorten, per client IP.
     *
     * 30/min is a starting-point default, not a measured/tuned value —
     * see README §24. Revisit this once you have real traffic data:
     * too low and legitimate bulk users get blocked, too high and it
     * does nothing against abuse. Override via .env, never hardcode a
     * new number here.
     */
    'rate_limit_per_minute' => (int) env('SHORTEN_RATE_LIMIT_PER_MINUTE', 30),
];
