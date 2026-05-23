<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\ResponseFactory as BaseResponseFactory;

/**
 * Drop-in ResponseFactory that bakes UTF-8-tolerant flags into every
 * `response()->json(...)` call.
 *
 * Why this exists:
 *   `JsonResponse::__construct()` runs `json_encode()` synchronously
 *   with the encoding options it received at construction time
 *   (default `0`). If a payload contains a single byte that's not
 *   valid UTF-8 (legacy imports, mixed-encoding pastes), the encode
 *   throws BEFORE response middleware can see the response, so a
 *   middleware-level fix can't repair it.
 *
 * Solution: subclass the framework's ResponseFactory and rebind the
 * `'response'` and `Illuminate\Contracts\Routing\ResponseFactory`
 * singletons to it. Now every controller calling `response()->json()`
 * (or the `response()` helper) gets:
 *
 *   - JSON_UNESCAPED_UNICODE     keeps Bangla / Arabic readable on the
 *                                wire, no \uXXXX escapes.
 *   - JSON_UNESCAPED_SLASHES     keeps URLs in payloads readable.
 *   - JSON_INVALID_UTF8_SUBSTITUTE replaces malformed bytes with U+FFFD
 *                                (REPLACEMENT CHARACTER) instead of
 *                                throwing, so a single bad byte in one
 *                                row never crashes the whole endpoint.
 *
 * The HardenJsonResponse middleware is still active as a defence in
 * depth for any code path that constructs a JsonResponse directly
 * (bypassing the factory).
 */
class SafeResponseFactory extends BaseResponseFactory
{
    /**
     * Encoding flags ORed into every json() response built through
     * this factory.
     */
    public const SAFE_JSON_FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE;

    public function json($data = [], $status = 200, array $headers = [], $options = 0): JsonResponse
    {
        return parent::json($data, $status, $headers, $options | self::SAFE_JSON_FLAGS);
    }
}
