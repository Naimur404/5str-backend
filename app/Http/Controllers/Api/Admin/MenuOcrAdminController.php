<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\MenuOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin-only menu OCR endpoint.
 *
 * Flow:
 *   1. Admin opens the offerings page, selects a business (parent or
 *      branch), uploads a menu photo.
 *   2. Frontend POSTs to /api/admin/offerings/menu-ocr.
 *   3. We run the image through MenuOcrService (GPT-4o vision +
 *      strict JSON schema), then attempt a best-effort match of each
 *      `category_hint` string to an existing Category row by case-
 *      insensitive name compare.
 *   4. We respond with the parsed items (NOT persisted). The admin
 *      reviews / edits / deletes rows in the UI, then either
 *      "Save selected" (creates one offering per row) or "Cancel".
 *
 * The actual offering creation reuses the existing
 * BusinessOfferingAdminController so the cascade-to-branches logic,
 * activity log, observer notifications, etc. fire on every row
 * exactly as if the admin had filled the form by hand.
 *
 * Requires `admin` middleware (route group already enforces this).
 */
class MenuOcrAdminController extends Controller
{
    public function __construct(private MenuOcrService $ocr) {}

    /**
     * POST /api/admin/offerings/menu-ocr
     *   image:        the menu photograph (max 8 MB).
     *   business_id:  optional, just used to suggest currency hint.
     *
     * Response:
     *   {
     *     success: true,
     *     data: {
     *       detected_currency: "BDT" | "৳" | null,
     *       items: [
     *         {
     *           name, description, price, price_max,
     *           category_hint,
     *           suggested_category_id,        // best-effort match
     *           local_id                       // FE keys for review UI
     *         }
     *       ]
     *     }
     *   }
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'business_id' => ['nullable', 'integer'],
        ]);

        try {
            $extracted = $this->ocr->extract($request->file('image'));
        } catch (\Throwable $e) {
            Log::warning('Menu OCR extraction failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $items = collect($extracted['items']);
        $hintedCategoryNames = $items
            ->pluck('category_hint')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->unique()
            ->values()
            ->all();

        $hintToCategoryId = $this->resolveCategoryHints($hintedCategoryNames);

        $items = $items
            ->map(function (array $row) use ($hintToCategoryId) {
                $hint = $row['category_hint'] ?? null;
                $row['suggested_category_id'] = $hint
                    ? ($hintToCategoryId[mb_strtolower($hint)] ?? null)
                    : null;
                $row['local_id'] = (string) Str::uuid();
                return $row;
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'detected_currency' => $extracted['detected_currency'],
                'items' => $items,
            ],
        ]);
    }

    /**
     * Best-effort: match each hint string to an active category by
     * case-insensitive name. Falls through silently for unknown
     * hints — the admin will pick a category in the UI.
     *
     * @param  array<int, string>  $hints
     * @return array<string, int>
     */
    private function resolveCategoryHints(array $hints): array
    {
        if (empty($hints)) {
            return [];
        }

        $lowered = array_map(static fn ($h) => mb_strtolower($h), $hints);

        $matches = Category::query()
            ->where('is_active', true)
            ->whereIn(\DB::raw('LOWER(name)'), $lowered)
            ->select('id', 'name')
            ->get();

        $map = [];
        foreach ($matches as $cat) {
            $map[mb_strtolower($cat->name)] = (int) $cat->id;
        }
        return $map;
    }
}
