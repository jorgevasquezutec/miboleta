<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Basic health check - returns 200 if app is running.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Detailed health check - verifies all services.
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'services' => [],
        ];

        // Check Database
        try {
            DB::connection()->getPdo();
            $health['services']['database'] = [
                'status' => 'ok',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $health['services']['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
            $health['status'] = 'degraded';
        }

        // Check Redis
        try {
            Redis::ping();
            $health['services']['redis'] = [
                'status' => 'ok',
            ];
        } catch (\Exception $e) {
            $health['services']['redis'] = [
                'status' => 'error',
                'message' => 'Redis connection failed',
            ];
            $health['status'] = 'degraded';
        }

        // Check Cache
        try {
            $cacheKey = 'health_check_' . uniqid();
            Cache::put($cacheKey, true, 10);
            $cacheWorks = Cache::get($cacheKey) === true;
            Cache::forget($cacheKey);

            $health['services']['cache'] = [
                'status' => $cacheWorks ? 'ok' : 'error',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            $health['services']['cache'] = [
                'status' => 'error',
                'message' => 'Cache check failed',
            ];
            $health['status'] = 'degraded';
        }

        // Check Storage
        try {
            $storagePath = storage_path('app');
            $isWritable = is_writable($storagePath);

            $health['services']['storage'] = [
                'status' => $isWritable ? 'ok' : 'error',
            ];
        } catch (\Exception $e) {
            $health['services']['storage'] = [
                'status' => 'error',
                'message' => 'Storage check failed',
            ];
            $health['status'] = 'degraded';
        }

        // App info
        $health['app'] = [
            'name' => config('app.name'),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        $statusCode = $health['status'] === 'ok' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Readiness check - for Kubernetes/Docker orchestration.
     * Returns 200 only if the app is ready to receive traffic.
     */
    public function ready(): JsonResponse
    {
        try {
            // Must have database connection
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'ready',
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'not_ready',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }

    /**
     * Liveness check - for Kubernetes/Docker orchestration.
     * Returns 200 if the app process is alive.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
