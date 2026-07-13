<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalHealthController extends Controller
{
    public function __invoke(Request $request, SystemHealthReport $report): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $report->generate();

        return response()->json($data, $data['status'] === 'down' ? 503 : 200);
    }

    private function authorized(Request $request): bool
    {
        $expected = (string) config('health.internal_token');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-Internal-Health-Token'));

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }
}
