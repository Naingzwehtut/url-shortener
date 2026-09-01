<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No auth model yet — every request is allowed to hit this endpoint
        // (subject to rate limiting, see routes/api.php).
        return true;
    }

    public function rules(): array
    {
        return [
            // 'url' active_url would perform a DNS lookup on every request —
            // too slow and flaky for a shortener that should accept URLs to
            // sites that may not resolve at creation time. Format validation
            // (scheme + structure) is the right level of strictness here.
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * Laravel's default behaviour on a failed FormRequest is 422, which is
     * the semantically correct code for "well-formed request, invalid
     * content" — see README §10 for why we standardize on 422 over 400.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
