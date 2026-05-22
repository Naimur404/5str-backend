<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * GET /api/v1/settings/public
     * Returns only non-sensitive branding settings (no auth required).
     */
    public function publicSettings(): JsonResponse
    {
        $settings = app(SettingsService::class)->publicSettings();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }
}
