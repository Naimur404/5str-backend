<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessSubmission;
use App\Models\AttractionSubmission;
use App\Models\OfferingSubmission;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BusinessSubmissionController extends Controller
{
    /**
     * Submit a new business for review
     */
    public function submitBusiness(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'category' => 'required|string|max:100',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'website' => 'nullable|url|max:255',
                'opening_hours' => 'nullable|array',
                'opening_hours.*.day' => 'required_with:opening_hours|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'opening_hours.*.open_time' => 'required_with:opening_hours|date_format:H:i',
                'opening_hours.*.close_time' => 'required_with:opening_hours|date_format:H:i',
                'opening_hours.*.is_closed' => 'boolean',
                'images' => 'nullable|array|max:5',
                'images.*' => 'string', // Base64 encoded images
                'additional_info' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle image uploads
            $imagePaths = [];
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $index => $base64Image) {
                    $imagePath = $this->handleBase64ImageUpload($base64Image, 'business_submissions');
                    if ($imagePath) {
                        $imagePaths[] = $imagePath;
                    }
                }
            }

            // Create business submission
            $submission = BusinessSubmission::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'address' => $request->address,
                'city' => $request->city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
                'opening_hours' => $request->opening_hours ? json_encode($request->opening_hours) : null,
                'images' => !empty($imagePaths) ? json_encode($imagePaths) : null,
                'additional_info' => $request->additional_info,
                'status' => 'pending',
                'submission_type' => 'business',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Business submission received! We will review it within 24-48 hours.',
                'data' => [
                    'submission_id' => $submission->id,
                    'status' => $submission->status,
                    'estimated_review_time' => '24-48 hours'
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Business submission error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit business',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a new attraction for review
     */
    public function submitAttraction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'type' => 'required|string|in:historical,natural,cultural,entertainment,religious,educational,recreational',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'entry_fee' => 'nullable|numeric|min:0',
                'visiting_hours' => 'nullable|array',
                'visiting_hours.*.day' => 'required_with:visiting_hours|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'visiting_hours.*.open_time' => 'required_with:visiting_hours|date_format:H:i',
                'visiting_hours.*.close_time' => 'required_with:visiting_hours|date_format:H:i',
                'visiting_hours.*.is_closed' => 'boolean',
                'best_time_to_visit' => 'nullable|string|max:255',
                'facilities' => 'nullable|array',
                'facilities.*' => 'string|max:100',
                'images' => 'nullable|array|max:5',
                'images.*' => 'string', // Base64 encoded images
                'additional_info' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle image uploads
            $imagePaths = [];
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $index => $base64Image) {
                    $imagePath = $this->handleBase64ImageUpload($base64Image, 'attraction_submissions');
                    if ($imagePath) {
                        $imagePaths[] = $imagePath;
                    }
                }
            }

            // Create attraction submission
            $submission = AttractionSubmission::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'address' => $request->address,
                'city' => $request->city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'entry_fee' => $request->entry_fee,
                'visiting_hours' => $request->visiting_hours ? json_encode($request->visiting_hours) : null,
                'best_time_to_visit' => $request->best_time_to_visit,
                'facilities' => $request->facilities ? json_encode($request->facilities) : null,
                'images' => !empty($imagePaths) ? json_encode($imagePaths) : null,
                'additional_info' => $request->additional_info,
                'status' => 'pending',
                'submission_type' => 'attraction',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attraction submission received! We will review it within 24-48 hours.',
                'data' => [
                    'submission_id' => $submission->id,
                    'status' => $submission->status,
                    'estimated_review_time' => '24-48 hours'
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Attraction submission error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit attraction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a new offering for an existing business
     */
    public function submitOffering(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'business_id' => 'nullable|exists:businesses,id',
                'business_name' => 'required|string|max:255',
                'business_address' => 'required|string|max:500',
                'offering_name' => 'required|string|max:255',
                'offering_description' => 'required|string|max:1000',
                'offering_category' => 'required|string|max:100',
                'price' => 'nullable|numeric|min:0',
                'price_type' => 'nullable|string|in:fixed,range,negotiable,free',
                'availability' => 'nullable|string|max:255',
                'contact_info' => 'nullable|string|max:255',
                'images' => 'nullable|array|max:5',
                'images.*' => 'string', // Base64 encoded images
                'additional_info' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle image uploads
            $imagePaths = [];
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $index => $base64Image) {
                    $imagePath = $this->handleBase64ImageUpload($base64Image, 'offering_submissions');
                    if ($imagePath) {
                        $imagePaths[] = $imagePath;
                    }
                }
            }

            // Create offering submission
            $submission = OfferingSubmission::create([
                'user_id' => Auth::id(),
                'business_id' => $request->business_id,
                'business_name' => $request->business_name,
                'business_address' => $request->business_address,
                'offering_name' => $request->offering_name,
                'offering_description' => $request->offering_description,
                'offering_category' => $request->offering_category,
                'price' => $request->price,
                'price_type' => $request->price_type,
                'availability' => $request->availability,
                'contact_info' => $request->contact_info,
                'images' => !empty($imagePaths) ? json_encode($imagePaths) : null,
                'additional_info' => $request->additional_info,
                'status' => 'pending',
                'submission_type' => 'offering',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offering submission received! We will review it within 24-48 hours.',
                'data' => [
                    'submission_id' => $submission->id,
                    'status' => $submission->status,
                    'estimated_review_time' => '24-48 hours'
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Offering submission error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit offering',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's submissions
     */
    public function getUserSubmissions(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->query('type'); // business, attraction, offering, or null for all

            $submissions = collect();

            // Get business submissions
            if (!$type || $type === 'business') {
                $businessSubmissions = BusinessSubmission::where('user_id', $user->id)
                    ->latest()
                    ->get()
                    ->map(function ($submission) {
                        return [
                            'id' => $submission->id,
                            'type' => 'business',
                            'name' => $submission->name,
                            'status' => $submission->status,
                            'submitted_at' => $submission->created_at,
                            'reviewed_at' => $submission->reviewed_at,
                            'admin_notes' => $submission->admin_notes,
                        ];
                    });
                $submissions = $submissions->concat($businessSubmissions);
            }

            // Get attraction submissions
            if (!$type || $type === 'attraction') {
                $attractionSubmissions = AttractionSubmission::where('user_id', $user->id)
                    ->latest()
                    ->get()
                    ->map(function ($submission) {
                        return [
                            'id' => $submission->id,
                            'type' => 'attraction',
                            'name' => $submission->name,
                            'status' => $submission->status,
                            'submitted_at' => $submission->created_at,
                            'reviewed_at' => $submission->reviewed_at,
                            'admin_notes' => $submission->admin_notes,
                        ];
                    });
                $submissions = $submissions->concat($attractionSubmissions);
            }

            // Get offering submissions
            if (!$type || $type === 'offering') {
                $offeringSubmissions = OfferingSubmission::where('user_id', $user->id)
                    ->latest()
                    ->get()
                    ->map(function ($submission) {
                        return [
                            'id' => $submission->id,
                            'type' => 'offering',
                            'name' => $submission->offering_name,
                            'business_name' => $submission->business_name,
                            'status' => $submission->status,
                            'submitted_at' => $submission->created_at,
                            'reviewed_at' => $submission->reviewed_at,
                            'admin_notes' => $submission->admin_notes,
                        ];
                    });
                $submissions = $submissions->concat($offeringSubmissions);
            }

            // Sort by submission date
            $submissions = $submissions->sortByDesc('submitted_at')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'submissions' => $submissions,
                    'total_count' => $submissions->count(),
                    'pending_count' => $submissions->where('status', 'pending')->count(),
                    'approved_count' => $submissions->where('status', 'approved')->count(),
                    'rejected_count' => $submissions->where('status', 'rejected')->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get user submissions error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get submissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get submission details
     */
    public function getSubmissionDetails(Request $request, $type, $id)
    {
        try {
            $user = Auth::user();
            $submission = null;

            switch ($type) {
                case 'business':
                    $submission = BusinessSubmission::where('id', $id)
                        ->where('user_id', $user->id)
                        ->first();
                    break;
                case 'attraction':
                    $submission = AttractionSubmission::where('id', $id)
                        ->where('user_id', $user->id)
                        ->first();
                    break;
                case 'offering':
                    $submission = OfferingSubmission::where('id', $id)
                        ->where('user_id', $user->id)
                        ->first();
                    break;
            }

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'submission' => $submission,
                    'type' => $type,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get submission details error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get submission details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle base64 image upload
     */
    private function handleBase64ImageUpload(string $base64String, string $folder): ?string
    {
        try {
            // Check if the string contains data URL prefix
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
                $imageType = $matches[1];
                $base64String = substr($base64String, strpos($base64String, ',') + 1);
            } else {
                $imageType = 'jpg';
            }

            // Validate image type
            if (!in_array($imageType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new \Exception('Invalid image type');
            }

            // Decode base64
            $imageData = base64_decode($base64String);
            
            if ($imageData === false) {
                throw new \Exception('Invalid base64 data');
            }

            // Validate image size (max 5MB)
            if (strlen($imageData) > 5 * 1024 * 1024) {
                throw new \Exception('Image size too large (max 5MB)');
            }

            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $imageType;
            $filePath = $folder . '/' . $filename;

            // Save to storage
            $saved = Storage::disk('public')->put($filePath, $imageData);
            
            if (!$saved) {
                throw new \Exception('Failed to save image');
            }

            return $filePath;

        } catch (\Exception $e) {
            Log::error('Image upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all business categories for frontend consumption
     */
    public function getBusinessCategories(Request $request)
    {
        try {
            $categories = Category::where('is_active', true)
                ->where('level', 1) // Only top-level categories
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'icon_image', 'color_code']);

            return response()->json([
                'success' => true,
                'data' => $categories,
                'message' => 'Business categories retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get business categories error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}