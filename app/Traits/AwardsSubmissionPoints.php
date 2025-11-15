<?php

namespace App\Traits;

use App\Models\UserPoint;

trait AwardsSubmissionPoints
{
    /**
     * Award points to user for approved submission
     *
     * @param object $submission The submission model instance (BusinessSubmission, AttractionSubmission, or OfferingSubmission)
     * @return void
     */
    public static function awardSubmissionPoints($submission)
    {
        if (!$submission->user_id || $submission->status !== 'approved') {
            return;
        }

        // Check if points have already been awarded for this submission
        $existingPoints = UserPoint::where('reference_type', get_class($submission))
            ->where('reference_id', $submission->id)
            ->where('point_type', 'submission')
            ->first();

        if ($existingPoints) {
            return; // Points already awarded
        }

        // Determine points based on submission type
        $points = self::calculateSubmissionPoints($submission);
        
        if ($points > 0) {
            // Create points entry
            UserPoint::create([
                'user_id' => $submission->user_id,
                'points' => $points,
                'point_type' => 'submission',
                'reference_id' => $submission->id,
                'reference_type' => get_class($submission),
                'description' => self::getSubmissionPointsDescription($submission)
            ]);

            // Update user's total points
            self::updateUserTotalPoints($submission->user_id);
        }
    }

    /**
     * Calculate points based on submission type and quality
     *
     * @param object $submission
     * @return int
     */
    private static function calculateSubmissionPoints($submission)
    {
        $submissionType = class_basename(get_class($submission));
        
        switch ($submissionType) {
            case 'BusinessSubmission':
                $basePoints = config('submissions.points.business', 25);
                break;
            case 'AttractionSubmission':
                $basePoints = config('submissions.points.attraction', 20);
                break;
            case 'OfferingSubmission':
                $basePoints = config('submissions.points.offering', 15);
                break;
            default:
                $basePoints = 15; // Default fallback
        }

        $bonusPoints = 0;

        // Bonus for detailed descriptions
        if (isset($submission->description) && strlen($submission->description) > 100) {
            $bonusPoints += 5;
        }

        // Bonus for providing images
        if (isset($submission->images) && !empty($submission->images)) {
            $images = is_string($submission->images) ? json_decode($submission->images, true) : $submission->images;
            if (is_array($images) && count($images) > 0) {
                $bonusPoints += min(10, count($images) * 2); // Max 10 bonus points for images
            }
        }

        // Bonus for additional information
        if (isset($submission->additional_info) && !empty($submission->additional_info)) {
            $bonusPoints += 3;
        }

        // Bonus for business submissions with complete contact info
        if ($submissionType === 'BusinessSubmission') {
            if (!empty($submission->phone) || !empty($submission->email) || !empty($submission->website)) {
                $bonusPoints += 5;
            }
        }

        return $basePoints + $bonusPoints;
    }

    /**
     * Get description for points earning
     *
     * @param object $submission
     * @return string
     */
    private static function getSubmissionPointsDescription($submission)
    {
        $submissionType = class_basename(get_class($submission));
        $itemName = $submission->name ?? $submission->offering_name ?? 'submission';
        
        switch ($submissionType) {
            case 'BusinessSubmission':
                return "Business submission approved: {$itemName}";
            case 'AttractionSubmission':
                return "Attraction submission approved: {$itemName}";
            case 'OfferingSubmission':
                return "Offering submission approved: {$itemName}";
            default:
                return "Submission approved: {$itemName}";
        }
    }

    /**
     * Update user's total points
     *
     * @param int $userId
     * @return void
     */
    private static function updateUserTotalPoints($userId)
    {
        $totalPoints = UserPoint::where('user_id', $userId)->sum('points');
        
        \App\Models\User::where('id', $userId)->update([
            'total_points' => $totalPoints
        ]);
    }
}