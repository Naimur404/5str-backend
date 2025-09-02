<?php

namespace App\Jobs;

use App\Models\PersonalizationMetrics;
use App\Models\UserInteraction;
use App\Models\User;
use App\Services\ABTestingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyzeRecommendationEffectivenessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $timeframe;
    
    public $timeout = 600; // 10 minutes
    public $tries = 1; // Only try once for analytics

    public function __construct($timeframe = '24h')
    {
        $this->timeframe = $timeframe;
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        try {
            Log::info('Starting recommendation effectiveness analysis', [
                'timeframe' => $this->timeframe
            ]);

            $timeframeDates = $this->getTimeframeDates();
            
            // Analyze conversion rates (simplified without A/B testing)
            $conversionMetrics = $this->analyzeConversionMetrics($timeframeDates);
            
            // Analyze user engagement patterns
            $engagementMetrics = $this->analyzeEngagementMetrics($timeframeDates);
            
            // Store comprehensive analytics (simplified without A/B testing)
            $this->storeAnalyticsResults($conversionMetrics, $engagementMetrics);
            
            Log::info('Recommendation effectiveness analysis completed', [
                'timeframe' => $this->timeframe,
                'metrics_stored' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Recommendation effectiveness analysis failed', [
                'timeframe' => $this->timeframe,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getTimeframeDates(): array
    {
        $endDate = now();
        
        switch ($this->timeframe) {
            case '1h':
                $startDate = $endDate->copy()->subHour();
                break;
            case '24h':
                $startDate = $endDate->copy()->subDay();
                break;
            case '7d':
                $startDate = $endDate->copy()->subWeek();
                break;
            case '30d':
                $startDate = $endDate->copy()->subMonth();
                break;
            default:
                $startDate = $endDate->copy()->subDay();
        }

        return [$startDate, $endDate];
    }

    private function analyzeConversionMetrics(array $timeframeDates): array
    {
        [$startDate, $endDate] = $timeframeDates;

        // Analyze conversion funnel: view -> click -> action
        $conversionFunnel = DB::table('user_interactions')
            ->select([
                'interaction_type',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(DISTINCT business_id) as unique_businesses')
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('interaction_type')
            ->get()
            ->keyBy('interaction_type');

        // Calculate conversion rates between funnel steps
        $views = $conversionFunnel->get('view')?->total_count ?? 0;
        $clicks = $conversionFunnel->get('click')?->total_count ?? 0;
        $favorites = $conversionFunnel->get('favorite')?->total_count ?? 0;
        $calls = $conversionFunnel->get('phone_call')?->total_count ?? 0;

        return [
            'funnel_data' => $conversionFunnel->toArray(),
            'conversion_rates' => [
                'view_to_click' => $views > 0 ? $clicks / $views : 0,
                'click_to_favorite' => $clicks > 0 ? $favorites / $clicks : 0,
                'view_to_call' => $views > 0 ? $calls / $views : 0,
                'overall_conversion' => $views > 0 ? ($favorites + $calls) / $views : 0
            ]
        ];
    }

    private function analyzeEngagementMetrics(array $timeframeDates): array
    {
        [$startDate, $endDate] = $timeframeDates;

        // Analyze user engagement patterns
        $engagementData = DB::table('user_interactions')
            ->select([
                'user_id',
                DB::raw('COUNT(*) as total_interactions'),
                DB::raw('COUNT(DISTINCT business_id) as unique_businesses'),
                DB::raw('COUNT(DISTINCT DATE(created_at)) as active_days'),
                DB::raw('AVG(CASE WHEN interaction_type IN ("favorite", "phone_call", "review") THEN 1 ELSE 0 END) as engagement_score')
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('user_id')
            ->having('total_interactions', '>=', 3) // Only users with meaningful activity
            ->get();

        $totalUsers = $engagementData->count();
        $highEngagementUsers = $engagementData->where('engagement_score', '>', 0.3)->count();
        
        return [
            'total_active_users' => $totalUsers,
            'high_engagement_users' => $highEngagementUsers,
            'high_engagement_rate' => $totalUsers > 0 ? $highEngagementUsers / $totalUsers : 0,
            'avg_interactions_per_user' => $engagementData->avg('total_interactions'),
            'avg_businesses_per_user' => $engagementData->avg('unique_businesses'),
            'avg_active_days' => $engagementData->avg('active_days')
        ];
    }

    private function storeAnalyticsResults($conversionMetrics, $engagementMetrics): void
    {
        PersonalizationMetrics::create([
            'experiment_name' => 'recommendation_effectiveness',
            'user_id' => null, // System-wide metrics
            'variant' => 'analysis',
            'event_type' => 'analytics_report',
            'event_data' => [
                'timeframe' => $this->timeframe,
                'conversion_metrics' => $conversionMetrics,
                'engagement_metrics' => $engagementMetrics,
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Recommendation effectiveness analysis job failed', [
            'timeframe' => $this->timeframe,
            'error' => $exception->getMessage()
        ]);
    }
}
