<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\R2Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadAdminController extends Controller
{
    private const ALLOWED_DIRECTORIES = [
        'banners', 'businesses/logos', 'businesses/covers', 'businesses/gallery',
        'offerings/images', 'offers/banners', 'attractions/gallery',
        'categories/icons', 'categories/banners', 'avatars',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],
            'directory' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_DIRECTORIES)],
        ]);

        $url = R2Storage::storeUrl($data['file'], $data['directory']);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'directory' => $data['directory'],
            ],
        ], 201);
    }
}
