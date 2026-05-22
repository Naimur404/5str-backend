<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Health endpoints for orchestration:
 *   GET /api/healthz   → liveness  (fast, never fails unless the app is broken)
 *   GET /api/readyz    → readiness (probes every external dependency)
 *
 * Both endpoints are intentionally unauthenticated so a load balancer or
 * k8s controller can hit them without secrets. They expose only the
 * shape the orchestrator needs — no request bodies, no PII.
 */
class HealthController extends Controller
{
    public function __construct(private readonly HealthCheckService $health)
    {
    }

    public function live(): JsonResponse
    {
        return response()->json($this->health->liveness());
    }

    public function ready(): JsonResponse
    {
        $report = $this->health->readiness();

        return response()->json(
            $report,
            $report['status'] === 'up' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
