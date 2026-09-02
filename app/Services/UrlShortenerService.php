<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Url;
use Illuminate\Database\QueryException;

/**
 * Encapsulates the one piece of business logic that doesn't belong in a
 * controller or a model: generating a short code and safely persisting it
 * despite the possibility of a collision under concurrent requests.
 */
class UrlShortenerService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    private const CODE_LENGTH = 6;
    private const MAX_ATTEMPTS = 5;

    public function create(string $url): Url
    {
        // --- The race condition, explained ---
        //
        // A naive implementation does:
        //   1. generate code
        //   2. SELECT ... WHERE short_code = ? (check it's free)
        //   3. INSERT ...
        //
        // Between steps 2 and 3, a second request can generate and insert
        // the SAME code. Step 2 in request A said "free", but by the time
        // request A reaches step 3, request B has already taken it. This
        // is a classic TOCTOU (time-of-check to time-of-use) bug. No
        // amount of "checking harder" in PHP fixes this — only the
        // database can arbitrate between two concurrent transactions,
        // because only the database sees both of them at once.
        //
        // So: we don't pre-check at all. We attempt the INSERT directly
        // and let the UNIQUE constraint on short_code be the single
        // source of truth. If PostgreSQL rejects the insert with a unique
        // violation (SQLSTATE 23505), we retry with a fresh code. This is
        // correct under any level of concurrency, because the guarantee
        // comes from the database's own constraint enforcement, not from
        // application-level timing.
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return Url::create([
                    'url' => $url,
                    'short_code' => $this->generateCode(),
                    // Explicit, not relied on from the DB's DEFAULT 0:
                    // Eloquent doesn't re-fetch DB-applied defaults into the
                    // in-memory model after an insert, so leaving this out
                    // would make $url->access_count null immediately after
                    // create() despite the database row correctly being 0.
                    'access_count' => 0,
                ]);
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e; // some other DB error — don't swallow it
                }

                if ($attempts >= self::MAX_ATTEMPTS) {
                    throw $e; // extremely unlikely with a 62^6 keyspace, but don't loop forever
                }

                // loop and try again with a newly generated code
            }
        }
    }

    private function generateCode(): string
    {
        // random_int() is a CSPRNG (unlike mt_rand/rand), which matters
        // even here: predictable short codes would let an attacker guess
        // other users' short URLs.
        $alphabetLength = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // PostgreSQL SQLSTATE 23505 = unique_violation
        return $e->getCode() === '23505';
    }
}
