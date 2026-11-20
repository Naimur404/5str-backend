<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make every API JsonResponse tolerant to bad UTF-8 bytes and emit
 * unescaped Unicode.
 *
 * `json_encode()` throws `Malformed UTF-8 characters, possibly
 * incorrectly encoded` whenever a single byte in the payload is not
 * valid UTF-8. In our data set this happens occasionally on rows
 * imported from older systems where a description or business name
 * has Latin-1 bytes mixed in. Without this middleware that one bad
 * row crashes its entire endpoint with a 500.
 *
 * Setting `JSON_INVALID_UTF8_SUBSTITUTE` makes json_encode replace the
 * offending byte with U+FFFD (REPLACEMENT CHARACTER) instead of
 * throwing. The rest of the response stays intact, the user sees the
 * record they asked for, and we get a chance to clean the data on a
 * background pass instead of taking down the endpoint.
 *
 * `JSON_UNESCAPED_UNICODE` keeps Bangla, Arabic, etc. readable in
 * payloads and on the wire (no `\uXXXX` runs).
 *
 * `JSON_UNESCAPED_SLASHES` keeps URLs in payloads readable.
 */
class HardenJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(
                $response->getEncodingOptions()
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE,
            );
        }

        return $response;
    }
}
