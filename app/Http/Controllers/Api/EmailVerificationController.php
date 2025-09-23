<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Mail\EmailVerification;
use Carbon\Carbon;

class EmailVerificationController extends Controller
{
    /**
     * Verify email with code
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $verificationCode = EmailVerificationCode::findValidCode($request->email, $request->code);

            if (!$verificationCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ], 400);
            }

            // Mark code as verified
            $verificationCode->markAsVerified();

            // Update user's email_verified_at
            $user = User::where('email', $request->email)->first();
            if ($user) {
                $user->update(['email_verified_at' => Carbon::now()]);
                
                // Generate auth token for user
                $token = $user->createToken('auth_token')->plainTextToken;
                
                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully! You can now login.',
                    'data' => [
                        'verified' => true,
                        'verified_at' => $user->email_verified_at,
                        'token' => $token,
                        'token_type' => 'Bearer',
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'city' => $user->city,
                            'is_active' => $user->is_active,
                            'role' => $user->roles->first()?->name ?? 'user'
                        ]
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Resend verification code
     */
    public function resend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if email is already verified
            if (EmailVerificationCode::isEmailVerified($request->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is already verified. Please try logging in.'
                ], 400);
            }

            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if there's a recent verification code (prevent spam)
            $latestCode = EmailVerificationCode::getLatestForEmail($request->email);
            if ($latestCode && $latestCode->created_at->diffInMinutes(Carbon::now()) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait at least 2 minutes before requesting a new code.',
                    'retry_after_seconds' => 120 - $latestCode->created_at->diffInSeconds(Carbon::now())
                ], 429);
            }

            // Generate new verification code
            $verificationCode = EmailVerificationCode::createForEmail($request->email);

            // Send verification email
            Mail::to($request->email)->send(new EmailVerification($verificationCode, $user->name));

            return response()->json([
                'success' => true,
                'message' => 'New verification code sent to your email.',
                'data' => [
                    'email' => $request->email,
                    'verification_expires_at' => $verificationCode->expires_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check verification status
     */
    public function status(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $isVerified = EmailVerificationCode::isEmailVerified($request->email);
            $latestCode = EmailVerificationCode::getLatestForEmail($request->email);

            $response = [
                'success' => true,
                'data' => [
                    'email' => $request->email,
                    'is_verified' => $isVerified,
                    'verification_required' => !$isVerified
                ]
            ];

            if (!$isVerified && $latestCode) {
                $response['data']['verification_expires_at'] = $latestCode->expires_at;
                $response['data']['is_expired'] = $latestCode->isExpired();
                $response['data']['minutes_remaining'] = $latestCode->isExpired() ? 0 : 
                    Carbon::now()->diffInMinutes($latestCode->expires_at, false);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check verification status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}