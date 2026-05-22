<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsAdminController extends Controller
{
    public function __construct(protected SettingsService $settings)
    {
    }

    /**
     * GET /api/admin/settings
     * Returns all settings including sensitive values for authenticated admins.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settings->authenticatedSettings(),
        ]);
    }

    /**
     * PUT /api/admin/settings
     * Update settings. Accepts multipart/form-data for logo upload.
     */
    public function update(Request $request): JsonResponse
    {
        $allowedKeys = $this->settings->registeredKeys();

        // Reject unknown keys
        $extraKeys = array_diff(array_keys($request->except(['admin_logo_file'])), $allowedKeys);
        if (!empty($extraKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown settings keys: ' . implode(', ', $extraKeys),
            ], 422);
        }

        $rules = [
            'admin_title' => ['sometimes', 'string', 'min:1', 'max:100'],
            'ai_enabled' => ['sometimes', 'boolean'],
            'ai_api_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'admin_logo' => ['sometimes', 'nullable', 'string', 'max:500'],
            'admin_logo_file' => ['sometimes', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ];

        $validated = $request->validate($rules);

        // Handle logo file upload
        if ($request->hasFile('admin_logo_file')) {
            $url = $this->settings->uploadLogo($request->file('admin_logo_file'));
            $validated['admin_logo'] = $url;
        }

        // Remove the file key from settings data
        unset($validated['admin_logo_file']);

        // Only set keys that were actually provided
        $toUpdate = array_intersect_key($validated, array_flip($allowedKeys));

        if (!empty($toUpdate)) {
            $this->settings->set($toUpdate);
        }

        return response()->json([
            'success' => true,
            'data' => $this->settings->authenticatedSettings(),
            'message' => 'Settings updated successfully.',
        ]);
    }
}
